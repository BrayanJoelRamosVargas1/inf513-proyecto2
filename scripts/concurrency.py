from pathlib import Path
import os,subprocess,json,time
root=Path(__file__).resolve().parents[1]
env=dict(os.environ,APP_ENV='testing',DB_DATABASE='ficct_test',DB_CONNECTION='pgsql',BCRYPT_ROUNDS='4')
scenario=root/'.local/race.json';scenario.write_text('{}')
base=['php','scripts/concurrency_worker.php',str(scenario)]
setup=subprocess.run(base+['setup'],cwd=root,env=env,capture_output=True,text=True)
assert setup.returncode==0 and setup.stdout.strip()=='READY','No se pudo preparar el ensayo; verifique PostgreSQL con scripts/local_database.py. '+setup.stdout[:300]+setup.stderr[:300]
data=json.loads(scenario.read_text());results={}
for mode in ['apply','grade','approve']:
 data['at']=time.time()+2;scenario.write_text(json.dumps(data))
 args=[base+[mode,str(data['offers'][3+i])] if mode=='apply' else base+[mode] for i in range(2)]
 workers=[subprocess.Popen(a,cwd=root,env=env,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True) for a in args]
 out=[p.communicate(timeout=25) for p in workers]
 results[mode]=[{'exit':p.returncode,'stdout':a.strip(),'stderr':b.strip()} for p,(a,b) in zip(workers,out)]
 assert sorted(p.returncode for p in workers)==[0,2],results
check=subprocess.run(base+['verify'],cwd=root,env=env,capture_output=True,text=True,check=True)
counts=json.loads(check.stdout);assert counts=={'applications':4,'selected':1,'publications':1,'grades':1},counts
report={'result':'PASS','database':'PostgreSQL / ficct_test','two_independent_processes':True,'races':results,'counts':counts}
(root/'evidence/concurrency.json').write_text(json.dumps(report,indent=2),encoding='utf-8')
print(json.dumps(report,indent=2))
