"""Reejecuta PHPUnit en ficct_test conservando los registros y la DEMO existentes."""
from pathlib import Path
import argparse
import datetime
import hashlib
import json
import os
import subprocess
import sys
import xml.etree.ElementTree as ET
from test_environment import isolated_test_environment

if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8', errors='replace')

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'evidence/validacion-local-03'


def fingerprint_demo(config):
    pg = Path(os.environ.get('FICCT_PG_BIN', r'C:\Program Files\PostgreSQL\15\bin'))
    sql = "SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename"
    def query(sql):
        return subprocess.check_output([
            str(pg / 'psql.exe'), '-h', '127.0.0.1', '-p', str(config['port']),
            '-U', config['user'], '-d', config.get('database', 'ficct_demo'), '-tAc', sql,
        ], env=dict(os.environ, PGPASSWORD=config['password']), text=True, encoding='utf-8', creationflags=subprocess.CREATE_NO_WINDOW).strip()
    tables = query(sql).splitlines()
    digests = {table: query('SELECT md5(COALESCE(jsonb_agg(to_jsonb(t) ORDER BY to_jsonb(t)::text)::text,\'[]\')) FROM "' + table.replace('"', '""') + '" t') for table in tables}
    files = {str(p.relative_to(ROOT / 'storage/app/private')): hashlib.sha256(p.read_bytes()).hexdigest() for p in (ROOT / 'storage/app/private').rglob('*') if p.is_file()}
    return {'tables': digests, 'files': files}


def run():
    OUT.mkdir(exist_ok=True, parents=True)
    config = json.loads((ROOT / '.local/database.json').read_text(encoding='utf-8'))
    before = fingerprint_demo(config)
    evidence = {str(p.relative_to(ROOT)): hashlib.sha256(p.read_bytes()).hexdigest() for p in (ROOT / 'evidence').rglob('*') if p.is_file() and OUT not in p.parents}
    env = dict(isolated_test_environment(ROOT), APP_ENV='testing', DB_CONNECTION='pgsql', DB_DATABASE='ficct_test',
               DB_HOST='127.0.0.1', DB_PORT=str(config['port']), DB_USERNAME=config['user'], DB_PASSWORD=config['password'],
               BCRYPT_ROUNDS='4', SESSION_DRIVER='array', CACHE_STORE='array', APP_DEBUG='false')
    assert env['DB_DATABASE'] == 'ficct_test'
    result = subprocess.run(['php', 'vendor/phpunit/phpunit/phpunit', '--colors=never', '--log-junit', str(OUT / 'phpunit.xml')],
                            cwd=ROOT, env=env, capture_output=True, text=True, encoding='utf-8', errors='replace')
    (OUT / 'tests.txt').write_text(result.stdout + result.stderr, encoding='utf-8')
    print(result.stdout + result.stderr)
    after = fingerprint_demo(config)
    historical_preserved = all(hashlib.sha256((ROOT / name).read_bytes()).hexdigest() == digest for name, digest in evidence.items())
    assert result.returncode == 0, 'PHPUnit falló; consulte tests.txt'
    assert before == after, 'Cambió la DEMO existente'
    assert historical_preserved, 'Cambió una evidencia previa'
    suite = ET.parse(OUT / 'phpunit.xml').getroot().find('testsuite')
    previous = ET.parse(ROOT / 'evidence/phpunit.xml').findall('.//testcase')
    current = ET.parse(OUT / 'phpunit.xml').findall('.//testcase')
    key = lambda c: (c.attrib['class'], c.attrib['name'])
    previous_keys = {key(c) for c in previous}
    current_keys = {key(c) for c in current}
    assert previous_keys <= current_keys
    report = {'result': 'PASS', 'executed_at_utc': datetime.datetime.now(datetime.timezone.utc).isoformat(),
              'database': 'ficct_test', 'database_host': '127.0.0.1', 'database_port': config['port'],
              'new_local_execution': True, 'tests': int(suite.attrib['tests']), 'assertions': int(suite.attrib['assertions']),
              'errors': int(suite.attrib['errors']), 'failures': int(suite.attrib['failures']),
              'previous_test_identities_preserved': len(previous_keys), 'new_tests': len(current_keys - previous_keys),
              'demo_tables_preserved': len(before['tables']),
              'demo_private_files_preserved': len(before['files']), 'historical_evidence_files_preserved': len(evidence),
              'scope': 'Same-machine local execution; not an external audit or institutional acceptance'}
    (OUT / 'regresion.json').write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    print(json.dumps(report, ensure_ascii=False))


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--output-dir', type=Path, default=OUT)
    args = parser.parse_args()
    OUT = args.output_dir.resolve()
    run()
