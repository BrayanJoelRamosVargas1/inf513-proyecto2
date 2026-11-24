"""Accept a release in a disposable directory and NEW PostgreSQL cluster.

Requires installed PHP/Composer/Node/npm/PostgreSQL; reads no DEMO configuration,
uploads or installed dependencies. Stops the cluster on exit; retains evidence.
Never creates OS services, deploys, or deletes an existing database/directory.
"""
import argparse
import base64
import datetime
import gzip
import hashlib
import http.cookiejar
import json
import os
from pathlib import Path, PurePosixPath
import secrets
import shutil
import socket
import subprocess
import tarfile
import time
import urllib.request
import xml.etree.ElementTree as ET

CANONICAL = 'fc7bd40b944002fc698eb56234816de4b36ce4ee11486e335673edd8f17e11e2'


def verified_content(archive):
    with gzip.open(archive, 'rb') as stream:
        expanded = 0
        while block := stream.read(1024 * 1024):
            expanded += len(block)
            if expanded > 600 * 1024 * 1024:
                raise ValueError('Expanded release exceeds the 600 MiB limit')
            # Consume EOF to validate gzip CRC, too.
    with tarfile.open(archive, 'r:gz') as stream:
        members = stream.getmembers()
        if sum(m.size for m in members) > 512 * 1024 * 1024:
            raise ValueError('Release exceeds the 512 MiB extraction limit')
        names, data = set(), {}
        for m in members:
            p = PurePosixPath(m.name)
            if (not m.isfile() or not m.name.startswith('sistema/') or '\\' in m.name
                    or ':' in m.name or p.is_absolute() or '..' in p.parts
                    or p.as_posix() != m.name or m.name.casefold() in names):
                raise ValueError('Unsafe or duplicate release member')
            names.add(m.name.casefold())
            if any(x in p.parts for x in ('.local', '.git', 'vendor', 'node_modules')) or p.name == '.env':
                raise ValueError('Private or installed content in release')
            data[p.relative_to('sistema').as_posix()] = stream.extractfile(m).read()
    manifest = json.loads(data.pop('MANIFIESTO_SHA256.json'))
    if manifest != {n: hashlib.sha256(v).hexdigest() for n, v in data.items()}:
        raise ValueError('Release manifest mismatch')
    if manifest.get('docs/CU_Y_RF_CANONICOS.md') != CANONICAL:
        raise ValueError('Canonical contract mismatch')
    return data, manifest


def free_port():
    with socket.socket() as probe:
        probe.bind(('127.0.0.1', 0))
        return probe.getsockname()[1]


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    for name in ('archive', 'work-root', 'pg-bin', 'report'):
        parser.add_argument('--' + name, type=Path, required=True)
    args = parser.parse_args()
    content, manifest = verified_content(args.archive)
    stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d%H%M%S') + '-' + secrets.token_hex(3)
    work = args.work_root.resolve() / stamp
    work.mkdir(parents=True, exist_ok=False)
    app = work / 'sistema'
    app.mkdir()
    for name, data in content.items():
        target = app / name
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(data)
    pg, suffix = args.pg_bin.resolve(), '.exe' if os.name == 'nt' else ''
    for binary in ('initdb', 'pg_ctl', 'psql', 'createdb', 'pg_dump', 'pg_restore'):
        if not (pg / (binary + suffix)).is_file():
            raise ValueError('Missing PostgreSQL tool: ' + binary)
    composer, npm = shutil.which('composer'), shutil.which('npm.cmd' if os.name == 'nt' else 'npm')
    if not composer or not npm or not shutil.which('php'):
        raise ValueError('PHP, Composer and npm must already be installed')
    phar = Path(composer).with_name('composer.phar')
    composer_cmd = ['php', str(phar)] if phar.is_file() else [composer]
    dbport, webport = free_port(), free_port()
    while webport == dbport:
        webport = free_port()
    admin_pw, role_pw = secrets.token_hex(24), secrets.token_hex(24)
    env = {k: v for k, v in os.environ.items() if not k.startswith(('DB_', 'APP_', 'LARAVEL_', 'FICCT_'))}
    env.update(PGPASSWORD=admin_pw, PGCONNECT_TIMEOUT='10', PHP_CLI_SERVER_WORKERS='1')
    pwfile = work / 'init-password'
    pwfile.write_text(admin_pw, encoding='utf-8')
    report = {'result': 'RUNNING', 'archive_sha256': hashlib.sha256(args.archive.read_bytes()).hexdigest(),
              'started_at_utc': datetime.datetime.now(datetime.timezone.utc).isoformat(),
              'driver_matches_archive': hashlib.sha256(Path(__file__).read_bytes()).hexdigest() == manifest.get('scripts/verify_clean_install.py'),
              'archive_files': len(content) + 1, 'manifest_hashes': len(manifest),
              'canonical_sha256': CANONICAL, 'isolated_new_cluster': True,
              'private_storage_new': True, 'dependencies_copied_from_demo': False,
              'operational_configuration_read': False, 'remote_writes': False,
              'NO_GO_INSTITUCIONAL': True, 'checks': [], 'work_directory': str(work)}
    cluster_started, server = False, None
    def run(label, command, custom_env=None, timeout=600):
        logfile = work / (label + '.log')
        # Daemons can inherit pipes on Windows: write to a file instead of waiting
        # for EOF from a background postgres process after pg_ctl has exited.
        with logfile.open('w', encoding='utf-8') as output:
            result = subprocess.run(command, cwd=app, env=custom_env or env,
                                    stdout=output, stderr=subprocess.STDOUT, timeout=timeout)
        if result.returncode:
            raise RuntimeError(label + ' failed; inspect the private installation log')
        report['checks'].append(label)
        return logfile.read_text(encoding='utf-8', errors='replace')
    def pgcmd(binary, arguments):
        return [str(pg / (binary + suffix)), '-h', '127.0.0.1', '-p', str(dbport), '-U', 'acceptance_admin'] + arguments
    def sql(database, query):
        return run('sql-' + secrets.token_hex(4), pgcmd('psql', ['-d', database, '-v', 'ON_ERROR_STOP=1', '-tAc', query])).strip()
    try:
        run('initdb', [str(pg / ('initdb' + suffix)), '-D', str(work / 'pgdata'), '-U', 'acceptance_admin',
                       '--pwfile=' + str(pwfile), '--auth=scram-sha-256', '--encoding=UTF8', '--no-locale'])
        pwfile.unlink()
        run('postgres-start', [str(pg / ('pg_ctl' + suffix)), '-D', str(work / 'pgdata'),
                              '-l', str(work / 'postgres.log'), '-o', f'-h 127.0.0.1 -p {dbport}', '-w', 'start'])
        cluster_started = True
        sql('postgres', f"CREATE ROLE acceptance_app LOGIN PASSWORD '{role_pw}' NOSUPERUSER NOCREATEDB NOCREATEROLE")
        for database in ('ficct_clean', 'ficct_test', 'ficct_restored'):
            run('create-' + database, pgcmd('createdb', ['-O', 'acceptance_app', database]))
        variables = {'APP_ENV': 'local', 'APP_DEBUG': 'false', 'APP_KEY': 'base64:' + base64.b64encode(secrets.token_bytes(32)).decode(),
                     'APP_URL': f'http://127.0.0.1:{webport}', 'DB_CONNECTION': 'pgsql', 'DB_HOST': '127.0.0.1',
                     'DB_PORT': str(dbport), 'DB_DATABASE': 'ficct_clean', 'DB_USERNAME': 'acceptance_app',
                     'DB_PASSWORD': role_pw, 'DB_SEARCH_PATH': 'public', 'SESSION_DRIVER': 'file',
                     'SESSION_COOKIE': 'ficct_clean_' + secrets.token_hex(8), 'CACHE_STORE': 'file',
                     'FICCT_CLAMSCAN_BINARY': '', 'FICCT_CLAMSCAN_DATABASE': ''}
        lines = []
        for line in (app / '.env.example').read_text(encoding='utf-8').splitlines():
            key = line.partition('=')[0]
            lines.append(key + '=' + json.dumps(variables.pop(key)) if key in variables else line)
        lines.extend(k + '=' + json.dumps(v) for k, v in variables.items())
        (app / '.env').write_text('\n'.join(lines) + '\n', encoding='utf-8')
        for folder in ('storage/app/private', 'storage/framework/cache/data', 'storage/framework/sessions',
                       'storage/framework/views', 'storage/logs', 'bootstrap/cache'):
            (app / folder).mkdir(parents=True, exist_ok=True)
        run('composer-install', composer_cmd + ['install', '--no-interaction', '--prefer-dist', '--no-progress'])
        run('composer-platform', composer_cmd + ['check-platform-reqs'])
        run('npm-ci', [npm, 'ci', '--no-audit', '--no-fund'])
        run('react-tests', [npm, 'test', '--', '--reporter=json', '--outputFile=' + str(work / 'vitest.json')])
        report['react_tests'] = json.loads((work / 'vitest.json').read_text(encoding='utf-8'))['numPassedTests']
        run('frontend-build', [npm, 'run', 'build'])
        for command in ('migrate', 'db:seed'):
            run(command.replace(':', '-'), ['php', 'artisan', command, '--force'])
        for command in ('config:cache', 'route:cache', 'view:cache', 'route:list'):
            run(command.replace(':', '-'), ['php', 'artisan', command])
        from test_environment import isolated_test_environment
        testenv = isolated_test_environment(app)
        testenv.update(DB_HOST='127.0.0.1', DB_PORT=str(dbport), DB_USERNAME='acceptance_app', DB_PASSWORD=role_pw,
                       FICCT_CLAMSCAN_BINARY='', FICCT_CLAMSCAN_DATABASE='')
        run('phpunit', ['php', 'vendor/phpunit/phpunit/phpunit', '--colors=never', '--log-junit', str(work / 'phpunit.xml')], custom_env=testenv)
        cases = ET.parse(work / 'phpunit.xml').findall('.//testcase')
        report.update(php_tests=len(cases), php_assertions=sum(int(c.get('assertions', '0')) for c in cases))
        tables = sql('ficct_clean', "SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE' ORDER BY table_name").splitlines()
        report['tables'] = len(tables)
        report['triggers'] = int(sql('ficct_clean', 'SELECT count(*) FROM pg_trigger WHERE NOT tgisinternal'))
        assert len(tables) >= 50 and report['triggers'] >= 16
        run('backup', pgcmd('pg_dump', ['-Fc', '-f', str(work / 'clean.dump'), 'ficct_clean']))
        run('restore', pgcmd('pg_restore', ['--no-owner', '--no-privileges', '-d', 'ficct_restored', str(work / 'clean.dump')]))
        for table in tables:
            query = f'SELECT md5(COALESCE(string_agg(row_data, E\'\\n\' ORDER BY row_data),\'\')) FROM (SELECT row_to_json(t)::text AS row_data FROM "{table}" t) q'
            assert sql('ficct_clean', query) == sql('ficct_restored', query), table
        report['restored_tables_identical'] = len(tables)
        trigger_query = "SELECT md5(string_agg(pg_get_triggerdef(oid), E'\\n' ORDER BY tgrelid::regclass::text,tgname)) FROM pg_trigger WHERE NOT tgisinternal"
        assert sql('ficct_clean', trigger_query) == sql('ficct_restored', trigger_query)
        report['restored_triggers'] = int(sql('ficct_restored', 'SELECT count(*) FROM pg_trigger WHERE NOT tgisinternal'))
        assert report['restored_triggers'] == report['triggers']
        with (work / 'http.log').open('w', encoding='utf-8') as output:
            server = subprocess.Popen(['php', 'artisan', 'serve', '--host=127.0.0.1', '--port=' + str(webport), '--no-reload'], cwd=app, env=env, stdout=output, stderr=output)
        base = f'http://127.0.0.1:{webport}'
        for attempt in range(50):
            try:
                with urllib.request.urlopen(base + '/p2', timeout=2) as response:
                    assert response.status == 200
                    html = response.read().decode()
                break
            except OSError:
                time.sleep(.2)
        else:
            raise RuntimeError('Clean HTTP installation did not start')
        build = json.loads((app / 'public/build/manifest.json').read_text(encoding='utf-8'))
        assets = sorted({asset for entry in build.values() for asset in [entry['file'], *entry.get('css', [])]})
        for asset in assets:
            with urllib.request.urlopen(base + '/build/' + asset) as response:
                assert response.status == 200 and len(response.read()) > 100
        credentials = {}
        for line in (app / '.local/ACCESOS_DEMO.txt').read_text(encoding='utf-8').splitlines():
            if '|' in line:
                email, password = (s.strip() for s in line.split('|', 1))
                credentials[email.split('@')[0]] = (email, password)
        for role in ('admin', 'autoridad', 'revisor', 'docente', 'estudiante'):
            opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
            def api(path, payload=None, token=None):
                headers = {'Accept': 'application/json', 'Content-Type': 'application/json'}
                if token:
                    headers['X-CSRF-TOKEN'] = token
                req = urllib.request.Request(base + '/api/v1' + path, data=json.dumps(payload).encode() if payload is not None else None, headers=headers)
                with opener.open(req, timeout=20) as response:
                    return json.load(response)
            token = api('/csrf')['token']
            email, password = credentials[role]
            api('/login', {'email': email, 'password': password}, token)
            api('/me/navigation')
            api('/calls')
        report.update(http_profiles_verified=5, frontend_assets_http=len(assets),
                      antivirus_acceptance='NOT_VERIFIED; intentionally unconfigured, no clean verdict fabricated',
                      browser_visual_acceptance='NOT_REPEATED; HTTP installation and React tests executed', result='PASS')
    except Exception as error:
        report.update(result='FAIL', error=str(error).replace(role_pw, '[REDACTED]').replace(admin_pw, '[REDACTED]'))
        raise
    finally:
        try:
            if server:
                if os.name == 'nt':
                    subprocess.run(['taskkill', '/PID', str(server.pid), '/T', '/F'], capture_output=True)
                else:
                    server.terminate()
                server.wait(timeout=20)
            if cluster_started:
                run('postgres-stop', [str(pg / ('pg_ctl' + suffix)), '-D', str(work / 'pgdata'), '-m', 'fast', '-w', 'stop'])
                report['isolated_cluster_stopped'] = True
        finally:
            if pwfile.exists():
                pwfile.unlink()
            args.report.parent.mkdir(parents=True, exist_ok=True)
            report['finished_at_utc'] = datetime.datetime.now(datetime.timezone.utc).isoformat()
            args.report.write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding='utf-8')
            print(json.dumps({k: v for k, v in report.items() if k not in ('work_directory', 'checks')}, ensure_ascii=False))


if __name__ == '__main__':
    main()
