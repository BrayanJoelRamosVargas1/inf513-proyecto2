// Preparation contains identities/configuration only. Every academic mutation below uses React.
const fs=require('fs'),path=require('path');
const {chromium}=require(process.env.FICCT_PLAYWRIGHT_MODULE||'playwright');
const statePath=process.argv[2],s=JSON.parse(fs.readFileSync(statePath,'utf8')),out=process.argv[3],phase=process.argv[4];
if(s.base!=='http://127.0.0.1:8119'||!s.scan_env.DB_DATABASE.startsWith('ficct_applicants_'))throw Error('Isolated acceptance copy required');
const privateFile=path.join(path.dirname(statePath),'competitive-private.json'),f=JSON.parse(fs.readFileSync(privateFile,'utf8'));
const checks=[],traffic=[],errors=[],sessions=new Map(),assert=(v,msg)=>{if(!v)throw Error(msg);};
const section=(p,title)=>p.locator('section').filter({has:p.getByRole('heading',{name:title,exact:true})}).last();
const save=()=>fs.writeFileSync(privateFile,JSON.stringify(f,null,2));
const record=(action,detail={})=>{checks.push({action,...detail,result:'FUNCIONA Y VERIFICADO'});fs.writeFileSync(path.join(out,'progress.json'),JSON.stringify({phase,checks,traffic,errors},null,2));};
const pause=ms=>new Promise(r=>setTimeout(r,ms));
async function read(p,route,expected=200){const r=await p.request.get(s.base+'/api/v1'+route);assert(r.status()===expected,'Read '+route+': '+r.status());return r.json();}
async function fill(box,values){for(const [key,value] of Object.entries(values)){const input=box.locator(`[name="${key}"]`);if(await input.evaluate(e=>e.tagName==='SELECT'))await input.selectOption(String(value));else await input.fill(String(value));}}
async function send(p,box,button,endpoint,values={},expected=200){
 await fill(box,values);
 const [r]=await Promise.all([p.waitForResponse(r=>r.url().endsWith('/api/v1'+endpoint)&&r.request().method()==='POST'),box.getByRole('button',{name:button,exact:true}).click()]);
 assert(r.status()===expected,button+' '+endpoint+': '+r.status()+' '+(await r.text()).slice(0,400));
 if(expected===200)await p.getByRole('button',{name:'Procesando…',exact:true}).waitFor({state:'hidden'});
 else await p.getByRole('alert').waitFor();
 return r;
}
(async()=>{
 fs.mkdirSync(out,{recursive:true});const browser=await chromium.launch({headless:true,executablePath:s.chromium});
 browser.on('disconnected',()=>{});
 async function login(key,origin=s.base){
  const sessionKey=key+'@'+origin;if(sessions.has(sessionKey))return sessions.get(sessionKey);
  const ctx=await browser.newContext({viewport:{width:1366,height:1000},locale:'es-BO'}),p=await ctx.newPage(),c=f.actors[key];
  p.setDefaultTimeout(25000);p.on('pageerror',e=>errors.push(e.message));p.on('response',r=>{const url=new URL(r.url());if([s.base,'http://127.0.0.1:8120'].includes(url.origin)&&url.pathname.startsWith('/api/'))traffic.push({origin:url.origin,path:url.pathname,method:r.request().method(),status:r.status()});});
  await p.goto(origin+'/p2');
  for(let attempt=0;attempt<3;attempt++){
   await p.getByLabel('Correo electrónico',{exact:true}).fill(c.email);await p.getByLabel('Contraseña',{exact:true}).fill(c.password);
   const [r]=await Promise.all([p.waitForResponse(r=>r.url().endsWith('/api/v1/login')&&r.request().method()==='POST'),p.getByRole('button',{name:'Entrar al sistema',exact:true}).click()]);
   if(r.status()===429){await pause((Number(r.headers()['retry-after'])||60)*1000+1000);continue;}
   assert(r.ok(),'Login rejected '+r.status());await p.getByRole('navigation',{name:'Principal',exact:true}).waitFor();sessions.set(sessionKey,p);return p;
  }
  throw Error('Login rate limit did not recover');
 }
 const offer=c=>f.offers[c.offer];
 const inspect=async(p,c)=>read(p,'/applications/'+c.application);
 async function transition(authority,call,next){await authority.goto(s.base+'/p2/convocatorias/'+call);const box=section(authority,'Control del proceso');const labels={published:'Recepción publicada',closed:'Recepción cerrada',evaluation:'Evaluación'};await send(authority,box,'Pasar a '+labels[next],`/calls/${call}/transition`);const persisted=await read(authority,'/calls/'+call);assert(persisted.call.status===next,'Stage not persisted');record('Transición desde formulario',{call,stage:next});}
 try{
  if(phase==='setup'){
   const p=await login('authority');for(const call of Object.values(f.calls)){const d=await read(p,'/calls/'+call);assert(d.call.status==='draft','Expected draft fixture');await transition(p,call,'published');}
   record('Configuración independiente lista',{actors:Object.keys(f.actors).length,categories:Object.keys(f.categories).length,offers:Object.keys(f.offers).length,preparation:f.preparation});
  }
  if(phase==='applications'){
   for(const student of Object.keys(f.actors).filter(k=>/^[A-L]$/.test(k))){
    const p=await login(student);
    for(const c of f.cases.filter(c=>c.student===student)){
     const o=offer(c);
     if(!c.application){
      await p.goto(s.base+'/p2/convocatorias/'+o.call);const d=await read(p,'/calls/'+o.call),existing=d.offers.find(v=>v.id===o.id)?.own_application;
      if(existing)c.application=existing;
      else{const card=p.locator('article').filter({has:p.getByRole('heading',{name:new RegExp('Competencia '+c.offer+' '+f.tag+'$')})});const [r]=await Promise.all([p.waitForResponse(r=>r.url().endsWith(`/offers/${o.id}/applications`)&&r.request().method()==='POST'),card.getByRole('button',{name:'Iniciar postulación',exact:true}).click()]);assert(r.ok(),'Apply rejected');c.application=(await r.json()).id;}
      save();
     }
     await p.goto(s.base+'/p2/postulaciones/'+c.application);await p.getByRole('heading',{name:'Expediente digital',exact:true}).waitFor();let d=await inspect(p,c);
     if(!d.documents.length){
      assert(!await p.getByRole('button',{name:'Confirmar envío definitivo',exact:true}).count(),'Incomplete dossier exposes submission');
      await p.locator('input[type=file]').setInputFiles(s.clean);await send(p,section(p,'Expediente digital'),'Guardar archivo',`/applications/${c.application}/archivo`,{kind:'cpd'});
     }
     d=await inspect(p,c);if(d.application.status==='draft'){await p.reload();await send(p,section(p,'Expediente digital'),'Confirmar envío definitivo',`/applications/${c.application}/enviar`);}
     let clean=false;for(let n=0;n<100;n++){d=await inspect(p,c);if(d.documents.length&&d.documents.every(v=>v.security.download_allowed)){clean=true;break;}await pause(1000);}
     assert(clean,'Genuine worker did not validate '+c.application);await p.reload();await p.getByRole('link',{name:/^Descargar original:/}).waitFor();
     record('Postulación y expediente enviados, ClamAV real y recarga',{student,offer:c.offer,application:c.application,category:o.category_id,documents:d.documents.map(v=>({id:v.id,kind:v.kind,version:v.version}))});
    }
   }
  }
  if(phase==='reviews'){
   const p=await login('reviewer');
   for(const c of f.cases){
    assert(c.application,'Applications phase missing');let d=await inspect(p,c);if(d.application.eligibility!=='pending'&& (c.disabled||d.application.merit!==null)){record('Revisión previamente persistida',{application:c.application});continue;}
    await p.goto(s.base+'/p2/postulaciones/'+c.application);await p.getByRole('heading',{name:'Revisión y habilitación',exact:true}).waitFor();
    const decision=Object.fromEntries(Object.keys(d.criteria).map(k=>[k,c.disabled&&k==='activo'?'fails':'meets']));decision.reason='Revision sintetica individual; evidencia de ensayo cotejada por criterio.';
    await send(p,p.getByRole('button',{name:'Guardar revisión por criterio',exact:true}).locator('..'),'Guardar revisión por criterio',`/applications/${c.application}/revision`,decision);
    await p.reload();await send(p,p.getByRole('button',{name:'Confirmar habilitación',exact:true}).locator('..'),'Confirmar habilitación',`/applications/${c.application}/habilitacion`,{enabled:c.disabled?'0':'1',reason:c.disabled?'Inhabilitacion sintetica motivada: criterio activo incumplido.':'Habilitacion sintetica: todos los criterios fueron cotejados.'});
    if(!c.disabled){await p.reload();const box=section(p,'Registro de méritos CPD');if(c===f.cases[0]){await box.locator('[name=score]').fill('40.01');assert(await box.locator('[name=score]').evaluate(e=>e.validity.rangeOverflow),'Client accepts merit >40');}
     await send(p,box,'Confirmar méritos',`/applications/${c.application}/meritos`,{score:c.merit,reference:'CPD-SINTETICO-'+c.student+'-'+c.offer});}
    d=await inspect(p,c);assert(d.application.eligibility===(c.disabled?'disabled':'enabled'),'Eligibility not persisted');assert(c.disabled||Number(d.application.merit)===Number(c.merit),'Merit not persisted');record('Revisión, decisión motivada y mérito independiente',{application:c.application,eligibility:d.application.eligibility,merit:d.application.merit});
   }
   const authority=await login('authority');for(const call of Object.values(f.calls)){const d=await read(authority,'/calls/'+call);if(d.call.status==='published')await transition(authority,call,'closed');if(d.call.status==='published'||d.call.status==='closed')await transition(authority,call,'evaluation');}
  }
  if(phase==='grades'){
   for(let n=0;n<5;n++){
    const p=await login('evaluator'+n);
    for(const c of f.cases.filter(c=>!c.disabled&&!c.pending))for(const [area,score] of Object.entries(c.scores))if(offer(c).assigned[area]===n){
     const d=await inspect(p,c);if(d.grades.some(g=>g.area===area&&g.status!=='draft'))continue;
     await p.goto(s.base+'/p2/postulaciones/'+c.application);await p.getByRole('heading',{name:'Mi evaluación: '+area,exact:true}).waitFor();await send(p,section(p,'Mi evaluación: '+area),'Registrar nota',`/applications/${c.application}/nota`,{score,intent:'confirm'});
     const persisted=await inspect(p,c);assert(persisted.grades.some(g=>g.area===area&&Number(g.score)===Number(score)&&g.status==='confirmed'),'Confirmed grade missing');record('Nota confirmada por evaluador asignado',{application:c.application,evaluator:n,area,score});
    }
   }
   const p=await login('authority');for(const c of f.cases.filter(c=>!c.disabled&&!c.pending)){
    const d=await inspect(p,c);if(d.application.final!==null)continue;await p.goto(s.base+'/p2/postulaciones/'+c.application);await p.getByRole('button',{name:'Calcular resultado',exact:true}).waitFor();await send(p,p.getByRole('button',{name:'Calcular resultado',exact:true}).locator('..'),'Calcular resultado',`/applications/${c.application}/calcular`);
    await p.reload();const persisted=await inspect(p,c);assert(persisted.application.final!==null,'Final missing');await p.getByText('Final /100: '+persisted.application.final,{exact:true}).waitFor();record('Resultado visible y persistido',{application:c.application,student:c.student,offer:c.offer,merit:persisted.application.merit,exam:persisted.application.exam,final:persisted.application.final});
   }
  }
  if(phase==='results'){
   const p=await login('authority');
   for(const [key,o] of Object.entries(f.offers)){
    await p.goto(s.base+'/p2/ofertas/'+o.id+'/resultados');await p.getByRole('heading',{name:/^Clasificación y selección/}).waitFor();let d=await read(p,`/offers/${o.id}/ranking`);
    if(key==='pendiente'){assert(!d.ranking&&d.warning,'Incomplete scores were silently ranked');await p.getByText(d.warning,{exact:true}).waitFor();record('Resultado incompleto bloquea clasificación y publicación',{offer:key,warning:d.warning});continue;}
    assert(d.ranking,'Ranking missing '+key);const ranks=d.ranking.rank.map(a=>({id:a.id,final:a.final}));
    if(['software','redes'].includes(key)){
     const participants=f.cases.filter(c=>c.offer===key&&(key==='software'?['B','C']:['F','G','J']).includes(c.student)).map(c=>c.application).sort((a,b)=>a-b);assert(JSON.stringify([...d.ranking.tied].sort((a,b)=>a-b))===JSON.stringify(participants),'Wrong cutoff tie participants');
     assert(!await p.getByRole('button',{name:'Aprobar publicación DEMO',exact:true}).count(),'Unresolved tie exposes publication');
     if(!d.ties.length){await send(p,section(p,'Convocar examen exclusivo de desempate'),'Guardar',`/offers/${o.id}/ties`,{commission:'Comision sintetica A, Comision sintetica B',scheduled_at:new Date(Date.now()-4*3600000-120000).toISOString().slice(0,19),conditions:'Examen sintetico exclusivo para los empatados por la ultima plaza; decision expresa de comision.'});await p.reload();d=await read(p,`/offers/${o.id}/ranking`);}
     const t=d.ties.at(-1);if(t.status==='scheduled'){
      const values={winners:String(participants[0]),minutes:'Acta sintetica: la comision registra el nuevo examen y selecciona expresamente un aspirante para la plaza en disputa.'};for(const [i,id] of participants.entries())values['result-'+id]='Resultado de ensayo '+(95-i*5)+'; evaluacion documentada por comision.';
      await send(p,section(p,'Registrar decisión de comisión'),'Guardar',`/ties/${t.id}/resolution`,values);await p.reload();
     }
     record('Empate por último cupo, examen exclusivo y acta explícita',{offer:key,participants,winner:participants[0],ranks});
    }else assert(!d.ranking.tied.length,'Tie outside cutoff blocks selection '+key);
    d=await read(p,`/offers/${o.id}/ranking`);if(!d.publications.length){await send(p,section(p,'Aprobar selección DEMO'),'Aprobar publicación DEMO',`/offers/${o.id}/publications`,{minutes:'Acta sintetica de aprobacion: cupos, condiciones y decisiones cotejados en prueba aislada.'});await p.reload();}
    d=await read(p,`/offers/${o.id}/ranking`);const selected=d.ranking.rank.filter(a=>a.selection==='selected');assert(selected.length<=o.seats,'Seats exceeded');assert(d.publications.length===1,'Publication missing/duplicated');
    if(key==='electronica')assert(selected.length===3&&d.ranking.vacancies===1&&selected.some(v=>v.final==='0.00'),'Zero/fewer candidates scenario incorrect');
    if(key==='exacto')assert(selected.length===2&&d.ranking.vacancies===0,'Exact seats scenario incorrect');
    if(key==='sin_habilitados')assert(!selected.length&&d.ranking.vacancies===1,'Ineligible candidate selected');
    if(key==='empate_fuera')assert(selected.length===1&&d.ranking.tied.length===0,'Outside tie changed winner');
    await p.screenshot({path:path.join(out,'ranking-'+key+'.png'),fullPage:true});record('Publicación respeta cupos y recarga',{offer:key,seats:o.seats,selected:selected.map(a=>a.id),vacancies:d.ranking.vacancies,ranks:d.ranking.rank.map(a=>({id:a.id,final:a.final,selection:a.selection}))});
   }
  }
  if(phase==='rectification'){
   const c=f.cases.find(c=>c.offer==='software'&&c.student==='D'),o=offer(c),reviewer=await login('reviewer'),authority=await login('authority');
   const before=await read(authority,`/offers/${o.id}/ranking`);assert(before.publications.length===1,'Requires initial competitive publication');
   await reviewer.goto(s.base+`/p2/postulaciones/${c.application}/rectificaciones`);await reviewer.getByRole('heading',{name:'Rectificaciones y versiones',exact:true}).waitFor();
   let pending=await read(authority,`/applications/${c.application}/corrections`),request=pending.requests.find(r=>r.status==='pending');
   if(!request){const form=section(reviewer,'Solicitar rectificación').locator('form');await send(reviewer,form,'Guardar',`/applications/${c.application}/corrections`,{grade_id:'',score:'40',reason:'Rectificacion sintetica del respaldo CPD: valor certificado de ensayo corregido.',evidence:'CPD-SINTETICO-RECTIFICADO-D-SOFTWARE'},201);pending=await read(authority,`/applications/${c.application}/corrections`);request=pending.requests.find(r=>r.status==='pending');}
   assert(request&&request.requester_id===f.actors.reviewer.id&&request.old_value==='27.80'&&request.new_value==='40.00','Unexpected correction; never overwrite an existing request');
   let d=await inspect(authority,c);assert(d.application.final==='75.80','Request altered grade before approval');
   await authority.goto(s.base+`/p2/postulaciones/${c.application}/rectificaciones`);await send(authority,section(authority,'Decidir solicitud'),'Guardar',`/corrections/${request.id}/decision`,{approved:'1',reason:'Otra cuenta autorizada coteja evidencia sintetica y aprueba rectificacion.'});await authority.reload();
   await authority.getByText(/27\.80 → 40\.00/).waitFor();d=await inspect(authority,c);assert(d.application.merit==='40.00'&&d.application.final==='88.00','Correction did not recalculate final');
   let ranking=await read(authority,`/offers/${o.id}/ranking`);assert(JSON.stringify(before.publications)===JSON.stringify(ranking.publications),'Correction replaced historical publication');assert(ranking.offer.needs_publication_review,'Publication review not required');assert(ranking.ranking.rank[1].id===c.application,'Authorized correction did not change rank');
   for(const other of f.cases.filter(v=>v.student==='D'&&v!==c)){const untouched=await inspect(authority,other);assert(untouched.application.final==='90.00','Other application changed through correction');}
   record('Rectificación autorizada, recálculo y cambio de clasificación sin reemplazo histórico',{application:c.application,old_merit:'27.80',new_merit:'40.00',old_final:'75.80',new_final:'88.00',previous_publications:ranking.publications.length});
   await authority.screenshot({path:path.join(out,'rectificacion-aprobada-con-historial.png'),fullPage:true});
   await authority.goto(s.base+`/p2/ofertas/${o.id}/resultados`);await send(authority,section(authority,'Aprobar selección DEMO'),'Aprobar publicación DEMO',`/offers/${o.id}/publications`,{minutes:'Nueva aprobacion sintetica tras rectificacion: se conserva la publicacion anterior y se confirma el cambio de ranking.'});await authority.reload();ranking=await read(authority,`/offers/${o.id}/ranking`);assert(ranking.publications.length===2,'Republishing did not create new version');assert(ranking.publications.some(p=>JSON.stringify(p)===JSON.stringify(before.publications[0])),'Old publication missing');assert(ranking.ranking.rank.filter(v=>v.selection==='selected').some(v=>v.id===c.application),'New selection not approved');
   record('Nueva publicación aprobada conserva versión anterior',{offer:'software',versions:ranking.publications.length,selected:ranking.ranking.rank.filter(v=>v.selection==='selected').map(v=>v.id)});
   await authority.goto(s.base+`/p2/convocatorias/${o.call}/auditoria`);await authority.getByRole('heading',{name:'Historial del proceso',exact:true}).waitFor();const row=authority.locator('tbody tr').filter({hasText:'Rectificación aprobada'}).first();await row.locator('summary').click();await row.getByText(/Otra cuenta autorizada/).waitFor();await authority.screenshot({path:path.join(out,'auditoria-de-rectificacion.png'),fullPage:true});
   for(const call of Object.values(f.calls)){
    await authority.goto(s.base+'/p2/convocatorias/'+call);for(const [label,extension] of [['Descargar consolidado PDF','pdf'],['Descargar consolidado Excel','xlsx']]){const [download]=await Promise.all([authority.waitForEvent('download'),authority.getByRole('link',{name:label,exact:true}).click()]);await download.saveAs(path.join(out,`competencia-${call}.${extension}`));assert(!await download.failure(),'Report download failed');}
   }
   record('Reportes de ambas convocatorias descargados tras rectificación');
  }
  if(phase==='concurrency'){
   const c=f.cases.find(c=>c.offer==='pendiente'&&c.student==='F'),o=offer(c),evaluator=await login('evaluator2');
   await evaluator.goto(s.base+'/p2/postulaciones/'+c.application);await send(evaluator,section(evaluator,'Mi evaluación: Redes'),'Registrar nota',`/applications/${c.application}/nota`,{score:'0',intent:'confirm'});
   const authority=await login('authority');await authority.goto(s.base+'/p2/postulaciones/'+c.application);await send(authority,authority.getByRole('button',{name:'Calcular resultado',exact:true}).locator('..'),'Calcular resultado',`/applications/${c.application}/calcular`);const calculated=await inspect(authority,c);assert(calculated.application.final==='20.00','Missing grade was not recovered independently from zero');
   const second=await login('authority','http://127.0.0.1:8120');
   await authority.goto(s.base+`/p2/ofertas/${o.id}/resultados`);await second.goto(`http://127.0.0.1:8120/p2/ofertas/${o.id}/resultados`);
   const boxes=[section(authority,'Aprobar selección DEMO'),section(second,'Aprobar selección DEMO')];for(const box of boxes)await fill(box,{minutes:'Acta sintetica de aprobacion concurrente desde dos servidores PHP independientes.'});
   const responses=[authority.waitForResponse(r=>r.url().endsWith(`/offers/${o.id}/publications`)&&r.request().method()==='POST'),second.waitForResponse(r=>r.url().endsWith(`/offers/${o.id}/publications`)&&r.request().method()==='POST')];
   await Promise.all(boxes.map(box=>box.getByRole('button',{name:'Aprobar publicación DEMO',exact:true}).click()));const result=await Promise.all(responses);const statuses=result.map(r=>r.status()).sort();assert(JSON.stringify(statuses)==='[200,422]','Concurrent publication accepted twice: '+statuses);
   await authority.reload();const ranking=await read(authority,`/offers/${o.id}/ranking`);assert(ranking.publications.length===1&&ranking.ranking.rank.filter(v=>v.selection==='selected').length===1,'Concurrent selection duplicated publication/seats');record('Dos servidores PHP publican simultáneamente: una aprobación y un rechazo',{servers:[8119,8120],statuses,publications:ranking.publications.length,selected:ranking.ranking.rank.filter(v=>v.selection==='selected').map(v=>v.id)});
   for(const call of Object.values(f.calls)){await authority.goto(s.base+'/p2/convocatorias/'+call);await send(authority,section(authority,'Control del proceso'),'Pasar a Resultados',`/calls/${call}/transition`);const persisted=await read(authority,'/calls/'+call);assert(persisted.call.status==='results','Complete call did not reach results');record('Convocatoria competitiva completa llega a Resultados',{call});}
  }
  if(phase==='readback'){
   const authority=await login('authority');
   for(const [key,o] of Object.entries(f.offers)){
    await authority.goto(s.base+`/p2/ofertas/${o.id}/resultados`);await authority.getByRole('heading',{name:/^Clasificación y selección/}).waitFor();const d=await read(authority,`/offers/${o.id}/ranking`);assert(d.call.status==='results'&&d.ranking,'Incomplete final call');const current=section(authority,'Resultados de la oferta');assert(await current.locator('tbody tr').count()===d.ranking.rank.length,'Visible candidate count differs');
    for(const a of d.ranking.rank){const row=current.locator('tbody tr').filter({has:authority.getByRole('link',{name:a.name,exact:true})});assert(await row.locator('td').nth(2).innerText()===a.merit,'Visible merit differs');assert(await row.locator('td').nth(3).innerText()===a.exam,'Visible exam differs');assert(await row.locator('td').nth(4).innerText()===a.final,'Visible final differs');assert(await row.locator('td').nth(5).innerText()===(a.selection==='selected'?'Seleccionado DEMO':'Fuera de cupo'),'Visible selection differs');}
    if(key==='software'){
     for(const [version,student,score] of [[1,'B','87.25'],[2,'D','88.00']]){const publication=d.publications.find(p=>p.version===version);await authority.getByText(`Publicación v${version} · Revisión ${publication.result_revision}`,{exact:true}).click();const region=authority.getByRole('region',{name:'Seleccionados históricos de publicación v'+version,exact:true});await region.getByText(f.actors[student].name,{exact:true}).waitFor();await region.getByRole('cell',{name:score,exact:true}).waitFor();}
     const revision=section(authority,'Impacto de revisión '+d.offer.result_revision);await revision.getByText('75.80',{exact:true}).waitFor();await revision.getByText('88.00',{exact:true}).waitFor();assert(d.oldTies.length===1,'Old tie missing from authorized API');const tie=d.oldTies[0];await authority.getByText(`Examen #${tie.id} · Revisión ${tie.result_revision} · Ronda ${tie.round}`,{exact:true}).click();await authority.getByText(tie.minutes,{exact:true}).waitFor();
     await authority.context().route(s.base+'/audit-axe.js',r=>r.fulfill({path:s.axe,contentType:'application/javascript'}));await authority.addScriptTag({url:s.base+'/audit-axe.js'});const violations=await authority.evaluate(async()=>(await axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21a','wcag21aa']}})).violations.map(v=>v.id));assert(!violations.length,'History accessibility: '+violations.join(','));record('Historia utilizable: seleccionados anteriores/nuevos, impacto y acta del desempate anterior',{offer:key,old_selected:'B',new_selected:'D',old_final:'75.80',new_final:'88.00',axe_violations:violations});
     const original=f.appearance_original??(await read(authority,'/me/preferences')).preferences;if(!f.appearance_original){f.appearance_original=original;save();}
     async function appearance(values){let details=authority.locator('details.preferences');if(!await details.evaluate(el=>el.open))await details.locator('summary').click();await fill(details,values);const [r]=await Promise.all([authority.waitForResponse(r=>r.url().endsWith('/api/v1/me/preferences')&&r.request().method()==='PUT'),details.getByRole('button',{name:'Aplicar preferencias',exact:true}).click()]);assert(r.ok(),'Preferences rejected');await authority.waitForFunction(v=>document.documentElement.dataset.theme===v.theme&&document.documentElement.dataset.font===String(v.font)&&document.documentElement.dataset.contrast===(v.contrast==='1'?'alto':'normal')&&(v.mode==='auto'||document.documentElement.dataset.mode===v.mode),values);}
     await authority.setViewportSize({width:320,height:1000});const combinations=[];
     for(const theme of ['ninos','jovenes','adultos'])for(const mode of ['dia','noche'])for(const contrast of ['0','1']){const values={theme,mode,font:'200',contrast};await appearance(values);await authority.evaluate(async()=>{await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));await Promise.all(document.getAnimations().map(a=>a.finished.catch(()=>{})));});assert(await authority.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'History overflow '+JSON.stringify(values));const issues=await authority.evaluate(async()=>(await axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21a','wcag21aa']}})).violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>({target:n.target,summary:n.failureSummary,html:n.html}))})));if(issues.length)fs.writeFileSync(path.join(out,'history-accessibility-failure.json'),JSON.stringify({values,issues},null,2));assert(!issues.length,'History accessibility '+JSON.stringify(values)+': '+issues.map(v=>v.id).join(','));combinations.push(values);}
     await appearance({theme:original.theme,mode:original.mode,font:String(original.font),contrast:original.contrast?'1':'0'});const preferences=authority.locator('details.preferences');if(await preferences.evaluate(el=>el.open))await preferences.locator('summary').click();await authority.setViewportSize({width:1366,height:1000});record('Historia a 320px y fuente 200% con preferencias reales',{combinations,appearance_values_restored:true});
    }
    await authority.getByRole('navigation',{name:'Principal',exact:true}).getByRole('link',{name:'Convocatorias',exact:true}).waitFor();await authority.getByText(/^Visitas de esta vista: [0-9]+$/).waitFor();await authority.evaluate(()=>window.scrollTo(0,0));await authority.screenshot({path:path.join(out,'final-'+key+'.png'),fullPage:true});
    if(key==='software'){await authority.setViewportSize({width:390,height:844});assert(await authority.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Ranking overflows');await authority.screenshot({path:path.join(out,'final-software-movil.png'),fullPage:true});await authority.setViewportSize({width:1366,height:1000});}
    record('Clasificación final recargada y contrastada celda por celda',{offer:key,rows:d.ranking.rank.length,publication_versions:d.publications.length,seats:o.seats});
   }
   for(const call of Object.values(f.calls)){
    await authority.goto(s.base+`/p2/convocatorias/${call}/auditoria`);await authority.getByRole('heading',{name:'Historial del proceso',exact:true}).waitFor();const first=await read(authority,`/calls/${call}/audit?page=1`);
    if(first.events.last_page>1){await authority.getByRole('button',{name:'Página siguiente',exact:true}).click();await authority.getByText(/^Página 2 de/).waitFor();const second=await read(authority,`/calls/${call}/audit?page=2`);await authority.waitForFunction(id=>document.querySelector('tbody tr td')?.textContent.startsWith('#'+id),second.events.data[0].id);assert(!second.events.data.some(x=>first.events.data.some(y=>y.id===x.id)),'Audit pages overlap');await authority.getByRole('button',{name:'Página anterior',exact:true}).click();await authority.getByText(/^Página 1 de/).waitFor();await authority.waitForFunction(id=>document.querySelector('tbody tr td')?.textContent.startsWith('#'+id),first.events.data[0].id);record('Paginación real de auditoría conserva convocatoria y eventos',{call,total:first.events.total,pages:first.events.last_page});}
    await authority.goto(s.base+'/p2/convocatorias/'+call);for(const [label,extension] of [['Descargar consolidado PDF','pdf'],['Descargar consolidado Excel','xlsx']]){const [download]=await Promise.all([authority.waitForEvent('download'),authority.getByRole('link',{name:label,exact:true}).click()]);await download.saveAs(path.join(out,`competencia-final-${call}.${extension}`));assert(!await download.failure(),'Final report failed');}
   }
   const student=await login('A');const results={software:'91.50',redes:'80.00',electronica:'60.00',segunda:'20.00'};
   for(const [key,expected] of Object.entries(results)){const c=f.cases.find(c=>c.student==='A'&&c.offer===key);await student.goto(s.base+'/p2/postulaciones/'+c.application);await student.getByText('Final /100: '+expected,{exact:true}).waitFor();assert((await inspect(student,c)).application.final===expected,'Cross-category result changed');}
   await student.goto(s.base+'/p2/adjudicaciones');await student.getByRole('heading',{name:'Mis adjudicaciones',exact:true}).waitFor();const awards=await read(student,'/awards');assert(awards.awards.length===3,'Cross-category awards lost');assert(await student.getByRole('heading',{level:2}).filter({hasText:f.actors.A.name}).count()===3,'Published awards not visible');record('Postulante conserva cuatro resultados y tres adjudicaciones entre categorías',{results,awards:3});
   const other=f.cases.find(c=>c.student==='B'&&c.offer==='software');await student.goto(s.base+'/p2/postulaciones/'+other.application);await student.getByRole('alert').filter({hasText:'permiso'}).waitFor();assert(!await student.getByRole('heading',{name:'Expediente digital',exact:true}).count(),'Other candidate dossier exposed');record('Expediente ajeno rechazado desde URL directa');
   const evaluator=await login('evaluator2'),disabled=f.cases.find(c=>c.offer==='pendiente'&&c.disabled);await evaluator.goto(s.base+'/p2/postulaciones/'+disabled.application);await evaluator.getByRole('heading',{level:1}).waitFor();assert(!await evaluator.getByRole('button',{name:'Registrar nota',exact:true}).count(),'Disabled applicant can be graded in UI');record('Evaluador asignado no recibe formulario para inhabilitado');
   const wrong=await login('evaluator0'),network=f.cases.find(c=>c.offer==='redes'&&c.student==='A');await wrong.goto(s.base+'/p2/postulaciones/'+network.application);await wrong.getByRole('alert').filter({hasText:'permiso'}).waitFor();record('Permiso de evaluador sin asignación a la oferta no abre expediente');
  }
  assert(!errors.length,'JavaScript errors');const receipt={result:'PASS',phase,checks,traffic,errors,fixture_preparation:f.preparation,scope:'Academic mutations through React; independent decimal and PostgreSQL checks are separate receipts. No institutional pass threshold invented.'};fs.writeFileSync(path.join(out,'competitive-'+phase+'.json'),JSON.stringify(receipt,null,2));console.log(JSON.stringify({result:'PASS',phase,checks:checks.length}));
 }catch(e){for(const [key,p] of sessions)await p.screenshot({path:path.join(out,'failure-'+key.replace(/[^A-Za-z0-9_-]/g,'_')+'.png'),fullPage:true}).catch(()=>{});fs.writeFileSync(path.join(out,'failure.json'),JSON.stringify({phase,error:e.message,checks,traffic,errors},null,2));throw e;}finally{await browser.close();}
})().catch(e=>{console.error(e.message);process.exit(1)});
