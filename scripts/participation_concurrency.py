from pathlib import Path
import os,subprocess,time,json
root=Path(__file__).resolve().parents[1]
env=dict(os.environ,APP_ENV='testing',DB_DATABASE='ficct_test',DB_CONNECTION='pgsql',BCRYPT_ROUNDS='4')
work=Path(os.environ.get('FICCT_RACE_DIR',root/'.local'));work.mkdir(parents=True,exist_ok=True)
outdir=Path(os.environ.get('FICCT_EVIDENCE_DIR',root/'evidence'));outdir.mkdir(parents=True,exist_ok=True)
scenario=work/'participation-race.json';scenario.write_text('{}',encoding='utf-8')
base=['php','scripts/participation_race.php',str(scenario)]
def run(mode):
    p=subprocess.run(base+[mode],cwd=root,env=env,capture_output=True,text=True,encoding='utf-8')
    assert p.returncode==0,p.stdout+p.stderr
    return p.stdout.strip()
assert run('setup')=='READY'
results={}
for label,modes in [('double_submission',[['submit'],['submit']]),('double_review',[['review'],['review']]),('double_renunciation',[['renounce'],['renounce']]),('conflicting_acceptance',[['choice','accept'],['choice','defer']]),('upload_vs_seal',[['upload'],['seal']])]:
    if label=='upload_vs_seal':assert run('prepare_upload')=='READY'
    data=json.loads(scenario.read_text(encoding='utf-8'));data['at']=time.time()+1;scenario.write_text(json.dumps(data),encoding='utf-8')
    workers=[subprocess.Popen(base+m,cwd=root,env=env,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True,encoding='utf-8') for m in modes]
    outputs=[p.communicate(timeout=40) for p in workers]
    results[label]=[{'exit':p.returncode,'stdout':out.strip(),'stderr':err.strip()} for p,(out,err) in zip(workers,outputs)]
    codes=sorted(p.returncode for p in workers)
    assert codes in ([[0,0],[0,2]] if label=='upload_vs_seal' else [[0,2]]),results
    assert all(out.strip() in ['ACCEPTED','REJECTED'] and not err for out,err in outputs),results
checks=json.loads(run('verify'))
assert checks=={'dossier_version':3,'sealed_document_is_latest_accepted':True,'renunciation_decisions':1,'vacancy_events':1,'held_vacancies':1,'old_publication_preserved':True,'no_replacement':True,'award_choice_versions':1},checks
report={'result':'PASS','database':'ficct_test','independent_processes':True,'races':results,'invariants':checks}
(outdir/'participation-concurrency.json').write_text(json.dumps(report,indent=2),encoding='utf-8')
print(json.dumps(report,indent=2))
