"""Instalación en carpeta/base nuevas. Conserva la DEMO y todas las copias previas."""
from pathlib import Path
import os, subprocess, json, datetime, shutil
root=Path(__file__).resolve().parents[1]
stamp=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d%H%M%S')
dest=root/'.local'/('clean03_'+stamp);dest.mkdir(parents=True)
assert dest.resolve().is_relative_to((root/'.local').resolve())
cfg=json.loads((root/'.local/database.json').read_text())
pg=Path(os.environ.get('FICCT_PG_BIN',r'C:\Program Files\PostgreSQL\15\bin'))
env=dict(os.environ,PGPASSWORD=cfg['password'])
db='ficct_clean03_'+stamp
subprocess.run([str(pg/'createdb.exe'),'-h','127.0.0.1','-p',str(cfg['port']),'-U',cfg['user'],db],env=env,check=True,capture_output=True)
files=subprocess.check_output(['git','ls-files','--cached','--others','--exclude-standard','-z'],cwd=root).decode('utf-8').split('\0')
for name in set(files):
    if not name or name.startswith('evidence/') or name=='MANIFIESTO_SHA256.json':continue
    source=root/name
    if source.is_file():
        target=dest/name;target.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(source,target)
settings={'APP_ENV':'local','APP_DEBUG':'false','APP_KEY':'','APP_URL':'http://127.0.0.1:8101','DB_CONNECTION':'pgsql','DB_HOST':'127.0.0.1','DB_PORT':str(cfg['port']),'DB_DATABASE':db,'DB_USERNAME':cfg['user'],'DB_PASSWORD':cfg['password'],'SESSION_DRIVER':'file','CACHE_STORE':'file'}
lines=[]
for line in (root/'.env.example').read_text(encoding='utf-8').splitlines():
    key=line.split('=',1)[0]
    if key in settings:line=key+'='+settings.pop(key)
    lines.append(line)
lines.extend(k+'='+v for k,v in settings.items())
(dest/'.env').write_text('\n'.join(lines)+'\n',encoding='utf-8')
for folder in ['storage/app/private','storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/logs','bootstrap/cache']:(dest/folder).mkdir(parents=True,exist_ok=True)
composer=Path(shutil.which('composer')).with_name('composer.phar')
assert composer.exists(),composer
commands=[['php',str(composer),'install','--no-interaction','--prefer-dist','--no-progress'],['php','artisan','key:generate','--force'],['php','artisan','migrate','--force'],['php','artisan','db:seed','--force'],['php','artisan','ficct:demo-recorrido'],['php','artisan','view:cache'],['php','artisan','route:list','--except-vendor']]
log=[]
for command in commands:
    run=subprocess.run(command,cwd=dest,capture_output=True,text=True,encoding='utf-8',errors='replace')
    log.append(run.stdout+run.stderr)
    (dest/'installation.log').write_text('\n'.join(log),encoding='utf-8')
    assert run.returncode==0,'Instalación falló: '+str(command[:3])+'. Revisar log privado.'
def sql(query):return subprocess.check_output([str(pg/'psql.exe'),'-h','127.0.0.1','-p',str(cfg['port']),'-U',cfg['user'],'-d',db,'-tAc',query],env=env).decode().strip()
assert sql('SELECT count(*) FROM publications')=='1'
assert sql("SELECT count(*) FROM information_schema.tables WHERE table_name IN ('amendments','dossier_snapshots','participation_requests','participation_decisions','vacancy_events','award_choices')")=='6'
assert sql("SELECT count(*) FROM information_schema.tables WHERE table_name IN ('score_versions','correction_requests','result_revisions','access_control_lock')")=='4'
assert sql("SELECT count(*) FROM pg_trigger WHERE tgname='immutable_record' AND NOT tgisinternal")=='9'
report={'result':'PASS','database':db,'new_directory':True,'new_vendor_install':True,'domain_migrations':4,'synthetic_journey_publications':1,'governance_tables':4,'participation_tables':6,'immutable_triggers':9,'commands_passed':len(commands),'scope':'Instalación independiente en este equipo; no prueba de máquina sin herramientas.'}
(root/'evidence/clean-install-03.json').write_text(json.dumps(report,indent=2),encoding='utf-8')
print(json.dumps(report,indent=2))
