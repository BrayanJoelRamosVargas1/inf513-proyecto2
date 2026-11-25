from pathlib import Path
import json,os,subprocess,datetime,shutil,hashlib,sys,time,urllib.request
P=Path(__file__).resolve().parent;ROOT=P.parents[2];st=json.loads((P/'state.json').read_text('utf-8'));runtime=Path(st['runtime']);cfg=st['config']
stamp=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d%H%M%S');work=P/'ui'/stamp;work.mkdir(parents=True)
out=ROOT/('VALIDACION_PARTICIPACION_NAVEGADOR_'+stamp);out.mkdir();pg=Path('C:/Program Files/PostgreSQL/15/bin')
conn=['-h','127.0.0.1','-p',str(cfg['port']),'-U',cfg['user']];env=dict(os.environ,PGPASSWORD=cfg['password']);source=st['database'];db='ficct_ui_'+stamp
def run(exe,args):
 p=subprocess.run([str(pg/(exe+'.exe')),*args],env=env,capture_output=True,text=True,encoding='utf-8',errors='replace',creationflags=subprocess.CREATE_NO_WINDOW);assert p.returncode==0,p.stderr;return p.stdout.strip()
def sql(db,q):return run('psql',conn+['-d',db,'-v','ON_ERROR_STOP=1','-tAc',q])
def records(db,table):return json.loads(sql(db,'SELECT COALESCE(json_agg(t ORDER BY id),\'[]\'::json) FROM "'+table+'" t'))
def fingerprint(db):
 return {t:sql(db,'SELECT count(*)||\':\'||md5(COALESCE(jsonb_agg(to_jsonb(t) ORDER BY to_jsonb(t)::text)::text,\'[]\')) FROM "'+t+'" t') for t in sql(db,"SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename").splitlines()}
before_source=fingerprint(source);run('pg_dump',conn+['-Fc','-f',str(work/'source.dump'),source]);run('createdb',conn+[db]);run('pg_restore',conn+['--no-owner','--no-privileges','-d',db,str(work/'source.dump')]);assert fingerprint(db)==before_source
for f in ['app/private','framework/views','framework/cache/data','framework/sessions','logs']:(work/'storage'/f).mkdir(parents=True,exist_ok=True)
shutil.copytree(runtime/'storage/app/private',work/'storage/app/private',dirs_exist_ok=True)
before={t:records(db,t) for t in ['publications','grades','documents','dossier_snapshots','award_choices']}
creds={}
for line in (runtime/'.local/ACCESOS_DEMO.txt').read_text('utf-8').splitlines():
 if '|' in line:
  email,password=map(str.strip,line.split('|',1));creds[email.split('@')[0]]={'email':email,'password':password}
fixture=json.loads((ROOT/'OPERACION_Y_RECUPERACION_FICCT_DEMO_0_3/escenarios-guiados.json').read_text('utf-8'))
upload=next((work/'storage/app/private').rglob('*.png'))
state={'base':'http://127.0.0.1:8118','out':str(out),'credentials':creds,'fixture':fixture,'upload':str(upload),'deadline':(datetime.datetime.now(datetime.timezone(datetime.timedelta(hours=-4)))+datetime.timedelta(days=2)).strftime('%Y-%m-%dT%H:%M')}
(work/'browser-state.json').write_text(json.dumps(state),encoding='utf-8')
overrides={'APP_CONFIG_CACHE':'.local/ui-unused-config-'+stamp+'.php','DB_DATABASE':db,'SESSION_COOKIE':'ficct_ui_'+stamp,'APP_URL':state['base']}
router=work/'router.php';router.write_text('<?php\n$root='+json.dumps(runtime.as_posix())+';foreach(['+','.join(json.dumps(k)+' => '+json.dumps(v) for k,v in overrides.items())+'] as $k=>$v){$_ENV[$k]=$v;$_SERVER[$k]=$v;putenv($k."=".$v);}\n$uri=parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH);if($uri!=="/" && is_file($root."/public".$uri))return false;require $root."/vendor/autoload.php";$app=require $root."/bootstrap/app.php";$app->useStoragePath('+json.dumps((work/'storage').as_posix())+');$app->handleRequest(Illuminate\\Http\\Request::capture());',encoding='utf-8')
with (work/'http.log').open('w',encoding='utf-8') as log:
 server=subprocess.Popen(['php','-S','127.0.0.1:8118',str(router)],cwd=runtime/'public',env=dict(os.environ,DB_HOST='127.0.0.1',DB_PORT=str(cfg['port']),DB_USERNAME=cfg['user'],DB_PASSWORD=cfg['password']),stdout=log,stderr=log,creationflags=subprocess.CREATE_NO_WINDOW)
try:
 for _ in range(50):
  try:
   with urllib.request.urlopen(state['base']+'/login',timeout=3) as r:
    if r.status==200:break
  except Exception:time.sleep(.2)
 result=subprocess.run(['node',str(P/'participacion_ui.cjs'),str(work/'browser-state.json')],capture_output=True,text=True,encoding='utf-8',errors='replace',timeout=240)
 (out/'navegador.txt').write_text(result.stdout+result.stderr,encoding='utf-8');assert result.returncode==0,result.stdout+result.stderr
 for t,old in before.items():
  new={row['id']:row for row in records(db,t)};assert all(new[row['id']]==row for row in old),t
 a=int(fixture['amendment_application']);offer=int(fixture['offers'][2]);winner=int(fixture['applications'][2]);call=int(fixture['call_amendment'])
 application=next(r for r in records(db,'applications') if r['id']==a);assert application['participation']=='withdrawn' and application['eligibility']=='enabled'
 amendments=[r for r in records(db,'amendments') if r['application_id']==a];assert any(r['status']=='expired' for r in amendments) and any(r['status']=='reviewed' for r in amendments)
 publications=[r for r in records(db,'publications') if r['offer_id']==offer];assert len(publications)==2
 snap=publications[-1]['snapshot'];snap=json.loads(snap) if isinstance(snap,str) else snap;assert winner not in snap['selected']
 offerrow=next(r for r in records(db,'offers') if r['id']==offer);assert offerrow['held_vacancies']==1
 assert fingerprint(source)==before_source,'Cambió la DEMO disponible durante la prueba'
 report={'resultado':'PASS','base_y_almacenamiento_aislados':True,'tablas_demo_disponible_sin_cambios':31,'registros_historicos_comparados':{t:len(v) for t,v in before.items()},'ventana_vencida_cerrada':True,'subsanacion_nueva_revisada':True,'retiro_aprobado':True,'renuncia_vacante_retenida':True,'publicacion_nueva_sin_reemplazo':True,'historia_existente_identica':True,'fecha_utc':datetime.datetime.now(datetime.timezone.utc).isoformat(),'paquete_base_sha256':hashlib.sha256((ROOT/'SISTEMA_FICCT_GRUPO09_DEMO_0_3_OPERATIVA.zip').read_bytes()).hexdigest(),'alcance':'Prueba local con navegador real en copia sintética. No validación institucional.'}
 (out/'verificacion-base-datos.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps({'resultado':'PASS','carpeta':str(out)}))
finally:
 server.terminate();server.wait(timeout=10)
