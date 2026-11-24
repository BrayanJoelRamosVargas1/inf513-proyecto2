"""Build and verify the existing academic tarball from current source; never deploys."""
from pathlib import Path
import argparse
import datetime
import hashlib
import gzip
import io
import json
import os
import re
import shutil
import tarfile
from sync_runtime import ROOT, safe_path, verify_build

CANONICAL = 'fc7bd40b944002fc698eb56234816de4b36ce4ee11486e335673edd8f17e11e2'


def collect(root):
    names = set()
    for folder in ('app', 'config', 'database/migrations', 'database/factories', 'database/seeders',
                   'lang', 'resources', 'routes', 'scripts', 'tests', 'docs'):
        for p in (root / folder).rglob('*'):
            if p.is_file() and '__pycache__' not in p.parts and p.suffix not in ('.pyc', '.dump', '.sqlite'):
                names.add(p.relative_to(root).as_posix())
    names.update(['artisan','bootstrap/app.php','bootstrap/providers.php','README.md','.gitignore',
                  '.editorconfig','.env.example','composer.json','composer.lock','package.json',
                  'package-lock.json','phpunit.xml','vite.config.js'])
    names.update(p.relative_to(root).as_posix() for p in (root/'public').iterdir() if p.is_file() and p.name!='hot')
    names.update(p.relative_to(root).as_posix() for p in (root/'public/build').rglob('*') if p.is_file())
    # Evidence for this archive's own installation stays external, under
    # evidence/release-acceptance: including its own hash would be circular.
    for folder in ('evidence/frontend-redesign', 'evidence/academic-close', 'evidence/clean-install-current'):
        for p in (root/folder).rglob('*'):
            if p.is_file() and not any(x in p.name for x in ('fallo','failure','intento','diagnostico','progreso')):
                if p != root/'evidence/academic-close/package.json':
                    names.add(p.relative_to(root).as_posix())
    journeys = sorted((root/'evidence/academic-business').glob('*/http-restauracion-preservacion.json'))
    passed = [p for p in journeys if json.loads(p.read_text(encoding='utf-8')).get('resultado') == 'PASS']
    if passed:
        for p in passed[-1].parent.rglob('*'):
            if p.is_file() and not any(x in p.name for x in ('fallo','failure','intento')):
                names.add(p.relative_to(root).as_posix())
    content={name:safe_path(root,name).read_bytes() for name in sorted(names) if (root/name).is_file()}
    # Only empty runtime directories, never caches, sessions, uploads, dumps or credentials.
    for folder in ('bootstrap/cache','storage/app/private','storage/framework/cache/data',
                   'storage/framework/sessions','storage/framework/views','storage/logs'):
        content[folder+'/.gitignore']=b'*\n!.gitignore\n'
    return content


def reject_secrets(content, secrets):
    for name,data in content.items():
        if any(p in Path(name).parts for p in ('.local','.git','vendor','node_modules')) or Path(name).name in ('.env','auth.json','ACCESOS_DEMO.txt'):
            raise ValueError('Private file selected: '+name)
        if any(value.encode() in data for value in secrets if len(value)>7):
            raise ValueError('Known private credential found in '+name)
        if re.search(rb'gh[pousr]_[A-Za-z0-9]{30,}|-----BEGIN (?:RSA |OPENSSH )?PRIVATE KEY-----',data):
            raise ValueError('Credential pattern found in '+name)


def secrets_from(root, runtime):
    values=[]
    for file in (root/'.env',runtime/'.env'):
        for line in file.read_text(encoding='utf-8').splitlines():
            key,sep,value=line.partition('=')
            if sep and key in ('APP_KEY','DB_PASSWORD','MAIL_PASSWORD','AWS_SECRET_ACCESS_KEY'):
                values.append(value.strip().strip('"\''))
    for line in (runtime/'.local/ACCESOS_DEMO.txt').read_text(encoding='utf-8').splitlines():
        if '|' in line:
            values.append(line.split('|',1)[1].strip())
    cfg=json.loads((runtime/'.local/database.json').read_text(encoding='utf-8'))
    values.append(cfg['password'])
    return values


def verify_archive(path, expected):
    with tarfile.open(path,'r:gz') as archive:
        members=archive.getmembers()
        names=[m.name for m in members]
        if len(names)!=len(set(names)):
            raise ValueError('Duplicate archive entries')
        for m in members:
            if not m.isfile() or not m.name.startswith('sistema/') or '..' in Path(m.name).parts:
                raise ValueError('Unsafe archive member')
        data={m.name[len('sistema/'):]:archive.extractfile(m).read() for m in members}
    manifest=json.loads(data.pop('MANIFIESTO_SHA256.json'))
    calculated={n:hashlib.sha256(b).hexdigest() for n,b in data.items()}
    if manifest!=calculated or data!=expected:
        raise ValueError('Archive differs from reviewed current source')
    if calculated['docs/CU_Y_RF_CANONICOS.md']!=CANONICAL:
        raise ValueError('Canonical contract changed')
    return len(names),len(manifest)


def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument('--runtime',type=Path,required=True)
    p.add_argument('--output',type=Path,default=ROOT.parent/'2026-2_INF513-P2_grupo09sa.tar.gz')
    p.add_argument('--audit-root',type=Path,help='Existing closure matrices and sanitized current receipts; included as an immutable release snapshot')
    args=p.parse_args()
    verify_build(ROOT)
    expected=collect(ROOT)
    if args.audit_root:
        audit=args.audit_root.resolve()
        if audit != ROOT.parent/'AUDITORIA_INTEGRAL_INF513_20261123':
            raise ValueError('Use the existing project closure directory')
        for name in ('06_ACEPTACION_CU.csv','07_ACEPTACION_RF.csv','08_ACEPTACION_INF513.csv','09_ACEPTACION_ACCIONES_ROLES.csv','10_ESTADO_ACTUAL.md'):
            expected['docs/cierre/'+name]=(audit/name).read_bytes()
        for name in ('phpunit-compatibility-final.xml','react-tie-label-current.json','matrix-current-totals.json','preservation-closure-latest.json','tie-continuation-postgresql.json','shared-handlers-current.json'):
            expected['evidence/cierre/'+name]=(audit/'evidence'/name).read_bytes()
        for folder,name in (('closure-legacy-reads-verified','legacy-compatibility.json'),('closure-legacy-aliases-verified','legacy-aliases.json'),('closure-tie-label-verified','tie-readback-ui.json')):
            expected['evidence/cierre/'+name]=(ROOT/'evidence/integral-audit'/folder/name).read_bytes()
    reject_secrets(expected,secrets_from(ROOT,args.runtime.resolve()))
    digest={n:hashlib.sha256(b).hexdigest() for n,b in expected.items()}
    if digest['docs/CU_Y_RF_CANONICOS.md']!=CANONICAL:
        raise ValueError('Canonical contract changed')
    output=args.output.resolve()
    if not re.fullmatch(r'\d{4}-[12]_INF513-P2_grupo\d{2}sa\.tar\.gz',output.name):
        raise ValueError('Use the academic delivery filename required by INF513')
    # A failed build never replaces the previous delivery.
    temporary=output.with_name(output.name+'.candidate-tmp')
    if temporary.exists():
        raise ValueError('A prior candidate temporary requires inspection')
    try:
        all_content=dict(expected)
        all_content['MANIFIESTO_SHA256.json']=json.dumps(digest,indent=2).encode()
        with temporary.open('wb') as raw, gzip.GzipFile(fileobj=raw,mode='wb',filename='',mtime=0) as compressed, tarfile.open(fileobj=compressed,mode='w') as archive:
            for name,data in sorted(all_content.items()):
                info=tarfile.TarInfo('sistema/'+name)
                info.size=len(data)
                info.mode=0o644
                info.mtime=0
                archive.addfile(info,io.BytesIO(data))
        files,hashes=verify_archive(temporary,expected)
        previous=None
        if output.exists():
            previous=hashlib.sha256(output.read_bytes()).hexdigest()
            backup=ROOT/'.local/release-backups'/previous/output.name
            backup.parent.mkdir(parents=True,exist_ok=True)
            if not backup.exists():
                shutil.copyfile(output,backup)
        os.replace(temporary,output)
    finally:
        if temporary.exists():
            temporary.unlink()
    report={'result':'PASS','created_at_utc':datetime.datetime.now(datetime.timezone.utc).isoformat(),
            'filename':output.name,'files':files,'verified_hashes':hashes,
            'sha256':hashlib.sha256(output.read_bytes()).hexdigest(),'previous_package_sha256_preserved':previous,
            'known_secret_scan':'PASS','source_matches_archive':True,'remote_deployment':False,
            'installed_independently_from_archive':False,'NO_GO_INSTITUCIONAL':True}
    output.with_name(output.name+'.sha256').write_text(report['sha256']+'  '+output.name+'\n',encoding='utf-8')
    out=ROOT/'evidence/academic-close/package.json'
    out.parent.mkdir(exist_ok=True,parents=True)
    out.write_text(json.dumps(report,indent=2),encoding='utf-8')
    print(json.dumps(report))


if __name__=='__main__':
    main()
