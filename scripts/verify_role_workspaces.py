"""UI acceptance using the retained isolated academic copy; mutations stay in that copy."""
import argparse
import json
import os
from pathlib import Path
import socket
import subprocess
import time
import threading
import hashlib
from sync_runtime import verify_build

app = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--state', type=Path, required=True)
parser.add_argument('--scope', choices=('roles', 'themes', 'functional-call', 'functional-lifecycle', 'functional-audit', 'functional-participation', 'competitive', 'administration', 'analytics', 'amendments', 'institutional', 'scope-configuration', 'tie-continuation', 'legacy-compatibility'), default='roles')
parser.add_argument('--phase', choices=('setup','applications','reviews','grades','results','rectification','concurrency','readback'), default='setup')
parser.add_argument('--output', type=Path, help='Evidence directory within this application')
args = parser.parse_args()
state_path = args.state.resolve()
if not state_path.is_relative_to((app / '.local/academic-close').resolve()):
    raise ValueError('Use the retained private academic acceptance copy')
state = json.loads(state_path.read_text(encoding='utf-8'))
env = state['scan_env']
if not env['DB_DATABASE'].startswith('ficct_applicants_') or env['DB_HOST'] != '127.0.0.1':
    raise ValueError('Isolated local acceptance database required')
if state['base'] != 'http://127.0.0.1:8119':
    raise ValueError('Unexpected acceptance endpoint')
out = args.output.resolve() if args.output else app / 'evidence/role-review/browser'
if not out.resolve().is_relative_to((app / 'evidence').resolve()):
    raise ValueError('Evidence must remain within the application evidence directory')
out.mkdir(parents=True, exist_ok=True)
with socket.socket() as probe:
    probe.bind(('127.0.0.1', 8119))
server = None
second_server = None
worker_stop = threading.Event()
worker_thread = None

def source_fingerprint():
    files = [p for folder in ('app', 'config', 'routes', 'resources', 'public/build') for p in (app / folder).rglob('*') if p.is_file() and '.test.' not in p.name]
    files += [app / name for name in ('bootstrap/app.php', 'bootstrap/providers.php', 'composer.lock', 'package-lock.json', 'docs/CU_Y_RF_CANONICOS.md')]
    return {p.relative_to(app).as_posix(): hashlib.sha256(p.read_bytes()).hexdigest() for p in sorted(files)}

def capture_call_snapshot(name, call_id=None):
    fixture_path = state_path.parent / 'functional-call-private.json'
    if call_id is None and not fixture_path.exists():
        return
    if call_id is None:
        call_id = int(json.loads(fixture_path.read_text(encoding='utf-8'))['call'])
    # Independent read-only PostgreSQL evidence; never used to prepare or advance the scenario.
    sql = f"""SELECT jsonb_build_object(
      'call',(SELECT to_jsonb(c) FROM calls c WHERE id={call_id}),
      'offers',(SELECT jsonb_agg(o ORDER BY id) FROM offers o WHERE call_id={call_id}),
      'memberships',(SELECT jsonb_agg(m ORDER BY id) FROM memberships m WHERE call_id={call_id}),
      'assignments',(SELECT jsonb_agg(x ORDER BY x.id) FROM assignments x JOIN offers o ON o.id=x.offer_id WHERE o.call_id={call_id}),
      'applications',(SELECT jsonb_agg(a ORDER BY a.id) FROM applications a JOIN offers o ON o.id=a.offer_id WHERE o.call_id={call_id}),
      'documents',(SELECT jsonb_agg(x) FROM (SELECT d.id,d.application_id,d.kind,d.version,d.sha256,d.mime FROM documents d JOIN applications a ON a.id=d.application_id JOIN offers o ON o.id=a.offer_id WHERE o.call_id={call_id} ORDER BY d.id) x),
      'scan_results',(SELECT jsonb_agg(x) FROM (SELECT r.*,j.document_id FROM institutional_scan_results r JOIN institutional_scan_jobs j ON j.id=r.job_id JOIN documents d ON d.id=j.document_id JOIN applications a ON a.id=d.application_id JOIN offers o ON o.id=a.offer_id WHERE o.call_id={call_id} ORDER BY r.id) x),
      'grades',(SELECT jsonb_agg(g ORDER BY g.id) FROM grades g JOIN applications a ON a.id=g.application_id JOIN offers o ON o.id=a.offer_id WHERE o.call_id={call_id}),
      'publications',(SELECT jsonb_agg(p ORDER BY p.id) FROM publications p JOIN offers o ON o.id=p.offer_id WHERE o.call_id={call_id}),
      'audit_events',(SELECT count(*) FROM audit_events WHERE call_id={call_id}))"""
    command = ['C:/Program Files/PostgreSQL/15/bin/psql.exe', '-X', '-v', 'ON_ERROR_STOP=1', '-h', env['DB_HOST'], '-p', str(env['DB_PORT']), '-U', env['DB_USERNAME'], '-d', env['DB_DATABASE'], '-tAc', sql]
    result = subprocess.run(command, env=dict(os.environ, PGPASSWORD=env['DB_PASSWORD']), capture_output=True, text=True, encoding='utf-8', check=True)
    (out / name).write_text(json.dumps(json.loads(result.stdout), ensure_ascii=False, indent=2), encoding='utf-8')

def isolated_worker():
    with (out / 'real-worker.txt').open('a', encoding='utf-8') as log:
        while not worker_stop.is_set():
            result = subprocess.run(['php', 'artisan', 'ficct:scan-worker', '--once', '--max-seconds=30'], cwd=app, env=env, stdout=log, stderr=log, timeout=150)
            if result.returncode:
                log.write('Worker returned a failure; documents remain blocked.\n')
                log.flush()
            worker_stop.wait(1)

try:
    verify_build(app)
    version_before = source_fingerprint()
    (out / 'source-before.json').write_text(json.dumps(version_before, indent=2), encoding='utf-8')
    if args.scope == 'competitive':
        fixture_file = state_path.parent / 'competitive-private.json'
        if not fixture_file.exists():
            preparation = subprocess.run(['php', str(app / 'scripts/prepare_competitive_fixture.php'), str(fixture_file)], cwd=app, env=env, capture_output=True, text=True, encoding='utf-8', check=True)
            (out / 'fixture-preparation.json').write_text(preparation.stdout, encoding='utf-8')
        competitive = json.loads(fixture_file.read_text(encoding='utf-8'))
        for call_id in competitive['calls'].values():
            capture_call_snapshot(f'postgresql-before-{call_id}.json', call_id)
    with (state_path.parent / 'role-http.log').open('w', encoding='utf-8') as log:
        server = subprocess.Popen(['php', '-S', '127.0.0.1:8119', str(state_path.parent / 'router.php')], cwd=app / 'public', env=env, stdout=log, stderr=log, creationflags=subprocess.CREATE_NO_WINDOW)
    if args.scope == 'competitive' and args.phase == 'concurrency':
        with socket.socket() as probe:
            probe.bind(('127.0.0.1', 8120))
        with (out / 'second-http.log').open('w', encoding='utf-8') as log:
            second_server = subprocess.Popen(['php', '-S', '127.0.0.1:8120', str(state_path.parent / 'router.php')], cwd=app / 'public', env=env, stdout=log, stderr=log, creationflags=subprocess.CREATE_NO_WINDOW)
    time.sleep(.5)
    if args.scope in ('functional-lifecycle', 'functional-participation'):
        capture_call_snapshot('postgresql-before.json')
    if args.scope in ('functional-lifecycle', 'amendments', 'institutional', 'scope-configuration', 'tie-continuation', 'legacy-compatibility') or (args.scope == 'competitive' and args.phase == 'applications'):
        worker_thread = threading.Thread(target=isolated_worker, daemon=True)
        worker_thread.start()
    script = {'roles': 'role_workspaces_browser.cjs', 'themes': 'theme_role_browser.cjs', 'functional-call': 'functional_call_browser.cjs', 'functional-lifecycle': 'functional_lifecycle_browser.cjs', 'functional-audit':'functional_audit_browser.cjs', 'functional-participation':'functional_participation_browser.cjs', 'competitive':'competitive_browser.cjs', 'administration':'closure_administration_browser.cjs', 'analytics':'closure_analytics_browser.cjs', 'amendments':'closure_amendment_browser.cjs', 'institutional':'closure_institutional_browser.cjs', 'scope-configuration':'closure_scope_browser.cjs', 'tie-continuation':'closure_tie_browser.cjs', 'legacy-compatibility':'verify_legacy_compatibility.cjs'}[args.scope]
    instrument_before = hashlib.sha256((app / 'scripts' / script).read_bytes()).hexdigest()
    result = subprocess.run(['node', str(app / 'scripts' / script), str(state_path), str(out), args.phase], cwd=app, env=dict(os.environ, FICCT_PLAYWRIGHT_MODULE=str(Path.home() / '.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright')), capture_output=True, text=True, encoding='utf-8', timeout=1800 if args.scope == 'competitive' else 420)
    (out / 'execution.txt').write_text(result.stdout + result.stderr, encoding='utf-8')
    version_after = source_fingerprint()
    instrument_after = hashlib.sha256((app / 'scripts' / script).read_bytes()).hexdigest()
    version = {'source_before_sha256': hashlib.sha256(json.dumps(version_before, sort_keys=True).encode()).hexdigest(), 'source_after_sha256': hashlib.sha256(json.dumps(version_after, sort_keys=True).encode()).hexdigest(), 'changed_during_run': [name for name in sorted(version_before.keys() | version_after.keys()) if version_before.get(name) != version_after.get(name)], 'instrument_before_sha256': instrument_before, 'instrument_after_sha256': instrument_after, 'instrument_changed_during_run': instrument_before != instrument_after, 'scope': 'Application source and matching compiled frontend; test credentials and private files excluded.'}
    (out / 'source-version.json').write_text(json.dumps(version, indent=2), encoding='utf-8')
    if version['changed_during_run']:
        raise RuntimeError('Application source changed during acceptance; results need review')
    if args.scope.startswith('functional-'):
        capture_call_snapshot('postgresql-after.json')
    if args.scope == 'competitive':
        for call_id in competitive['calls'].values():
            capture_call_snapshot(f'postgresql-after-{call_id}.json', call_id)
    print(result.stdout + result.stderr)
    raise SystemExit(result.returncode)
finally:
    worker_stop.set()
    if worker_thread:
        worker_thread.join(timeout=155)
    if server:
        server.terminate()
        server.wait(timeout=10)
    if second_server:
        second_server.terminate()
        second_server.wait(timeout=10)
