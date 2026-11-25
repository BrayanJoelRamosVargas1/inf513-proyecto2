"""Windows local acceptance: same application source, cloned PostgreSQL and private test storage. Requires the registered local runtime and ClamAV test configuration; never deploys."""
from pathlib import Path
import os,json,subprocess,time,datetime,shutil,hashlib,socket
app=Path(__file__).resolve().parents[1];p=app/'.local/operacion-03';st=json.loads((p/'state.json').read_text(encoding='utf-8'));cfg=st['config'];runtime=Path(st['runtime']);lab=json.loads((app/'.local/clamav-lab/state.json').read_text(encoding='utf-8'))
stamp=datetime.datetime.now().strftime('%Y%m%d%H%M%S');work=app/'.local/academic-close'/stamp;work.mkdir(parents=True);out=app/'evidence/academic-business'/stamp;out.mkdir(exist_ok=True,parents=True);browserout=out/'navegador';browserout.mkdir(exist_ok=True,parents=True)
pg=Path('C:/Program Files/PostgreSQL/15/bin');conn=['-h','127.0.0.1','-p',str(cfg['port']),'-U',cfg['user']];env=dict(os.environ,PGPASSWORD=cfg['password']);db='ficct_applicants_'+stamp

def run(tool,args):
 r=subprocess.run([str(pg/(tool+'.exe')),*conn,*args],env=env,capture_output=True,text=True,encoding='utf-8');assert r.returncode==0,r.stderr;return r.stdout.strip()
def fingerprint(database):
 names=run('psql',['-d',database,'-tAc',"select tablename from pg_tables where schemaname='public' order by tablename"]).splitlines()
 return {name:run('psql',['-d',database,'-tAc','select md5(coalesce(jsonb_agg(to_jsonb(t) order by to_jsonb(t)::text)::text,\'[]\')) from "'+name+'" t']) for name in names}
def files(root):return {str(f.relative_to(root)):hashlib.sha256(f.read_bytes()).hexdigest() for f in root.rglob('*') if f.is_file()}
def rows(database,table):return json.loads(run('psql',['-d',database,'-tAc','select coalesce(json_agg(t order by id),\'[]\') from "'+table+'" t']))
before=fingerprint(st['database']);original_files=files(runtime/'storage/app/private');run('pg_dump',['-Fc','-f',str(work/'before.dump'),st['database']]);run('createdb',[db]);run('pg_restore',['--no-owner','--no-privileges','-d',db,str(work/'before.dump')])
shutil.copytree(runtime/'storage/app/private',work/'storage/app/private');
for path in ['framework/views','framework/cache/data','framework/sessions','logs']:(work/'storage'/path).mkdir(parents=True,exist_ok=True)
custom=app/'.local/clamav-lab/test-signatures';assert (custom/'ficct-test.ndb').exists();marker=b'FICCT_ANTIMALWARE_ACCEPTANCE_TEST_ONLY_2026';blocked=work/'firma-inocua.pdf';blocked.write_bytes((app/'resources/demo-documents/cpd.pdf').read_bytes()+b'\n'+marker+b'\n')
phpenv=dict(os.environ,DB_CONNECTION='pgsql',DB_HOST='127.0.0.1',DB_PORT=str(cfg['port']),DB_USERNAME=cfg['user'],DB_PASSWORD=cfg['password'],DB_DATABASE=db,APP_CONFIG_CACHE=str(work/'unused.php'),LARAVEL_STORAGE_PATH=str(work/'storage'),FICCT_CLAMSCAN_BINARY=lab['binary'],FICCT_CLAMSCAN_DATABASE=str(custom),APP_ENV='local',APP_DEBUG='false',APP_URL='http://127.0.0.1:8119',SESSION_COOKIE='ficct_applicants_'+stamp)
r=subprocess.run(['php','artisan','migrate','--force'],cwd=app,env=phpenv,capture_output=True,text=True,encoding='utf-8');assert r.returncode==0,r.stdout+r.stderr
preserved={t:rows(db,t) for t in ['documents','dossier_snapshots','grades','publications','institutional_scan_jobs','institutional_scan_results','institutional_scan_events']}
prefix='<?php require '+json.dumps((app/'vendor/autoload.php').as_posix())+';$app=require '+json.dumps((app/'bootstrap/app.php').as_posix())+';$app->useStoragePath('+json.dumps((work/'storage').as_posix())+');$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();'
fixture=work/'fixture.php';fixture.write_text(prefix+r"""
if(!str_starts_with(config('database.connections.pgsql.database'),'ficct_applicants_'))throw new RuntimeException('Isolated database required');
$credentials=[];$users=[];
foreach(['student'=>'student','reviewer'=>'staff','authority'=>'authority','admin'=>'admin','outsider'=>'student','evaluator'=>'staff'] as $key=>$role){$password=bin2hex(random_bytes(16));$u=new App\Models\User();$u->forceFill(['name'=>'Prueba sintética '.$key,'email'=>'fase2-'.$key.'-'.bin2hex(random_bytes(5)).'@example.test','password'=>$password,'role'=>$role,'faculty'=>'FICCT','career'=>'Sistemas']);$u->save();$users[$key]=$u;$credentials[$key]=['email'=>$u->email,'password'=>$password];}
$s=app(App\Domain\Selection::class);$c=$s->createCall($users['authority'],['title'=>'Seguridad documental sintética','opens_at'=>now()->subDay(),'closes_at'=>now()->addDays(2),'exam_at'=>now()->addDays(3),'scope'=>'UAGRM','source'=>'Fixture aislado, sin valor institucional']);
Illuminate\Support\Facades\DB::table('memberships')->insert(['call_id'=>$c,'user_id'=>$users['reviewer']->id,'role'=>'reviewer']);
Illuminate\Support\Facades\DB::table('memberships')->insert(['call_id'=>$c,'user_id'=>$users['evaluator']->id,'role'=>'evaluator']);
$category=Illuminate\Support\Facades\DB::table('categories')->insertGetId(['name'=>'Tipo sintético fase 2','regime'=>'docencia','active'=>true]);
$o=$s->saveOffer($users['authority'],$c,['category_id'=>$category,'title'=>'Software · Seguridad documental','seats'=>1,'is_subject'=>false,'modality'=>'Presencial','documents'=>['cpd'=>'Formulario CPD sintético'],'requirements'=>['activo'=>'Condición sintética'],'weights'=>['Software'=>'100'],'scheme'=>'DEMO_AREAS_100','source'=>'Fixture de aceptación técnica']);$s->transition($users['authority'],$c,'published');echo json_encode(['credentials'=>$credentials,'call'=>$c,'offer'=>$o]);
""",encoding='utf-8')
r=subprocess.run(['php',str(fixture)],cwd=app,env=phpenv,capture_output=True,text=True,encoding='utf-8');assert r.returncode==0,r.stderr;f=json.loads(r.stdout)
scan=work/'scan.php';scan.write_text(prefix+r"""
if(!str_starts_with(config('database.connections.pgsql.database'),'ficct_applicants_'))throw new RuntimeException('Isolated database required');
$p=app(App\Domain\DocumentProcessor::class);$task=$p->enqueue((int)$argv[1],'applicant');echo json_encode($p->runOne($task));
""",encoding='utf-8')
operator=work/'operator.php';operator.write_text(prefix+r"""
if(!str_starts_with(config('database.connections.pgsql.database'),'ficct_applicants_'))throw new RuntimeException('Isolated only');
use App\Domain\{Selection,DocumentProcessor};use App\Models\User;use Illuminate\Support\Facades\DB;
$id=(int)$argv[2];
if($argv[1]==='enqueue'){echo json_encode(['task'=>app(DocumentProcessor::class)->enqueue($id,'applicant')]);exit;}
$a=Selection::row('applications',$id);$o=Selection::row('offers',$a->offer_id);$c=Selection::row('calls',$o->call_id);
$authority=User::findOrFail(DB::table('memberships')->where('call_id',$c->id)->where('role','authority')->value('user_id'));$reviewer=User::findOrFail(DB::table('memberships')->where('call_id',$c->id)->where('role','reviewer')->value('user_id'));$evaluator=User::findOrFail(DB::table('memberships')->where('call_id',$c->id)->where('role','evaluator')->value('user_id'));
$s=app(Selection::class);$s->merit($reviewer,$id,'20','CPD sintético de prueba aislada');$s->assign($authority,$o->id,$evaluator->id,'Software');$s->transition($authority,$c->id,'closed');$s->transition($authority,$c->id,'evaluation');echo json_encode(['assignment'=>DB::table('assignments')->where('offer_id',$o->id)->value('id')]);
""",encoding='utf-8')
state={'base':'http://127.0.0.1:8119','out':str(browserout),'credentials':f['credentials'],'call':f['call'],'offer':f['offer'],'clean':str(app/'resources/demo-documents/cpd.pdf'),'blocked':str(blocked),'scan':str(scan),'operator':str(operator),'scan_env':phpenv,'chromium':os.environ.get('FICCT_CHROMIUM_EXECUTABLE',str(Path.home()/'AppData/Local/ms-playwright/chromium-1248/chrome-win64/chrome.exe')),'axe':str(app/'.local/validacion-tecnica-aislada/browser/node_modules/axe-core/axe.min.js')};(work/'state.json').write_text(json.dumps(state),encoding='utf-8')
router=work/'router.php';router.write_text('<?php $root='+json.dumps(app.as_posix())+';$uri=parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH);if($uri!=="/"&&is_file($root."/public".$uri))return false;require $root."/vendor/autoload.php";$app=require $root."/bootstrap/app.php";$app->useStoragePath('+json.dumps((work/'storage').as_posix())+');$app->handleRequest(Illuminate\\Http\\Request::capture());',encoding='utf-8')
probe=socket.socket();probe.bind(('127.0.0.1',8119));probe.close();server=None
try:
 with (work/'http.log').open('w',encoding='utf-8') as log:server=subprocess.Popen(['php','-S','127.0.0.1:8119',str(router)],cwd=app/'public',env=phpenv,stdout=log,stderr=log,creationflags=subprocess.CREATE_NO_WINDOW)
 time.sleep(.5)
 r=subprocess.run(['node',str(app/'scripts/academic_business.cjs'),str(work/'state.json')],cwd=app,env=dict(os.environ,FICCT_PLAYWRIGHT_MODULE=os.environ.get('FICCT_PLAYWRIGHT_MODULE',str(Path.home()/'.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright'))),capture_output=True,text=True,encoding='utf-8',timeout=600);(browserout/'ejecucion.txt').write_text(r.stdout+r.stderr,encoding='utf-8');assert r.returncode==0,r.stdout+r.stderr
 for t,old in preserved.items():
  current={row['id']:row for row in rows(db,t)};assert all(current[row['id']]==row for row in old),t
 for name,digest in original_files.items():assert hashlib.sha256((work/'storage/app/private'/name).read_bytes()).hexdigest()==digest
 sourcefp=fingerprint(db);restore=db+'_restore';run('pg_dump',['-Fc','-f',str(work/'completed.dump'),db]);run('createdb',[restore]);run('pg_restore',['--no-owner','--no-privileges','-d',restore,str(work/'completed.dump')]);shutil.copytree(work/'storage/app/private',work/'restored-private');assert fingerprint(restore)==sourcefp and files(work/'restored-private')==files(work/'storage/app/private')
 assert fingerprint(st['database'])==before and files(runtime/'storage/app/private')==original_files
 triggers=int(run('psql',['-d',restore,'-tAc',"select count(*) from pg_trigger where not tgisinternal"]))
 report={'resultado':'PASS','tablas_restauradas_identicas':len(sourcefp),'triggers_restaurados':triggers,'archivos_privados_restaurados':len(files(work/'restored-private')),'tablas_operativas_preservadas':len(before),'archivos_operativos_preservados':len(original_files),'registros_historicos_preservados':{t:len(v) for t,v in preserved.items()},'motor_real':'ClamAV con firmas reales y firma adicional inocua de prueba','limites':'Copia aislada; aceptación EICAR y producción institucional pendientes'}
 (out/'http-restauracion-preservacion.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(report,ensure_ascii=False))
finally:
 if server:server.terminate();server.wait(timeout=10)
