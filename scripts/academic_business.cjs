// Operational configuration and credentials come only from a private generated JSON file.
const fs=require('fs'),path=require('path'),crypto=require('crypto'),{spawnSync}=require('child_process');
const {chromium}=require(process.env.FICCT_PLAYWRIGHT_MODULE||'playwright');
const s=JSON.parse(fs.readFileSync(process.argv[2],'utf8')),checks=[],requests=[],errors=[];
const assert=(v,m)=>{if(!v)throw Error(m)};
const record=(method,url,r)=>requests.push({method,route:url.replace(/\d+/g,':id'),status:r.status()});
(async()=>{
 const browser=await chromium.launch({headless:true,...(s.chromium?{executablePath:s.chromium}:{})});let page;const sessions={};
 try{
  async function get(c,url){const r=await c.request.get(s.base+'/api/v1'+url);record('GET',url,r);return r;}
  async function post(c,url,data,expected=200,multipart=false){const csrf=await(await get(c,'/csrf')).json();const r=await c.request.post(s.base+'/api/v1'+url,{headers:{'X-CSRF-TOKEN':csrf.token,Accept:'application/json'},...(multipart?{multipart:data}:{data})});record('POST',url,r);assert(r.status()===expected,`Unexpected ${r.status()} for ${url}; expected ${expected}`);return r;}
  async function login(role){if(sessions[role])return sessions[role];const c=await browser.newContext({locale:'es-BO'});await c.route(s.base+'/audit-axe.js',route=>route.fulfill({path:s.axe,contentType:'application/javascript'}));const lp=await c.newPage();await lp.goto(s.base+'/p2');await lp.getByLabel('Correo electrónico',{exact:true}).fill(s.credentials[role].email);await lp.getByLabel('Contraseña',{exact:true}).fill(s.credentials[role].password);await lp.getByRole('button',{name:'Entrar al sistema'}).click();await lp.locator('nav[aria-label=Principal]').waitFor();await lp.close();sessions[role]=c;return c;}
  async function inspect(c,id){return await(await get(c,'/applications/'+id)).json();}
  function scan(id,expected){const r=spawnSync('php',[s.scan,String(id)],{env:s.scan_env,encoding:'utf8',timeout:100000});assert(r.status===0,'Operator process failed');const result=JSON.parse(r.stdout);assert(result.state===expected,`Scanner returned ${result.state}; expected ${expected}`);checks.push(`Motor ClamAV real registra ${expected}`);return result;}
  const student=await login('student'),reviewer=await login('reviewer');
  page=await student.newPage();page.on('pageerror',e=>errors.push(e.message));
  await page.goto(s.base+'/p2/convocatorias/'+s.call);
  await page.getByRole('button',{name:'Iniciar postulación',exact:true}).click();
  await page.waitForURL(/\/postulaciones\/[0-9]+$/);
  const application=Number(page.url().split('/').pop());
  checks.push('Postulación creada desde la oferta mediante interfaz real');
  await page.goto(s.base+'/p2/postulaciones/'+application);await page.getByRole('heading',{name:'Expediente digital',exact:true}).waitFor();

  async function screen(role,route){const c=await login(role);const p=await c.newPage();p.on('pageerror',e=>errors.push(e.message));await p.goto(s.base+'/p2'+route);await p.locator('main h1,main h2').first().waitFor();return p;}
  function formBy(p,button){return p.locator('form').filter({has:p.getByRole('button',{name:button,exact:true})});}
  async function submit(p,form,button,route,status=200){
    const response=p.waitForResponse(r=>new URL(r.url()).pathname==='/api/v1'+route&&r.request().method()==='POST');
    await form.getByRole('button',{name:button,exact:true}).click();const r=await response;assert(r.status()===status,route+' HTTP '+r.status());record('POST UI',route,r);
    await p.getByRole('button',{name:'Procesando…',exact:true}).waitFor({state:'hidden'}).catch(()=>{});return r;
  }
  async function reviewUI(decisions,expected=200){const p=await screen('reviewer','/postulaciones/'+application);const f=formBy(p,'Guardar revisión por criterio');
    for(const [name,value] of Object.entries(decisions.decisions))await f.locator('[name="'+name+'"]').selectOption(value);
    await f.locator('[name=reason]').fill('corto');assert(!await f.evaluate(e=>e.checkValidity()),'Motivo breve admitido por cliente');
    await f.locator('[name=reason]').fill(decisions.reason);await submit(p,f,'Guardar revisión por criterio','/applications/'+application+'/revision',expected);
    if(expected===422){const text=await p.getByRole('alert').innerText();assert(text.length>10&&!/SQLSTATE|Exception|Stack trace/.test(text),'Error de revisión no orientado al usuario');}
    await p.screenshot({path:path.join(s.out,expected===200?'revision-formulario.png':'revision-error-validacion.png'),fullPage:true});await p.close();
  }
  const uploadForm=formBy(page,'Guardar archivo');assert(!await uploadForm.evaluate(e=>e.checkValidity()),'Archivo vacío admitido');
  await page.locator('input[type=file]').setInputFiles({name:'invalido.txt',mimeType:'text/plain',buffer:Buffer.from('No es un documento admitido')});
  await submit(page,uploadForm,'Guardar archivo','/applications/'+application+'/archivo',422);
  assert((await inspect(student,application)).documents.length===0,'Carga inválida creó un documento');
  await page.getByRole('alert').waitFor();checks.push('Carga inválida presenta error en interfaz y no crea documentos; archivo requerido validado por cliente');
  await page.locator('input[type=file]').setInputFiles(s.clean);await page.getByRole('button',{name:'Guardar archivo',exact:true}).click();await page.getByText('Análisis pendiente',{exact:true}).waitFor();
  let detail=await inspect(student,application),original=detail.documents[0].id;
  assert(detail.documents[0].security.state==='pending','Upload did not enter quarantine');assert(await page.getByRole('link',{name:/Abrir .*pdf/}).count()===0,'Unsafe open link');
  assert((await get(student,'/documents/'+original)).status()===423,'Pending download allowed');checks.push('Carga por interfaz: versión pendiente sin enlace de descarga y acceso directo 423');
  await page.screenshot({path:path.join(s.out,'postulante-pendiente.png'),fullPage:true});
  await page.getByRole('button',{name:'Confirmar envío definitivo',exact:true}).click();
  for(let i=0;i<30;i++){detail=await inspect(student,application);if(detail.application.status==='submitted')break;await page.waitForTimeout(100);}
  assert(detail.application.status==='submitted','Cannot submit pending dossier');checks.push('Estudiante envía expediente sin convertir el análisis pendiente en aprobación');
  const decisions={decisions:{'doc:cpd':'meets',activo:'meets'},reason:'Revisión sintética de documentación de prueba.'};
  await reviewUI(decisions,422);
  scan(original,'clean');
  const bytes=await(await get(student,'/documents/'+original)).body();assert(bytes.equals(fs.readFileSync(s.clean)),'Original bytes changed');
  await reviewUI(decisions);checks.push('Análisis válido permite descarga exacta y revisión autorizada');
  const reader=await screen('reviewer','/postulaciones/'+application);
  await reader.getByRole('button',{name:'Ver detalles: cpd.pdf',exact:true}).click();
  await reader.getByText('Original cargado, sin conversión ni reducción',{exact:true}).waitFor();
  assert((await reader.locator('.document-details').innerText()).includes('Formato: PDF'),'Reviewer cannot identify original format');
  const pendingDownload=reader.waitForEvent('download');
  await reader.getByRole('link',{name:'Descargar original: cpd.pdf',exact:true}).click();
  const received=await pendingDownload;
  assert(await received.failure()===null,'Browser download failed');
  const receivedBytes=fs.readFileSync(await received.path());
  assert(received.suggestedFilename()==='cpd.pdf','Original filename not preserved');
  assert(receivedBytes.equals(fs.readFileSync(s.clean)),'Reviewer downloaded altered or reduced bytes');
  await reader.screenshot({path:path.join(s.out,'revisor-original-descarga.png'),fullPage:true});
  await reader.close();checks.push('Revisor abre detalles y descarga desde React el PDF original: nombre y bytes coinciden con la carga');

  for(const role of ['authority','outsider']){const c=await login(role);assert((await get(c,'/documents/'+original)).status()===403,'Role received private file');if(role!=='authority'){await c.close();delete sessions[role];}}
  checks.push('Autoridad y otro estudiante no reciben archivos privados');
  await reviewUI({decisions:{'doc:cpd':'observed',activo:'meets'},reason:'Se solicita una versión nueva en la práctica sintética.'});

  async function openAmendment(){const p=await screen('reviewer','/postulaciones/'+application+'/participacion');const f=formBy(p,'Guardar').filter({has:p.locator('[name=kinds]')});
    await f.locator('[name=kinds]').selectOption('cpd');await f.locator('[name=deadline]').fill(new Date(Date.now()+7200000).toISOString().slice(0,16));
    await f.locator('[name=reason]').fill('Subsanación autorizada solo para la práctica.');await f.locator('[name=evidence]').fill('Referencia sintética de autorización de prueba.');
    await submit(p,f,'Guardar','/applications/'+application+'/amendments',201);await p.close();const r=await(await get(reviewer,'/applications/'+application+'/participation')).json();return r.amendments[0].id;
  }
  async function uploadAmendment(m,file){const p=await screen('student','/postulaciones/'+application+'/participacion');let f=formBy(p,'Agregar nueva versión');await f.locator('[name=file]').setInputFiles(file);await submit(p,f,'Agregar nueva versión','/amendments/'+m+'/documents');
    f=formBy(p,'Enviar subsanación a revisión');await submit(p,f,'Enviar subsanación a revisión','/amendments/'+m+'/submission');await p.close();const d=await inspect(student,application);return d.documents[0].id;
  }
  async function amendmentReview(m,data,expected=200){const p=await screen('reviewer','/postulaciones/'+application+'/participacion');const section=p.locator('section').filter({has:p.getByRole('heading',{name:'Nueva revisión completa',exact:true})}).last();const f=section.locator('form');
    for(const [name,value] of Object.entries(data.decisions))await f.locator('[name="'+name+'"]').selectOption(value);await f.locator('[name=reason]').fill(data.reason);
    await submit(p,f,'Guardar','/amendments/'+m+'/review',expected);if(expected===422)await p.getByRole('alert').waitFor();await p.screenshot({path:path.join(s.out,'subsanacion-'+m+'-'+expected+'.png'),fullPage:true});await p.close();
  }
  const m1=await openAmendment();const blocked=await uploadAmendment(m1,s.blocked);scan(blocked,'blocked');
  await amendmentReview(m1,decisions,422);assert((await get(student,'/documents/'+blocked)).status()===423,'Detected file allowed');
  await page.reload();await page.getByText('Archivo bloqueado',{exact:true}).waitFor();assert(await page.getByRole('link',{name:/Abrir .*pdf/}).count()===0,'Detected file offered');await page.screenshot({path:path.join(s.out,'postulante-bloqueado.png'),fullPage:true});checks.push('Firma inocua detectada por ClamAV: bloquea la nueva versión sin usar la aprobación anterior');
  await amendmentReview(m1,{decisions:{'doc:cpd':'observed',activo:'meets'},reason:'Se conserva el archivo bloqueado y se observa su versión.'});
  const m2=await openAmendment();const corrected=await uploadAmendment(m2,s.clean);
  await amendmentReview(m2,decisions,422);scan(corrected,'clean');await amendmentReview(m2,decisions);
  detail=await inspect(student,application);assert(detail.application.eligibility==='enabled','Re-review not completed');assert(detail.documents[0].id===corrected,'Wrong sealed version');
  assert((await get(student,'/documents/'+blocked)).status()===423,'Historical detected file was released');assert((await get(student,'/documents/'+original)).status()===200,'Original clean version unavailable');
  const history=await(await get(reviewer,'/applications/'+application+'/participation')).json();assert(history.documents.length===3,'Versions not preserved');checks.push('Segunda subsanación exige su propio análisis, habilita con revisión y conserva las tres versiones');
  await page.reload();await page.getByText('Análisis válido',{exact:true}).waitFor();await page.screenshot({path:path.join(s.out,'postulante-version-validada.png'),fullPage:true});
  await page.addScriptTag({url:s.base+'/audit-axe.js'});const violations=await page.evaluate(async()=>{const r=await axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21a','wcag21aa']}});return r.violations.map(v=>v.id)});assert(!violations.length,'Accessibility: '+violations.join(','));
  await page.setViewportSize({width:320,height:800});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Mobile overflow');await page.screenshot({path:path.join(s.out,'postulante-movil.png'),fullPage:true});assert(!errors.length,'JavaScript errors');checks.push('Pantalla sin fallos JS, sin hallazgos axe en alcance probado y sin desborde a 320 px');

  function operator(action,id){const r=spawnSync('php',[s.operator,action,String(id)],{env:s.scan_env,encoding:'utf8',timeout:30000});assert(r.status===0,'Operator failed: '+r.stderr);return JSON.parse(r.stdout);}

  const rp=await screen('reviewer','/postulaciones/'+application);let f=formBy(rp,'Confirmar méritos');await f.locator('[name=reference]').fill('Respaldo CPD de prueba con ClamAV real');await f.locator('[name=score]').fill('20');await submit(rp,f,'Confirmar méritos','/applications/'+application+'/meritos');await rp.close();
  const cp=await screen('authority','/convocatorias/'+s.call+'/configuracion');await cp.locator('details.card>summary').click();
  const assignForm=cp.locator('section').filter({has:cp.getByRole('heading',{name:'Asignar evaluador',exact:true})}).locator('form');await submit(cp,assignForm,'Guardar','/offers/'+s.offer+'/assignments');await cp.close();
  const stages=await screen('authority','/convocatorias/'+s.call);
  for(const label of ['Pasar a Recepción cerrada','Pasar a Evaluación']){const f=formBy(stages,label);await submit(stages,f,label,'/calls/'+s.call+'/transition');await stages.reload();}
  await stages.close();
  const evaluator=await login('evaluator'),authority=await login('authority');
  const assignment=(await inspect(evaluator,application)).assignments[0].id;

  const draftPage=await screen('evaluator','/postulaciones/'+application);let gf=formBy(draftPage,'Registrar nota');await gf.locator('[name=score]').fill('101');assert(!await gf.evaluate(e=>e.checkValidity()),'Nota mayor a 100 admitida por cliente');await gf.locator('[name=score]').fill('70');await gf.locator('[name=intent]').selectOption('draft');await submit(draftPage,gf,'Registrar nota','/applications/'+application+'/nota');await draftPage.close();checks.push('Méritos, asignación, cierre, evaluación y borrador de nota ejecutados mediante formularios; rango inválido rechazado por cliente');
  operator('enqueue',corrected);
  let d=await inspect(evaluator,application);assert(d.application.eligibility==='enabled'&&d.document_validation.ready===false&&!d.can_grade,'Academic and technical states conflated');assert(d.documents.length===0&&d.reviews.length===0,'Evaluator received private reviews');
  await post(evaluator,'/applications/'+application+'/nota',{assignment_id:assignment,score:'80',intent:'confirm'},422);
  await post(authority,'/applications/'+application+'/calcular',{},422);
  const ep=await evaluator.newPage();ep.on('pageerror',e=>errors.push(e.message));await ep.goto(s.base+'/p2/postulaciones/'+application);await ep.getByText('Validación documental pendiente',{exact:true}).waitFor();assert(await ep.getByRole('button',{name:'Registrar nota',exact:true}).count()===0,'Evaluator form remains active');await ep.screenshot({path:path.join(s.out,'evaluador-validacion-pendiente.png'),fullPage:true});
  const ap=await authority.newPage();await ap.goto(s.base+'/p2/postulaciones/'+application);await ap.getByText('Validación documental pendiente',{exact:true}).waitFor();assert(await ap.getByRole('button',{name:'Calcular resultado',exact:true}).count()===0,'Calculation form remains active');await ap.screenshot({path:path.join(s.out,'autoridad-validacion-pendiente.png'),fullPage:true});
  checks.push('DOC-21: habilitación enabled conservada, nueva nota y cálculo rechazados con 422 y formularios ocultos');
  scan(corrected,'clean');await ep.reload();await ep.getByText('Validación documental vigente',{exact:true}).waitFor();await ep.getByRole('button',{name:'Registrar nota',exact:true}).waitFor();
  const confirmForm=formBy(ep,'Registrar nota');await confirmForm.locator('[name=score]').fill('80');await confirmForm.locator('[name=intent]').selectOption('confirm');await submit(ep,confirmForm,'Registrar nota','/applications/'+application+'/nota');
  await ap.reload();const calculate=formBy(ap,'Calcular resultado');await submit(ap,calculate,'Calcular resultado','/applications/'+application+'/calcular');d=await inspect(authority,application);assert(d.application.final==='68.00','Wrong score after restored validation');await ap.reload();await ap.getByText('Validación documental vigente',{exact:true}).waitFor();await ap.screenshot({path:path.join(s.out,'autoridad-validacion-restablecida.png'),fullPage:true});
  checks.push('Nuevo análisis ClamAV limpio restablece evaluación; nota 80/100 y resultado 68/100, sin rehacer la habilitación');
  await ep.addScriptTag({url:s.base+'/audit-axe.js'});const doc21violations=await ep.evaluate(async()=>{const r=await axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21a','wcag21aa']}});return r.violations.map(v=>v.id)});assert(!doc21violations.length,'DOC21 accessibility: '+doc21violations);await ep.setViewportSize({width:320,height:800});assert(await ep.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Evaluator mobile overflow');await ep.screenshot({path:path.join(s.out,'evaluador-movil.png'),fullPage:true});
  checks.push('Evaluador sin documentos privados ni detalle de revisiones, sin hallazgos axe ni desborde a 320 px en la pantalla probada');

  const ranking=await screen('authority','/ofertas/'+s.offer+'/resultados');let publish=formBy(ranking,'Aprobar publicación DEMO');await publish.locator('[name=minutes]').fill('Acta sintética de aceptación del flujo académico, sin validez institucional.');await submit(ranking,publish,'Aprobar publicación DEMO','/offers/'+s.offer+'/publications');await ranking.getByText(/v1 ·/).waitFor();await ranking.screenshot({path:path.join(s.out,'publicacion-final.png'),fullPage:true});await ranking.close();
  const stage=await screen('authority','/convocatorias/'+s.call);let sf=formBy(stage,'Pasar a Resultados');await submit(stage,sf,'Pasar a Resultados','/calls/'+s.call+'/transition');await stage.close();
  const awards=await screen('student','/adjudicaciones');const choose=awards.locator('section').filter({has:awards.getByRole('heading',{name:'Registrar manifestación sobre esta publicación',exact:true})}).locator('form');await choose.locator('[name=reason]').fill('Aceptación de prueba sin valor de nombramiento oficial');await choose.locator('[name=evidence]').fill('Referencia sintética para verificar la trazabilidad');await submit(awards,choose,'Guardar','/applications/'+application+'/choices');await awards.close();checks.push('Cálculo 68/100, publicación v1 y manifestación DEMO completados por la interfaz');
  const payments=await screen('student','/simulacion');let pf=formBy(payments,'Registrar método de práctica');await submit(payments,pf,'Registrar método de práctica','/sandbox/methods',201);
  for(const outcome of ['failed','paid']){pf=formBy(payments,'Procesar simulación');await pf.locator('[name=amount]').fill('5.50');await pf.locator('[name=outcome]').selectOption(outcome);await pf.locator('[name=confirmed]').check();await submit(payments,pf,'Procesar simulación','/sandbox/payments',201);}
  pf=formBy(payments,'Crear plan');await pf.locator('[name=total]').fill('10.00');await pf.locator('[name=count]').fill('3');await submit(payments,pf,'Crear plan','/sandbox/plans',201);const response=payments.waitForResponse(r=>r.url().endsWith('/sandbox/payments')&&r.request().method()==='POST');await payments.getByRole('button',{name:'Simular pago de cuota',exact:true}).first().click();assert((await response).status()===201,'Cuota simulada no registrada');await payments.getByText('Pagada en simulación',{exact:false}).waitFor();await payments.screenshot({path:path.join(s.out,'pagos-y-cuotas.png'),fullPage:true});await payments.close();checks.push('Método, intento fallido, pago exitoso, plan y cuota simulada ejecutados desde React');
  const admin=await login('admin');const management=await screen('admin','/administracion');const uf=management.locator('section').filter({has:management.getByRole('heading',{name:'Crear usuario',exact:true})}).locator('form');await uf.locator('[name=name]').fill('Usuario sintético del ensayo académico');await uf.locator('[name=email]').fill('no-es-correo');assert(!await uf.evaluate(e=>e.checkValidity()),'Correo inválido admitido por cliente');await uf.locator('[name=email]').fill('cierre-'+crypto.randomUUID()+'@example.test');await uf.locator('[name=password]').fill(crypto.randomBytes(18).toString('hex'));await uf.locator('[name=faculty]').fill('FICCT');await uf.locator('[name=career]').fill('Sistemas');await submit(management,uf,'Guardar','/users',201);await management.screenshot({path:path.join(s.out,'administracion-usuario-creado.png'),fullPage:true});await management.close();checks.push('Administrador crea usuario por formulario y validación de correo funciona en cliente');

  fs.writeFileSync(path.join(s.out,'recorrido-http.json'),JSON.stringify({resultado:'PASS',checks,requests,errors,scan_acceptance:'ClamAV real: dos versiones limpias y una firma adicional inocua detectada; EICAR NOT_VERIFIED'},null,2));console.log(checks.join('\n'));
 }catch(e){if(page)await page.screenshot({path:path.join(s.out,'fallo.png'),fullPage:true}).catch(()=>{});throw e;}finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
