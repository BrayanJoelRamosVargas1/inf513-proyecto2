from pathlib import Path
import os,subprocess,json,time
root=Path(__file__).resolve().parents[1]
env=dict(os.environ,APP_ENV='testing',DB_DATABASE='ficct_test',DB_CONNECTION='pgsql',BCRYPT_ROUNDS='4')
import atexit,shutil,tempfile
race_storage=Path(tempfile.mkdtemp(prefix='ficct-race-storage-'))  # F-08: archivos subidos fuera del storage real
for _p in ['app/private','framework/views','framework/cache/data','framework/sessions','logs']:(race_storage/_p).mkdir(parents=True,exist_ok=True)
env['LARAVEL_STORAGE_PATH']=str(race_storage);atexit.register(shutil.rmtree,race_storage,True)
work=Path(os.environ.get('FICCT_RACE_DIR',root/'.local'));work.mkdir(parents=True,exist_ok=True)
outdir=Path(os.environ.get('FICCT_EVIDENCE_DIR',root/'evidence'));outdir.mkdir(parents=True,exist_ok=True)
scenario=work/'race.json';scenario.write_text('{}',encoding='utf-8')
base=['php','scripts/concurrency_worker.php',str(scenario)]
setup=subprocess.run(base+['setup'],cwd=root,env=env,capture_output=True,text=True,encoding='utf-8')
assert setup.returncode==0 and setup.stdout.strip()=='READY','No se pudo preparar el ensayo; verifique PostgreSQL con scripts/local_database.py. '+setup.stdout[:300]+setup.stderr[:300]
data=json.loads(scenario.read_text(encoding='utf-8'));results={}
for mode in ['apply','grade','approve']:
 if mode=='approve':
  calculated=subprocess.run(base+['calculate'],cwd=root,env=env,capture_output=True,text=True,encoding='utf-8');assert calculated.returncode==0 and calculated.stdout.strip()=='ACCEPTED',calculated.stdout+calculated.stderr
 data['at']=time.time()+2;scenario.write_text(json.dumps(data),encoding='utf-8')
 args=[base+[mode,str(data['offers'][3+i])] if mode=='apply' else base+[mode] for i in range(2)]
 workers=[subprocess.Popen(a,cwd=root,env=env,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True,encoding='utf-8') for a in args]
 out=[p.communicate(timeout=25) for p in workers]
 results[mode]=[{'exit':p.returncode,'stdout':a.strip(),'stderr':b.strip()} for p,(a,b) in zip(workers,out)]
 assert sorted(p.returncode for p in workers)==[0,2],results
check=subprocess.run(base+['verify'],cwd=root,env=env,capture_output=True,text=True,encoding='utf-8',check=True)
counts=json.loads(check.stdout);assert counts=={'applications':4,'selected':1,'publications':1,'grades':1},counts
report={'result':'PASS','database':'PostgreSQL / ficct_test','two_independent_processes':True,'races':results,'counts':counts}
(outdir/'concurrency.json').write_text(json.dumps(report,indent=2),encoding='utf-8')
print(json.dumps(report,indent=2))
