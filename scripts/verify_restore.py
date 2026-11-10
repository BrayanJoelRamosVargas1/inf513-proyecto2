"""Respalda y restaura en otra base; no modifica la base DEMO ni sus archivos."""
from pathlib import Path
import os,subprocess,json,hashlib,shutil,datetime
root=Path(__file__).resolve().parents[1]
config=json.loads((root/'.local/database.json').read_text())
pg=Path(os.environ.get('FICCT_PG_BIN',r'C:\Program Files\PostgreSQL\15\bin'))
stamp=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d%H%M%S')
dest=root/'.local/backups'/stamp;dest.mkdir(parents=True)
env=dict(os.environ,PGPASSWORD=config['password'])
conn=['-h','127.0.0.1','-p',str(config['port']),'-U',config['user']]
def run(exe,args):
 r=subprocess.run([str(pg/(exe+'.exe'))]+args,env=env,capture_output=True,text=True,encoding='utf-8',errors='replace',creationflags=subprocess.CREATE_NO_WINDOW)
 if r.returncode:raise RuntimeError(r.stderr)
 return r.stdout.strip()
run('pg_dump',conn+['-Fc','-f',str(dest/'database.dump'),'ficct_demo'])
db='ficct_restore_'+stamp
run('createdb',conn+[db]);run('pg_restore',conn+['--no-owner','--no-privileges','-d',db,str(dest/'database.dump')])
sql="SELECT json_build_object('users',(SELECT count(*) FROM users),'calls',(SELECT count(*) FROM calls),'offers',(SELECT count(*) FROM offers),'applications',(SELECT count(*) FROM applications),'documents',(SELECT count(*) FROM documents),'grades',(SELECT count(*) FROM grades),'tie_exams',(SELECT count(*) FROM tie_exams),'publications',(SELECT count(*) FROM publications),'audit_events',(SELECT count(*) FROM audit_events))"
before=json.loads(run('psql',conn+['-d','ficct_demo','-tAc',sql]));after=json.loads(run('psql',conn+['-d',db,'-tAc',sql]));assert before==after
documents=json.loads(run('psql',conn+['-d',db,'-tAc',"SELECT COALESCE(json_agg(json_build_object('path',path,'sha256',sha256)), '[]'::json) FROM documents"]))
files=[]
for item in documents:
 source=root/'storage/app/private'/item['path'];target=dest/'files'/item['path'];target.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(source,target)
 assert hashlib.sha256(target.read_bytes()).hexdigest()==item['sha256'];files.append(item['sha256'])
report={'status':'PASS','database_source':'ficct_demo','database_restored':db,'counts_match':before,'private_files_restored_and_verified':len(files),'dump_sha256':hashlib.sha256((dest/'database.dump').read_bytes()).hexdigest(),'note':'Respaldo privado en .local/backups; no se distribuyen cuentas ni hashes de contraseña.'}
(root/'evidence/restore.json').write_text(json.dumps(report,indent=2),encoding='utf-8')
print(json.dumps(report,indent=2))
