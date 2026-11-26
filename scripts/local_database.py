"""Cluster de desarrollo aislado: no utiliza ni modifica servicios existentes."""
from pathlib import Path
import os, secrets, subprocess, json

root = Path(__file__).resolve().parents[1]
local = root / '.local'
local.mkdir(exist_ok=True)
pg = Path(os.environ.get('FICCT_PG_BIN', r'C:\Program Files\PostgreSQL\15\bin'))
port = '55439'
config_file = local / 'database.json'
if config_file.exists():
    config = json.loads(config_file.read_text())
else:
    config = {'port': port, 'user': 'ficct_demo', 'password': secrets.token_urlsafe(32)}
    config_file.write_text(json.dumps(config), encoding='utf-8')

def run(args, **kw):
    return subprocess.run([str(a) for a in args], check=True, creationflags=subprocess.CREATE_NO_WINDOW, **kw)

data = local / 'pgdata'
if not (data / 'PG_VERSION').exists():
    pw = local / 'initpw'
    pw.write_text(config['password'], encoding='utf-8')
    try:
        run([pg/'initdb.exe', '-D', data, '-U', config['user'], '--pwfile', pw, '-A', 'scram-sha-256', '--encoding=UTF8', '--locale=C'])
    finally:
        pw.unlink(missing_ok=True)
status = subprocess.run([str(pg/'pg_ctl.exe'), 'status', '-D', str(data)], capture_output=True, creationflags=subprocess.CREATE_NO_WINDOW)
if status.returncode != 0:
    run([pg/'pg_ctl.exe', 'start', '-w', '-D', data, '-l', local/'postgres.log', '-o', f'-p {port} -h 127.0.0.1'])
env = dict(os.environ, PGPASSWORD=config['password'])
for name in ['ficct_demo', 'ficct_test']:
    found = subprocess.run([str(pg/'psql.exe'), '-h','127.0.0.1','-p',port,'-U',config['user'],'-d','postgres','-tAc',f"SELECT 1 FROM pg_database WHERE datname='{name}'"],env=env,capture_output=True,text=True,creationflags=subprocess.CREATE_NO_WINDOW)
    if found.stdout.strip() != '1':
        run([pg/'createdb.exe','-h','127.0.0.1','-p',port,'-U',config['user'],name],env=env)
envpath=root/'.env'
text=envpath.read_text(encoding='utf-8') if envpath.exists() else (root/'.env.example').read_text(encoding='utf-8')
import re
if envpath.exists() and os.environ.get('FICCT_FORCE_ENV')!='1':
    current=dict(re.findall(r'^(DB_PORT|DB_DATABASE)=(.*)$',text,re.M))
    if current.get('DB_PORT','').strip() not in ('',port) or current.get('DB_DATABASE','').strip() not in ('','ficct_demo','laravel'):
        raise SystemExit('El .env existente apunta a otra base o puerto ('+str(current)+'). No se modifica: es posible que sea la instancia operativa. Defina FICCT_FORCE_ENV=1 solo si es intencional.')
values={'APP_NAME':'"Auxiliares FICCT"','APP_ENV':'local','APP_DEBUG':'false','APP_URL':'http://127.0.0.1:8099','DB_CONNECTION':'pgsql','DB_HOST':'127.0.0.1','DB_PORT':port,'DB_DATABASE':'ficct_demo','DB_USERNAME':config['user'],'DB_PASSWORD':config['password'],'SESSION_DRIVER':'file','CACHE_STORE':'file','QUEUE_CONNECTION':'sync'}
for key,value in values.items():
    text=re.sub(r'^#?\s*'+key+r'=.*$',key+'='+value,text,flags=re.M) if re.search(r'^#?\s*'+key+'=',text,re.M) else text+'\n'+key+'='+value
envpath.write_text(text+'\n',encoding='utf-8')
print('PostgreSQL local aislado listo en 127.0.0.1:'+port+'. Credenciales guardadas solo en .env y .local, excluidas de Git.')
