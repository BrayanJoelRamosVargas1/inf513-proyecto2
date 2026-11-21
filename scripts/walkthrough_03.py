"""Recorrido HTTP con CSRF real y datos sintéticos en una base nueva.

No usa las cuentas, archivos ni decisiones de ficct_demo. Requiere PostgreSQL
local iniciado y dependencias PHP instaladas. --keep-server permite inspección
visual posterior; el PID y las credenciales se guardan únicamente en .local.
"""
from pathlib import Path
import argparse
import datetime as dt
import hashlib
import http.cookiejar
import json
import os
import re
import secrets
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

sys.dont_write_bytecode = True
from revalidate_03 import fingerprint_demo

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'evidence/validacion-local-03'
CHECKS = []
REQUESTS = []


def check(name, condition):
    CHECKS.append({'check': name, 'result': 'PASS' if condition else 'FAIL'})
    assert condition, name


class Client:
    def __init__(self, role, base):
        self.role, self.base, self.token = role, base, None
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def request(self, path, data=None, expected=200, file=None, csrf=True, retry_throttle=True):
        headers = {'Accept': 'application/json' if data is not None else 'text/html', 'Referer': self.base + '/'}
        if data is not None:
            data = dict(data)
            if csrf:
                data['_token'] = self.token
            if file:
                boundary = 'FICCT' + secrets.token_hex(12)
                body = b''
                for k, value in data.items():
                    body += f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{value}\r\n'.encode()
                body += f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="sintetico.pdf"\r\nContent-Type: application/pdf\r\n\r\n'.encode() + file + f'\r\n--{boundary}--\r\n'.encode()
                headers['Content-Type'] = 'multipart/form-data; boundary=' + boundary
            else:
                body = urllib.parse.urlencode(data, doseq=True).encode()
        else:
            body = None
        try:
            response = self.opener.open(urllib.request.Request(self.base + path, data=body, headers=headers), timeout=30)
        except urllib.error.HTTPError as error:
            response = error
        content = response.read()
        if response.code == 429 and path == '/login' and expected == 200 and retry_throttle:
            retry_after = int(response.headers.get('Retry-After', '60'))
            REQUESTS.append({'role': self.role, 'method': 'POST', 'path': path, 'status': 429, 'expected': 429, 'note': 'Throttle activo; se respeta Retry-After sin desactivar protección', 'retry_after_seconds': retry_after})
            assert 0 <= retry_after <= 60
            time.sleep(retry_after)
            return self.request(path, data, expected, file, csrf, retry_throttle=False)
        REQUESTS.append({'role': self.role, 'method': 'POST' if data is not None else 'GET', 'path': path, 'status': response.code, 'expected': expected})
        assert response.code == expected, f'{self.role} {path}: HTTP {response.code}, esperado {expected}: {content[:800]!r}'
        if expected == 200 and path != '/login':
            assert not response.geturl().endswith('/login'), f'{self.role} {path}: sesión invalidada; se redirigió a login'
        match = re.search(r'name="_token" value="([^"]+)"', content.decode('utf-8', errors='replace'))
        if match:
            self.token = match.group(1)
        return content.decode('utf-8', errors='replace') if not path.endswith(('/pdf', '/xlsx')) else content

    def login(self, password):
        self.request('/login')
        self.request('/login', {'email': self.role + '@recorrido.ficct.test', 'password': password})
        self.request('/')


def run(port, keep_server):
    with socket.socket() as probe:
        probe.bind(('127.0.0.1', port))
    stamp = dt.datetime.now(dt.timezone.utc).strftime('%Y%m%d%H%M%S') + '_' + secrets.token_hex(2)
    database = 'ficct_walkthrough_' + stamp
    assert re.fullmatch(r'ficct_walkthrough_[0-9]{14}_[a-f0-9]{4}', database)
    private = ROOT / '.local' / database
    private.mkdir(parents=True)
    for path in ['app/private', 'framework/sessions', 'framework/views', 'framework/cache/data', 'logs']:
        (private / 'storage' / path).mkdir(parents=True)
    OUT.mkdir(parents=True, exist_ok=True)
    config = json.loads((ROOT / '.local/database.json').read_text(encoding='utf-8'))
    before_demo = fingerprint_demo(config)
    (private / 'before-demo.json').write_text(json.dumps(before_demo), encoding='utf-8')
    before_evidence = {str(p.relative_to(ROOT)): hashlib.sha256(p.read_bytes()).hexdigest() for p in (ROOT / 'evidence').rglob('*') if p.is_file() and OUT not in p.parents}
    pg = Path(os.environ.get('FICCT_PG_BIN', r'C:\Program Files\PostgreSQL\15\bin'))
    pg_env = dict(os.environ, PGPASSWORD=config['password'])
    conn = ['-h', '127.0.0.1', '-p', str(config['port']), '-U', config['user']]
    subprocess.run([str(pg / 'createdb.exe'), *conn, database], env=pg_env, check=True, capture_output=True, creationflags=subprocess.CREATE_NO_WINDOW)
    password = secrets.token_urlsafe(24)
    base = f'http://127.0.0.1:{port}'
    env = dict(os.environ, APP_ENV='local', APP_DEBUG='false', APP_URL=base,
               DB_CONNECTION='pgsql', DB_HOST='127.0.0.1', DB_PORT=str(config['port']), DB_DATABASE=database,
               DB_USERNAME=config['user'], DB_PASSWORD=config['password'], BCRYPT_ROUNDS='4',
               SESSION_DRIVER='file', CACHE_STORE='file', LARAVEL_STORAGE_PATH=str(private / 'storage'),
               APP_CONFIG_CACHE=str(private / 'unused-config.php'), FICCT_WALK_PASSWORD=password)
    def sql(query):
        result = subprocess.run([str(pg / 'psql.exe'), *conn, '-d', database, '-v', 'ON_ERROR_STOP=1', '-tAc', query],
                                env=pg_env, capture_output=True, text=True, encoding='utf-8', check=True, creationflags=subprocess.CREATE_NO_WINDOW)
        return result.stdout.strip()
    def scalar(query):
        return json.loads(sql('SELECT to_json(q) FROM (' + query + ') q'))
    def php(args):
        result = subprocess.run(['php', *args], cwd=ROOT, env=env, capture_output=True, text=True, encoding='utf-8', errors='replace', creationflags=subprocess.CREATE_NO_WINDOW)
        with (private / 'setup.log').open('a', encoding='utf-8') as log:
            log.write(result.stdout + result.stderr)
        assert result.returncode == 0, 'Falló la preparación; consulte el registro privado setup.log'
    php(['artisan', 'migrate', '--force', '--no-ansi'])
    php(['-r', "require 'vendor/autoload.php'; $app=require 'bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); if(!str_starts_with(config('database.connections.pgsql.database'),'ficct_walkthrough_')) throw new RuntimeException('Base no aislada'); (new App\\Models\\User)->forceFill(['name'=>'Administrador sintético','email'=>'admin@recorrido.ficct.test','password'=>getenv('FICCT_WALK_PASSWORD'),'role'=>'admin','faculty'=>'FICCT','career'=>'Informática'])->save();"])
    logfile = (private / 'http.log').open('w', encoding='utf-8')
    # PHP's built-in HTTP server need not populate $_ENV/$_SERVER with inherited
    # storage variables. Set the path explicitly before bootstrapping providers.
    router = private / 'router.php'
    php_root = json.dumps(ROOT.as_posix())
    php_storage = json.dumps((private / 'storage').as_posix())
    router.write_text('<?php\n$root=' + php_root + ';\n$uri=parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH);\n'
                      'if($uri!=="/" && is_file($root."/public".$uri)) return false;\n'
                      'require $root."/vendor/autoload.php";\n$app=require $root."/bootstrap/app.php";\n'
                      '$app->useStoragePath(' + php_storage + ');\n$app->handleRequest(Illuminate\\Http\\Request::capture());\n', encoding='utf-8')
    server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', str(router)], cwd=ROOT / 'public', env=env, stdout=logfile, stderr=logfile, creationflags=subprocess.CREATE_NO_WINDOW)
    (private / 'session.json').write_text(json.dumps({'database': database, 'server_pid': server.pid, 'base_url': base, 'password': password, 'accounts': ['admin', 'autoridad', 'revisor', 'evaluador', 'postulante', 'suplente', 'retiro', 'externo']}), encoding='utf-8')
    success = False
    try:
        for attempt in range(40):
            try:
                urllib.request.urlopen(base + '/login', timeout=2)
                break
            except (urllib.error.URLError, TimeoutError):
                time.sleep(0.25)
        clients = {role: Client(role, base) for role in ['admin', 'autoridad', 'revisor', 'evaluador', 'postulante', 'suplente', 'retiro', 'externo']}
        admin = clients['admin']; admin.login(password)
        admin.request('/login', {'email': 'irrelevante@test.local', 'password': 'irrelevante'}, expected=419, csrf=False)
        for role, client in clients.items():
            if role == 'admin':
                continue
            admin.request('/usuarios', {'name': role.title() + ' sintético', 'email': role + '@recorrido.ficct.test', 'password': password, 'role': 'authority' if role == 'autoridad' else 'staff' if role in ['revisor', 'evaluador'] else 'student', 'faculty': 'FICCT', 'career': 'Informática'})
        check('Administrador crea cuentas sintéticas y login de los cinco perfiles con CSRF', int(sql('SELECT count(*) FROM users')) == 8)
        admin.request('/categorias', {'name': 'Tipo sintético de recorrido', 'regime': 'docencia', 'active': '1'})
        authority, reviewer, evaluator, student = [clients[r] for r in ['autoridad', 'revisor', 'evaluador', 'postulante']]
        authority.login(password)
        today = dt.datetime.now(dt.timezone.utc)
        authority.request('/convocatorias', {'title': 'Recorrido sintético DEMO 0.3', 'opens_at': (today-dt.timedelta(days=1)).isoformat(), 'closes_at': (today+dt.timedelta(days=1)).isoformat(), 'exam_at': (today+dt.timedelta(days=2)).isoformat(), 'scope': 'UAGRM', 'subject_limit': '4', 'source': 'Fixture técnico sintético; sin valor normativo'})
        call = int(sql('SELECT max(id) FROM calls'))
        category = int(sql('SELECT max(id) FROM categories'))
        for role, permission in [('revisor', 'reviewer'), ('evaluador', 'evaluator')]:
            user = int(sql("SELECT id FROM users WHERE email='" + role + "@recorrido.ficct.test'"))
            admin.request('/permisos', {'call_id': call, 'user_id': user, 'role': permission, 'reason': 'Permiso para recorrido sintético'})
        for role in ['revisor', 'evaluador', 'postulante', 'suplente', 'retiro', 'externo']:
            clients[role].login(password)
        for title in ['Oferta sintética A', 'Oferta sintética B']:
            authority.request(f'/convocatorias/{call}/ofertas', {'title': title, 'category_id': category, 'seats': '1', 'modality': 'Presencial', 'documents': 'cpd=CPD sintético\nci=Identidad sintética', 'requirements': 'activo=Declaración sintética', 'weights': 'Prueba=100', 'scheme': 'DEMO_AREAS_100', 'source': 'Esquema sintético de prueba; no matriz normativa'})
        offers = [int(x) for x in sql('SELECT id FROM offers ORDER BY id').splitlines()]
        teacher = int(sql("SELECT id FROM users WHERE email='evaluador@recorrido.ficct.test'"))
        for offer in offers:
            authority.request(f'/ofertas/{offer}/asignar', {'user_id': teacher, 'area': 'Prueba'})
        authority.request(f'/convocatorias/{call}/estado', {'status': 'published'})
        check('Autoridad configura y publica; administrador asigna ámbitos', sql(f'SELECT status FROM calls WHERE id={call}') == 'published')
        def apply(client, offer):
            client.request(f'/ofertas/{offer}/postular', {})
            return int(sql('SELECT max(id) FROM applications'))
        pdf = b'%PDF-1.4\n% Documento sintetico sin validez\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n'
        def dossier(client, app):
            for kind in ['cpd', 'ci']:
                client.request(f'/postulaciones/{app}/archivo', {'kind': kind}, file=pdf)
            client.request(f'/postulaciones/{app}/enviar', {})
        decisions = {'decisions[doc:cpd]': 'meets', 'decisions[doc:ci]': 'meets', 'decisions[activo]': 'meets', 'reason': 'Revisión completa de evidencia sintética'}
        main = apply(student, offers[0]); dossier(student, main)
        check('Primer expediente se almacena en la carpeta aislada', len(list((private / 'storage/app/private/expedientes').glob('*.pdf'))) == 2 and fingerprint_demo(config) == before_demo)
        original = sql(f'SELECT row_to_json(t) FROM dossier_snapshots t WHERE application_id={main} ORDER BY id LIMIT 1')
        original_docs = sql(f'SELECT json_agg(t ORDER BY id) FROM documents t WHERE application_id={main}')
        reviewer.request(f'/postulaciones/{main}/revision', dict(decisions, **{'decisions[doc:ci]': 'observed'}))
        reviewer.request(f'/postulaciones/{main}/subsanaciones', {'kinds[]': ['ci'], 'deadline': (today+dt.timedelta(hours=2)).isoformat(), 'reason': 'Identidad observada para prueba sintética', 'evidence': 'Referencia de observación exclusivamente sintética'})
        amendment = int(sql('SELECT max(id) FROM amendments'))
        evaluator.request(f'/postulaciones/{main}/procesos', expected=403)
        clients['externo'].request(f'/postulaciones/{main}', expected=403)
        clients['externo'].request(f'/subsanaciones/{amendment}/archivo', {'kind': 'ci'}, file=pdf, expected=403)
        student.request(f'/subsanaciones/{amendment}/archivo', {'kind': 'ci'}, file=pdf+b'% version dos\n')
        student.request(f'/subsanaciones/{amendment}/enviar', {})
        reviewer.request(f'/subsanaciones/{amendment}/revisar', decisions)
        state = scalar(f'SELECT dossier_version,eligibility FROM applications WHERE id={main}')
        check('Subsanación por propietario y nueva revisión por revisor habilitan v2', state == {'dossier_version': 2, 'eligibility': 'enabled'})
        owner_page = student.request(f'/postulaciones/{main}')
        check('VIS-01: propietario conserva revisión real sin aviso de restricción', 'Cumple' in owner_page and 'Detalle de revisión restringido' not in owner_page)
        check('Expediente y documentos originales se conservan byte a byte', original == sql(f'SELECT row_to_json(t) FROM dossier_snapshots t WHERE application_id={main} ORDER BY id LIMIT 1') and original_docs == sql(f'SELECT json_agg(t ORDER BY id) FROM documents t WHERE application_id={main} AND amendment_id IS NULL'))
        admin.request(f'/postulaciones/{main}', expected=403)
        check('Administrador, evaluador y extraño respetan los límites del expediente', True)
        withdrawn = apply(clients['retiro'], offers[0])
        clients['retiro'].request(f'/postulaciones/{withdrawn}/participacion', {'kind': 'withdrawal', 'reason': 'Retiro voluntario de una postulación sintética', 'evidence': 'Manifestación sintética registrada para recorrido'})
        withdrawal = int(sql('SELECT max(id) FROM participation_requests'))
        reviewer.request(f'/participacion/{withdrawal}/decision', {'approved': '1', 'reason': 'Intento de decisión fuera de atribución DEMO'}, expected=403)
        authority.request(f'/participacion/{withdrawal}/decision', {'approved': '1', 'reason': 'Decisión de retiro exclusivamente sintética'})
        check('Retiro requiere autoridad y conserva la postulación', sql(f'SELECT participation FROM applications WHERE id={withdrawn}') == 'withdrawn')
        second = apply(student, offers[1]); dossier(student, second)
        runner = apply(clients['suplente'], offers[0]); dossier(clients['suplente'], runner)
        for app in [second, runner]:
            reviewer.request(f'/postulaciones/{app}/revision', decisions)
            reviewer.request(f'/postulaciones/{app}/habilitacion', {'enabled': '1', 'reason': 'Habilitación sintética con criterios completos'})
        for app in [main, second, runner]:
            reviewer.request(f'/postulaciones/{app}/meritos', {'score': '20.00', 'reference': 'CPD-SINTETICO-SIN-VALIDEZ'})
        authority.request(f'/convocatorias/{call}/estado', {'status': 'closed'})
        authority.request(f'/convocatorias/{call}/estado', {'status': 'evaluation'})
        for app, offer, score in [(main, offers[0], '80.00'), (second, offers[1], '90.00'), (runner, offers[0], '60.00')]:
            assignment = int(sql(f'SELECT id FROM assignments WHERE offer_id={offer}'))
            student.request(f'/postulaciones/{app}/nota', {'assignment_id': assignment, 'score': score}, expected=403)
            evaluation_page = evaluator.request(f'/postulaciones/{app}')
            if app == second:
                check('VIS-01: evaluador ve detalle restringido sin criterios pendientes ficticios', 'Detalle de revisión restringido' in evaluation_page and '>Pendiente<' not in evaluation_page)
                evaluator.request('/documentos/' + sql(f'SELECT min(id) FROM documents WHERE application_id={app}'), expected=403)
                check('VIS-01: documentos privados continúan inaccesibles al evaluador', True)
            evaluator.request(f'/postulaciones/{app}/nota', {'assignment_id': assignment, 'score': score, 'intent': 'confirm'})
            authority.request(f'/postulaciones/{app}/calcular', {})
        check('Evaluador asignado confirma notas; postulante no puede calificarse', sql(f'SELECT final FROM applications WHERE id={main}') == '68.00')
        for offer in offers:
            authority.request(f'/ofertas/{offer}/resultados')
            authority.request(f'/ofertas/{offer}/aprobar', {'minutes': 'Acta sintética de aprobación para recorrido técnico; sin validez institucional.'})
        publications = sql('SELECT json_agg(t ORDER BY id) FROM publications t')
        page = student.request('/adjudicaciones')
        check('Múltiples adjudicaciones visibles sin exclusión automática', '2 adjudicaciones visibles' in page and 'Múltiples adjudicaciones detectadas' in page)
        for app, choice in [(main, 'accept'), (second, 'defer')]:
            offer = offers[0] if app == main else offers[1]
            publication = int(sql(f'SELECT id FROM publications WHERE offer_id={offer}'))
            student.request(f'/postulaciones/{app}/aceptacion', {'publication_id': publication, 'version': '0', 'choice': choice, 'reason': 'Manifestación sintética para esta demostración', 'evidence': 'Evidencia sintética sin aceptación institucional'})
        check('Manifestaciones DEMO conservadas sin alterar publicaciones', int(sql('SELECT count(*) FROM award_choices')) == 2 and publications == sql('SELECT json_agg(t ORDER BY id) FROM publications t'))
        old_grade = sql(f'SELECT json_agg(t ORDER BY id) FROM grades t WHERE application_id={main}')
        student.request(f'/postulaciones/{main}/participacion', {'kind': 'renunciation', 'reason': 'Renuncia sintética a plaza ya adjudicada', 'evidence': 'Referencia sintética para demostrar vacante retenida'})
        renunciation = int(sql('SELECT max(id) FROM participation_requests'))
        check('Solicitud por sí sola no cambia adjudicación ni participación', sql(f'SELECT participation FROM applications WHERE id={main}') == 'active')
        authority.request(f'/participacion/{renunciation}/decision', {'approved': '1', 'reason': 'Decisión sintética de renuncia documentada'})
        check('Renuncia retiene vacante y exige revisión sin reemplazo automático', scalar(f'SELECT held_vacancies,needs_publication_review FROM offers WHERE id={offers[0]}') == {'held_vacancies': 1, 'needs_publication_review': True} and sql(f'SELECT participation FROM applications WHERE id={main}') == 'resigned')
        authority.request(f'/ofertas/{offers[0]}/aprobar', {'minutes': 'Nueva aprobación sintética tras renuncia; la vacante permanece retenida sin reemplazo.'})
        latest = json.loads(sql(f"SELECT snapshot->'selected' FROM publications WHERE offer_id={offers[0]} ORDER BY id DESC LIMIT 1"))
        check('Nueva publicación no asigna la plaza al siguiente candidato', latest == [] and sql(f'SELECT selection FROM applications WHERE id={runner}') == 'not_selected')
        vacancy_page = authority.request(f'/ofertas/{offers[0]}/resultados')
        check('VIS-02: vacante retenida no se atribuye a insuficiencia y usa singular', '1 plaza sin cubrir' in vacancy_page and '1 plaza retenida por renuncia' in vacancy_page and 'insuficiencia de candidatos' not in vacancy_page and '1 plazas' not in vacancy_page)
        check('Notas y publicaciones anteriores sobreviven a la renuncia', old_grade == sql(f'SELECT json_agg(t ORDER BY id) FROM grades t WHERE application_id={main}') and publications == sql('SELECT json_agg(t ORDER BY id) FROM publications t WHERE version=1'))
        authority.request(f'/convocatorias/{call}/auditoria')
        pdf_report = authority.request(f'/convocatorias/{call}/reporte/pdf')
        xlsx_report = authority.request(f'/convocatorias/{call}/reporte/xlsx')
        check('Autoridad consulta auditoría y descarga PDF/XLSX', pdf_report.startswith(b'%PDF') and xlsx_report.startswith(b'PK'))
        check('DEMO y archivos privados existentes permanecen idénticos', before_demo == fingerprint_demo(config))
        check('Evidencias históricas permanecen idénticas', all(hashlib.sha256((ROOT / name).read_bytes()).hexdigest() == digest for name, digest in before_evidence.items()))
        report = {'result': 'PASS', 'executed_at_utc': dt.datetime.now(dt.timezone.utc).isoformat(), 'database': database,
                  'base_url': base, 'real_http_csrf_and_sessions': True, 'synthetic_data_only': True,
                  'storage_isolated': True, 'new_local_execution': True, 'roles': ['postulante', 'revisor', 'evaluador', 'autoridad', 'administrador'],
                  'checks': CHECKS, 'http_requests': REQUESTS, 'scope': 'HTTP actions and database invariants; browser visual review recorded separately; no institutional approval'}
        (OUT / 'recorrido-http.json').write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
        (ROOT / '.local/validacion-local-03/last-session.json').write_text(json.dumps({'private_dir': str(private), 'base_url': base, 'server_pid': server.pid}), encoding='utf-8')
        success = True
        print(json.dumps({'result': 'PASS', 'checks': len(CHECKS), 'http_requests': len(REQUESTS), 'base_url': base, 'database': database, 'server_kept': keep_server}, ensure_ascii=False))
    finally:
        if not success or not keep_server:
            server.terminate()
            server.wait(timeout=10)
        logfile.close()


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--port', type=int, default=8103)
    parser.add_argument('--keep-server', action='store_true')
    parser.add_argument('--output-dir', type=Path, default=OUT)
    args = parser.parse_args()
    OUT = args.output_dir.resolve()
    run(args.port, args.keep_server)
