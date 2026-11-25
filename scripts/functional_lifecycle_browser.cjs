// Continues the call created through React; HTTP requests below are read-only assertions.
const fs=require('fs'),path=require('path');
const {chromium}=require(process.env.FICCT_PLAYWRIGHT_MODULE||'playwright');
const statePath=process.argv[2],s=JSON.parse(fs.readFileSync(statePath,'utf8')),out=process.argv[3];
if(s.base!=='http://127.0.0.1:8119'||!s.scan_env.DB_DATABASE.startsWith('ficct_applicants_'))throw Error('Isolated database required');
const fixture=JSON.parse(fs.readFileSync(path.join(path.dirname(statePath),'functional-call-private.json'),'utf8'));
const checks=[],traffic=[],errors=[],assert=(ok,msg)=>{if(!ok)throw Error(msg);};
const section=(p,title)=>p.locator('section').filter({has:p.getByRole('heading',{name:title,exact:true})}).last();
async function send(p,container,button,endpoint,values={}){
 for(const [key,value] of Object.entries(values)){const f=container.locator(`[name="${key}"]`);if(await f.evaluate(e=>e.tagName==='SELECT'))await f.selectOption(String(value));else await f.fill(String(value));}
 const [r]=await Promise.all([p.waitForResponse(r=>r.url().endsWith(endpoint)&&r.request().method()==='POST'),container.getByRole('button',{name:button,exact:true}).click()]);assert(r.ok(),button+': '+r.status()+' '+(await r.text()).slice(0,400));
 await p.waitForFunction(()=>![...document.querySelectorAll('p[role=status]')].some(e=>e.textContent==='Cargando información…'));
 checks.push({action:button,endpoint,result:'FUNCIONA Y VERIFICADO'});
 fs.writeFileSync(path.join(out,'progress.json'),JSON.stringify({checks,traffic,errors},null,2));
}
(async()=>{
 fs.mkdirSync(out,{recursive:true});const browser=await chromium.launch({headless:true,executablePath:s.chromium}),pages=[];
 async function login(c){const context=await browser.newContext({locale:'es-BO',viewport:{width:1440,height:1000}}),p=await context.newPage();pages.push(p);p.on('pageerror',e=>errors.push(e.message));p.on('response',r=>{if(r.url().startsWith(s.base+'/api/'))traffic.push({path:new URL(r.url()).pathname,method:r.request().method(),status:r.status()});});await p.goto(s.base+'/p2');await p.getByLabel('Correo electrónico',{exact:true}).fill(c.email);await p.getByLabel('Contraseña',{exact:true}).fill(c.password);await p.getByRole('button',{name:'Entrar al sistema',exact:true}).click();await p.getByRole('navigation',{name:'Principal',exact:true}).waitFor();return p;}
 try{
  const authority=await login(fixture.actor.authority);await authority.goto(s.base+`/p2/convocatorias/${fixture.call}/configuracion`);
  const config=await (await authority.request.get(s.base+`/api/v1/calls/${fixture.call}/configuration`)).json();
  for(const offer of config.offers){
   const details=authority.locator('details.card').filter({has:authority.locator('summary').filter({hasText:offer.title})}).first();await details.locator('summary').first().click();
   for(const area of Object.keys(JSON.parse(offer.weights))){await send(authority,details.locator('section').filter({has:authority.getByRole('heading',{name:'Asignar evaluador',exact:true})}),'Guardar',`/api/v1/offers/${offer.id}/assignments`,{area,user_id:fixture.actor.evaluator.id});if(!await details.getAttribute('open'))await details.locator('summary').first().click();}
  }
  await authority.goto(s.base+`/p2/convocatorias/${fixture.call}`);await send(authority,section(authority,'Control del proceso'),'Pasar a Recepción publicada',`/api/v1/calls/${fixture.call}/transition`);
  const studentContext=await browser.newContext({locale:'es-BO',viewport:{width:1440,height:1000}}),student=await studentContext.newPage();pages.push(student);await student.goto(s.base+'/p2');await student.getByRole('button',{name:'Crear cuenta de postulante',exact:true}).click();
  const c=fixture.actor.student;for(const [name,value] of Object.entries({name:c.name,email:c.email,password:c.password,password_confirmation:c.password,faculty:'FICCT',career:'Sistemas'}))await student.locator(`[name="${name}"]`).fill(value);
  const registered=student.waitForResponse(r=>r.url().endsWith('/api/v1/register')&&r.request().method()==='POST');await student.getByRole('button',{name:'Crear cuenta de postulante',exact:true}).click();assert((await registered).ok(),'Registration rejected');await student.getByRole('navigation',{name:'Principal',exact:true}).waitFor();checks.push({action:'Registro publico y sesion conservada',result:'FUNCIONA Y VERIFICADO'});
  const applications=[];
  for(const offer of config.offers){
   await student.goto(s.base+`/p2/convocatorias/${fixture.call}`);const card=student.locator('article').filter({has:student.getByRole('heading',{name:offer.title,exact:true})});const [applied]=await Promise.all([student.waitForResponse(r=>r.url().endsWith(`/offers/${offer.id}/applications`)&&r.request().method()==='POST'),card.getByRole('button',{name:'Iniciar postulación',exact:true}).click()]);const id=(await applied.json()).id;applications.push({id,offer:offer.id,title:offer.title});await student.waitForURL('**/postulaciones/'+id);
   await student.locator('input[type=file]').setInputFiles(s.clean);await send(student,section(student,'Expediente digital'),'Guardar archivo',`/api/v1/applications/${id}/archivo`);
   await send(student,section(student,'Expediente digital'),'Confirmar envío definitivo',`/api/v1/applications/${id}/enviar`);
   let allowed=false;
   for(let i=0;i<90;i++){const d=await (await student.request.get(s.base+'/api/v1/applications/'+id)).json();if(d.documents.length&&d.documents.every(x=>x.security.download_allowed)){allowed=true;break;}await new Promise(r=>setTimeout(r,1000));}
   assert(allowed,'Real worker did not validate uploaded document');await student.reload();
   const download=await student.request.get(s.base+`/api/v1/documents/${(await (await student.request.get(s.base+'/api/v1/applications/'+id)).json()).documents[0].id}/download`);assert(download.ok()&&Buffer.compare(await download.body(),fs.readFileSync(s.clean))===0,'Original PDF bytes mismatch');checks.push({action:'Postulacion, envio, analisis automatico real y descarga original',application:id,result:'FUNCIONA Y VERIFICADO'});
  }
  const reviewer=await login(fixture.actor.reviewer);
  for(const [index,a] of applications.entries()){
   await reviewer.goto(s.base+'/p2/postulaciones/'+a.id);const reviewForm=reviewer.getByRole('button',{name:'Guardar revisión por criterio',exact:true}).locator('..');
   await send(reviewer,reviewForm,'Guardar revisión por criterio',`/api/v1/applications/${a.id}/revision`,{'doc:cpd':'meets',activo:'meets',reason:'Documento abierto y criterios cotejados en ensayo sintetico.'});
   const eligibility=reviewer.getByRole('button',{name:'Confirmar habilitación',exact:true}).locator('..');await send(reviewer,eligibility,'Confirmar habilitación',`/api/v1/applications/${a.id}/habilitacion`,{enabled:'1',reason:'Todos los criterios cumplen; decision sintetica documentada.'});
   await send(reviewer,section(reviewer,'Registro de méritos CPD'),'Confirmar méritos',`/api/v1/applications/${a.id}/meritos`,{reference:'CPD-SINTETICO-UI',score:index===0?'20':'25'});
  }
  for(const button of ['Pasar a Recepción cerrada','Pasar a Evaluación']){await authority.goto(s.base+`/p2/convocatorias/${fixture.call}`);await send(authority,section(authority,'Control del proceso'),button,`/api/v1/calls/${fixture.call}/transition`);}
  const evaluator=await login(fixture.actor.evaluator),expected=['68.00','74.50'];
  for(const [index,a] of applications.entries()){
   await evaluator.goto(s.base+'/p2/postulaciones/'+a.id);const weights=JSON.parse(config.offers.find(o=>o.id===a.offer).weights);
   for(const area of Object.keys(weights))await send(evaluator,section(evaluator,'Mi evaluación: '+area),'Registrar nota',`/api/v1/applications/${a.id}/nota`,{score:index===0?'80':area==='Software'?'60':'90',intent:'confirm'});
   await authority.goto(s.base+'/p2/postulaciones/'+a.id);await send(authority,authority.locator('form').filter({has:authority.getByRole('button',{name:'Calcular resultado',exact:true})}),'Calcular resultado',`/api/v1/applications/${a.id}/calcular`);
   await authority.reload();const result=await (await authority.request.get(s.base+'/api/v1/applications/'+a.id)).json();assert(result.application.final===expected[index],'Independent expected final mismatch');await authority.getByText('Final /100: '+expected[index],{exact:true}).waitFor();
   await authority.goto(s.base+`/p2/ofertas/${a.offer}/resultados`);await send(authority,section(authority,'Aprobar selección DEMO'),'Aprobar publicación DEMO',`/api/v1/offers/${a.offer}/publications`,{minutes:'Acta sintetica de aprobacion del recorrido integral desde formularios.'});
  }
  await authority.goto(s.base+`/p2/convocatorias/${fixture.call}`);await send(authority,section(authority,'Control del proceso'),'Pasar a Resultados',`/api/v1/calls/${fixture.call}/transition`);
  await authority.getByRole('link',{name:'Consultar historial del proceso →',exact:true}).click();await authority.getByRole('heading',{name:'Historial del proceso',exact:true}).waitFor();assert(await authority.getByText(/grade.confirmed/).count()>0,'Confirmed grade absent from audit');
  await authority.screenshot({path:path.join(out,'auditoria-del-proceso.png'),fullPage:true});
  await student.goto(s.base+'/p2/adjudicaciones');await student.getByRole('heading',{name:'Mis adjudicaciones',exact:true}).waitFor();assert(await student.getByText(/Publicación v1/).count()===2,'Two independent published awards absent');
  const report=await authority.request.get(s.base+`/api/v1/calls/${fixture.call}/reports/pdf`);assert(report.ok()&&(await report.body()).subarray(0,5).toString()==='%PDF-','PDF export invalid');
  assert(!errors.length,'JavaScript errors');const receipt={result:'PASS',call:fixture.call,applications,expected_finals:expected,checks,traffic,errors,scope:'One synthetic candidate, two offers, three assignments, genuine worker. No direct database mutation or operator-triggered scan. Ties, rejection, correction and revocation remain separate scenarios.'};fs.writeFileSync(path.join(out,'lifecycle.json'),JSON.stringify(receipt,null,2));console.log(JSON.stringify({result:'PASS',checks:checks.length,call:fixture.call}));
 }catch(e){for(const [i,p] of pages.entries())await p.screenshot({path:path.join(out,`failure-${i}.png`),fullPage:true});fs.writeFileSync(path.join(out,'failure.json'),JSON.stringify({error:e.message,checks,traffic,errors},null,2));throw e;}
 finally{await browser.close();}
})().catch(e=>{console.error(e.message);process.exit(1)});
