from pathlib import Path
import os,subprocess,json,time
root=Path(__file__).resolve().parents[1]
env=dict(os.environ,APP_ENV='testing',DB_DATABASE='ficct_test',DB_CONNECTION='pgsql',BCRYPT_ROUNDS='4')
scenario=root/'.local/governance-race.json';scenario.write_text('{}')
base=['php','scripts/governance_race.php',str(scenario)]
setup=subprocess.run(base+['setup'],cwd=root,env=env,capture_output=True,text=True)
assert setup.returncode==0 and setup.stdout.strip()=='READY',setup.stdout+setup.stderr
data=json.loads(scenario.read_text()); results={}
for label,modes in [('conflicting_corrections',[['correction','0'],['correction','1']]),('revoke_vs_grade',[['revoke'],['grade']]),('concurrent_renewals',[['grant'],['grant']])]:
    data['at']=time.time()+1;scenario.write_text(json.dumps(data))
    workers=[subprocess.Popen(base+m,cwd=root,env=env,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True) for m in modes]
    output=[w.communicate(timeout=30) for w in workers]
    results[label]=[{'exit':w.returncode,'stdout':out.strip(),'stderr':err.strip()} for w,(out,err) in zip(workers,output)]
    codes=sorted(w.returncode for w in workers)
    assert codes in ([[0,0],[0,2]] if label=='revoke_vs_grade' else [[0,2]]),results
    assert all(not err and out.strip() in ['ACCEPTED','REJECTED'] for out,err in output),results
check=subprocess.run(base+['verify'],cwd=root,env=env,capture_output=True,text=True,check=True)
counts=json.loads(check.stdout)
assert counts=={'approved':1,'grade_version':2,'revisions':1,'publication_count':1,'grade_was_before_revocation':True,'membership_version':3},counts
report={'result':'PASS','database':'ficct_test','independent_processes':True,'races':results,'invariants':counts}
(root/'evidence/governance-concurrency.json').write_text(json.dumps(report,indent=2),encoding='utf-8')
print(json.dumps(report,indent=2))
