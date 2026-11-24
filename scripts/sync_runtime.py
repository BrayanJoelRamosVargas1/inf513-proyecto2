"""Explicit local code synchronization. Never touches .env, vendor, storage or the database."""
from pathlib import Path
import argparse
import datetime
import hashlib
import json
import os
import shutil
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
FOLDERS = ('app', 'config', 'database/migrations', 'database/factories', 'database/seeders',
           'lang', 'resources', 'routes', 'scripts')
ROOT_FILES = ('artisan', 'bootstrap/app.php', 'bootstrap/providers.php', 'composer.json',
              'composer.lock', 'package.json', 'package-lock.json', 'vite.config.js')


def digest(file):
    return hashlib.sha256(file.read_bytes()).hexdigest()


def safe_path(root, name):
    rel = Path(name)
    if rel.is_absolute() or '..' in rel.parts:
        raise ValueError('Unsafe relative path')
    target = root / rel
    for part in [target, *target.parents]:
        if part == root.parent:
            break
        if part.is_symlink() or getattr(part, 'is_junction', lambda: False)():
            raise ValueError('Links are not allowed in managed paths')
    if not target.resolve().is_relative_to(root.resolve()):
        raise ValueError('Path escaped managed directory')
    return target


def inventory(root):
    names = set(ROOT_FILES)
    for folder in FOLDERS:
        for p in (root / folder).rglob('*'):
            if p.is_file() and '__pycache__' not in p.parts and p.suffix != '.pyc':
                names.add(p.relative_to(root).as_posix())
    names.update(p.relative_to(root).as_posix() for p in (root / 'public').iterdir()
                 if p.is_file() and p.name != 'hot')
    names.update(p.relative_to(root).as_posix() for p in (root / 'public/build').rglob('*') if p.is_file())
    return {name: digest(safe_path(root, name)) for name in sorted(names) if (root / name).is_file()}


def verify_build(root):
    receipt = json.loads((root / 'public/build/source-hashes.json').read_text(encoding='utf-8'))
    expected = {p.relative_to(root).as_posix() for p in (root / 'resources/js/portal').rglob('*')
                if p.is_file() and '.test.' not in p.name}
    expected.update(['package.json', 'package-lock.json', 'vite.config.js', 'scripts/write_frontend_build_manifest.mjs'])
    if set(receipt['sources']) != expected:
        raise ValueError('Frontend source inventory changed: run npm run build')
    for group in ('sources', 'artifacts'):
        for name, sha in receipt[group].items():
            if digest(safe_path(root, name)) != sha:
                raise ValueError('Frontend differs from its build: run npm run build')


def plan(root, target):
    verify_build(root)
    if root.resolve() == target.resolve() or not (target / 'artisan').is_file():
        raise ValueError('Choose an existing, different application runtime')
    if not (target / '.local/database.json').is_file():
        raise ValueError('Target is not a registered local runtime')
    # Dependency updates need an explicit install, not an accidental code-only deployment.
    for lock in ('composer.lock', 'package-lock.json'):
        if json.loads((root / lock).read_text(encoding='utf-8')) != json.loads((target / lock).read_text(encoding='utf-8')):
            raise ValueError('Dependency lock differs; install and validate dependencies first')
    hashes = inventory(root)
    changed = [n for n, sha in hashes.items() if not safe_path(target, n).is_file() or digest(safe_path(target, n)) != sha]
    return hashes, changed


def atomic_copy(source, target):
    target.parent.mkdir(parents=True, exist_ok=True)
    tmp = target.with_name(target.name + '.ficct-sync-tmp')
    if tmp.exists():
        raise ValueError('A previous synchronization temporary exists')
    try:
        shutil.copyfile(source, tmp)
        os.replace(tmp, target)
    finally:
        if tmp.exists():
            tmp.unlink()


def restore(target, backup, records):
    for name in reversed(list(records)):
        dst = safe_path(target, name)
        if records[name]['existed']:
            atomic_copy(backup / 'files' / name, dst)
        elif dst.is_file():
            dst.unlink()  # Only an exact file recorded as newly created by this synchronization.


def apply(root, target, hashes, changed, backup_root, refresh=True):
    stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d%H%M%S%f')
    backup = backup_root / stamp
    backup.mkdir(parents=True, mode=0o700)
    names = list(changed)
    for p in (target / 'bootstrap/cache').glob('routes-*.php'):
        name = p.relative_to(target).as_posix()
        if name not in names:
            names.append(name)
    records = {}
    for name in names:
        dst = safe_path(target, name)
        records[name] = {'existed': dst.is_file()}
        if dst.is_file():
            stored = backup / 'files' / name
            stored.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(dst, stored)
            records[name]['before_sha256'] = digest(dst)
    state = {'target': str(target.resolve()), 'records': records, 'status': 'prepared'}
    state_file = backup / 'state.json'
    state_file.write_text(json.dumps(state, indent=2), encoding='utf-8')
    try:
        # Keep old assets; switch the Vite manifest only after all required assets exist.
        for name in sorted(changed, key=lambda n: n == 'public/build/manifest.json'):
            if digest(safe_path(root, name)) != hashes[name]:
                raise ValueError('Source changed during synchronization')
            atomic_copy(safe_path(root, name), safe_path(target, name))
        if refresh:
            for command in ('route:cache', 'view:clear'):
                r = subprocess.run(['php', 'artisan', command], cwd=target, capture_output=True, timeout=60)
                for cache in (target / 'bootstrap/cache').glob('routes-*.php'):
                    name = cache.relative_to(target).as_posix()
                    records.setdefault(name, {'existed': False})
                (backup / (command.replace(':', '-') + '.log')).write_bytes(r.stdout + r.stderr)
                if r.returncode:
                    raise RuntimeError('Cache refresh failed; see private backup log')
        if any(digest(safe_path(target, n)) != sha for n, sha in hashes.items()):
            raise ValueError('Runtime verification failed')
        if inventory(root) != hashes:
            raise ValueError('Source changed during synchronization')
        state['status'] = 'applied'
        for name in records:
            dst = safe_path(target, name)
            records[name]['after_sha256'] = digest(dst) if dst.is_file() else None
    except Exception:
        restore(target, backup, records)
        state['status'] = 'automatically_rolled_back'
        state_file.write_text(json.dumps(state, indent=2), encoding='utf-8')
        raise
    state_file.write_text(json.dumps(state, indent=2), encoding='utf-8')
    return backup


def rollback(target, backup):
    state = json.loads((backup / 'state.json').read_text(encoding='utf-8'))
    if state['status'] != 'applied' or Path(state['target']).resolve() != target.resolve():
        raise ValueError('Backup does not belong to this target or is not applied')
    for name, record in state['records'].items():
        dst = safe_path(target, name)
        current = digest(dst) if dst.is_file() else None
        if current != record['after_sha256']:
            raise ValueError('Runtime changed since synchronization; review before rollback')
        if record['existed'] and digest(backup / 'files' / name) != record['before_sha256']:
            raise ValueError('Backup integrity mismatch')
    restore(target, backup, state['records'])
    state['status'] = 'manually_rolled_back'
    (backup / 'state.json').write_text(json.dumps(state, indent=2), encoding='utf-8')


def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--target', type=Path, required=True)
    mode = p.add_mutually_exclusive_group()
    mode.add_argument('--apply', action='store_true')
    mode.add_argument('--rollback', type=Path)
    args = p.parse_args()
    target = args.target.resolve()
    if args.rollback:
        rollback(target, args.rollback.resolve())
        print(json.dumps({'result': 'ROLLED_BACK', 'database_modified': False}))
        return 0
    hashes, changed = plan(ROOT, target)
    if args.apply:
        lock = target / '.local/ficct-sync.lock'
        fd = os.open(lock, os.O_CREAT | os.O_EXCL | os.O_WRONLY, 0o600)
        try:
            os.write(fd, str(os.getpid()).encode())
            os.close(fd)
            backup = apply(ROOT, target, hashes, changed, ROOT / '.local/runtime-sync')
        finally:
            lock.unlink()
        print(json.dumps({'result': 'PASS', 'copied': changed, 'backup': str(backup), 'database_modified': False}))
        return 0
    print(json.dumps({'result': 'MATCH' if not changed else 'DIFFERENCES', 'changed': changed,
                      'managed_files': len(hashes), 'database_modified': False}))
    return 1 if changed else 0


if __name__ == '__main__':
    try:
        sys.exit(main())
    except Exception as e:
        print(str(e), file=sys.stderr)
        sys.exit(2)
