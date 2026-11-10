"""Respalda y restaura en otra base; no modifica la base DEMO ni sus archivos."""
from pathlib import Path
import os,subprocess,json,hashlib,shutil,datetime,sys
root=Path(__file__).resolve().parents[1]
config=json.loads((root/'.local/database.json').read_text())
pg=Path(os.environ.get('FICCT_PG_BIN',r'C:\Program Files\PostgreSQL\15\bin'))
source_db=sys.argv[1] if len(sys.argv)>1 else 'ficct_demo'
assert source_db in ['ficct_demo','ficct_test']
stamp=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d%H%M%S')
dest=root/'.local/backups'/(source_db+'_'+stamp);dest.mkdir(parents=True)
env=dict(os.environ,PGPASSWORD=config['password'])
conn=['-h','127.0.0.1','-p',str(config['port']),'-U',config['user']]
def run(exe,args):
 r=subprocess.run([str(pg/(exe+'.exe'))]+args,env=env,capture_output=True,text=True,encoding='utf-8',errors='replace',creationflags=subprocess.CREATE_NO_WINDOW)
 if r.returncode:raise RuntimeError(r.stderr)
 return r.stdout.strip()
run('pg_dump',conn+['-Fc','-f',str(dest/'database.dump'),source_db])
db=('ficct_restore_test_' if source_db=='ficct_test' else 'ficct_restore_')+stamp
run('createdb',conn+[db]);run('pg_restore',conn+['--no-owner','--no-privileges','-d',db,str(dest/'database.dump')])
tables=['users','calls','offers','applications','documents','reviews','memberships','assignments','grades','tie_exams','publications','audit_events','correction_requests','score_versions','result_revisions','amendments','dossier_snapshots','participation_requests','participation_decisions','vacancy_events','award_choices']
before={};after={};digests={}
for table in tables:
 before[table]=int(run('psql',conn+['-d',source_db,'-tAc','SELECT count(*) FROM '+table]))
 after[table]=int(run('psql',conn+['-d',db,'-tAc','SELECT count(*) FROM '+table]))
 query="SELECT md5(COALESCE(json_agg(t ORDER BY id)::text,'[]')) FROM "+table+" t"
 old=run('psql',conn+['-d',source_db,'-tAc',query]);new=run('psql',conn+['-d',db,'-tAc',query])
 assert old==new,table+' difiere tras restaurar'
 digests[table]=True
assert before==after
triggers=int(run('psql',conn+['-d',db,'-tAc',"SELECT count(*) FROM pg_trigger WHERE tgname='immutable_record' AND NOT tgisinternal"]))
assert triggers==9,triggers
documents=json.loads(run('psql',conn+['-d',db,'-tAc',"SELECT COALESCE(json_agg(json_build_object('path',path,'sha256',sha256)), '[]'::json) FROM documents"]))
files=[]
for item in documents:
 source=root/'storage/app/private'/item['path'];target=dest/'files'/item['path'];target.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(source,target)
 assert hashlib.sha256(target.read_bytes()).hexdigest()==item['sha256'];files.append(item['sha256'])
report={'status':'PASS','database_source':source_db,'all_table_contents_match':digests,'immutable_triggers_restored':triggers,'database_restored':db,'counts_match':before,'private_files_restored_and_verified':len(files),'dump_sha256':hashlib.sha256((dest/'database.dump').read_bytes()).hexdigest(),'note':'Respaldo privado en .local/backups; no se distribuyen cuentas ni hashes de contraseña.'}
(root/('evidence/participation-restore.json' if source_db=='ficct_test' else 'evidence/restore.json')).write_text(json.dumps(report,indent=2),encoding='utf-8')
print(json.dumps(report,indent=2))
