"""Browser acceptance of the existing sandbox, using an isolated server and fictitious accounts."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import socket
import subprocess
import time

from sync_runtime import verify_build

app = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--state', type=Path, required=True)
parser.add_argument('--output', type=Path, required=True)
parser.add_argument('--existing', action='store_true', help='Read-only verification of the completed scenario')
args = parser.parse_args()
state_path = args.state.resolve()
out = args.output.resolve()
if not state_path.is_relative_to((app / '.local/academic-close').resolve()) or not out.is_relative_to((app / 'evidence').resolve()):
    raise ValueError('Private acceptance state and application evidence directory required')
state = json.loads(state_path.read_text(encoding='utf-8'))
env = dict(state['scan_env'])
if env['DB_HOST'] != '127.0.0.1' or not env['DB_DATABASE'].startswith('ficct_applicants_'):
    raise ValueError('Isolated acceptance database required')
verify_build(app)
env['APP_URL'] = 'http://127.0.0.1:8121'
env['SESSION_COOKIE'] = 'ficct_payments_acceptance'
with socket.socket() as probe:
    probe.bind(('127.0.0.1', 8121))
out.mkdir(parents=True, exist_ok=True)
sources = {str(p.relative_to(app)): hashlib.sha256(p.read_bytes()).hexdigest() for folder in ('app', 'config', 'resources', 'routes', 'public/build') for p in (app / folder).rglob('*') if p.is_file() and '.test.' not in p.name}
(out / 'source-used.json').write_text(json.dumps(sources, indent=2), encoding='utf-8')
server = None
try:
    with (out / 'http.log').open('w', encoding='utf-8') as log:
        server = subprocess.Popen(['php', '-S', '127.0.0.1:8121', str(state_path.parent / 'router.php')], cwd=app / 'public', env=env, stdout=log, stderr=log, creationflags=subprocess.CREATE_NO_WINDOW)
    time.sleep(.5)
    result = subprocess.run(['node', 'scripts/simulated_payments_browser.cjs', str(state_path), str(out), 'existing' if args.existing else 'new'], cwd=app, env=dict(os.environ, FICCT_PLAYWRIGHT_MODULE=str(Path.home() / '.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright')), capture_output=True, text=True, encoding='utf-8', timeout=360)
    (out / 'execution.txt').write_text(result.stdout + result.stderr, encoding='utf-8')
    assert all((app / p).is_file() and hashlib.sha256((app / p).read_bytes()).hexdigest() == h for p, h in sources.items()), 'Source changed during acceptance'
    print(result.stdout + result.stderr)
    raise SystemExit(result.returncode)
finally:
    if server:
        server.terminate()
        server.wait(timeout=10)
