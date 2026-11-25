const fs=require('fs'),path=require('path');
const {chromium}=require('C:/Users/ElCremosasWee/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const s=JSON.parse(fs.readFileSync(process.argv[2],'utf8'));const checks=[],requests=[];
const assert=(v,m)=>{if(!v)throw Error(m)};
(async()=>{
 fs.mkdirSync(path.join(s.out,'pantallas'),{recursive:true});
 const b=await chromium.launch({headless:true,args:['--disable-gpu'],executablePath:'C:/Users/ElCremosasWee/AppData/Local/ms-playwright/chromium-1248/chrome-win64/chrome.exe'});
 const pages={};
 async function check(name,page,action){await action();assert(await page.locator('.alert.danger,.alert.error').count()===0,'Error visible: '+name);checks.push({nombre:name,resultado:'PASS'});await page.screenshot({path:path.join(s.out,'pantallas',String(checks.length).padStart(2,'0')+'.png'),fullPage:true});console.log(name);}
 async function submit(form,button){const p=form.page();await Promise.all([p.waitForNavigation(),button?form.getByRole('button',{name:button,exact:true}).click():form.locator('button').click()]);}
 async function text(form,name,value){await form.locator(`[name="${name}"]`).fill(value);}
 async function reason(form){await text(form,'reason','Comprobación sintética desde navegador, sin validez institucional.');if(await form.locator('[name="evidence"]').count())await text(form,'evidence','Evidencia sintética de la prueba local con navegador.');}
 try{
  for(const role of ['revisor','carla','autoridad','estudiante','docente']){
   const c=await b.newContext({viewport:{width:1366,height:900}});const p=await c.newPage();pages[role]=p;
   p.on('response',r=>{if(r.url().startsWith(s.base))requests.push({perfil:role,ruta:new URL(r.url()).pathname,metodo:r.request().method(),status:r.status()});});
   await p.goto(s.base+'/login');await p.locator('[name=email]').fill(s.credentials[role].email);await p.locator('[name=password]').fill(s.credentials[role].password);await Promise.all([p.waitForNavigation(),p.locator('button[type=submit],form button').first().click()]);assert(!p.url().endsWith('/login'),'Login '+role);
  }
  const app=s.fixture.amendment_application,proc=`/postulaciones/${app}/procesos`;let p=pages.revisor;
  await check('Ventana vencida se conserva y permite cierre explícito',p,async()=>{await p.goto(s.base+proc);assert(await p.getByText('Plazo vencido.',{exact:false}).count()>0,'No se muestra el vencimiento');const f=p.locator(`form[action="/subsanaciones/${s.fixture.amendment}/vencer"]`);await reason(f);await submit(f);assert(await p.getByText('Vencida',{exact:true}).count()>0,'No cerró ventana');});
  await check('Revisor autoriza una ventana nueva sin cambiar la anterior',p,async()=>{const f=p.locator(`form[action="/postulaciones/${app}/subsanaciones"]`);await f.locator('[name="kinds[]"][value=cpd]').check();await text(f,'deadline',s.deadline);await reason(f);await submit(f);assert(await p.getByText('Ventana abierta',{exact:true}).count()>0,'No abrió ventana');});
  p=pages.carla;
  await check('Postulante carga una versión nueva desde el formulario',p,async()=>{await p.goto(s.base+proc);const f=p.locator('form[enctype="multipart/form-data"]');await f.locator('[name=kind]').selectOption('cpd');await f.locator('[name=file]').setInputFiles(s.upload);await submit(f);assert(await p.locator('a[href^="/documentos/"]').count()>0,'Falta documento');});
  await check('Postulante envía y congela la subsanación',p,async()=>{const f=p.locator('form[action$="/enviar"]');await submit(f);assert(await p.locator('form[enctype="multipart/form-data"]').count()===0,'Sigue permitiendo cargar');});
  p=pages.revisor;
  await check('Revisor repite la revisión completa y habilita',p,async()=>{await p.goto(s.base+proc);const f=p.locator('form[action$="/revisar"]');for(const select of await f.locator('select').all())await select.selectOption('meets');await reason(f);await submit(f);assert(await p.getByText('Revisada',{exact:true}).count()>0,'Revisión sin cerrar');});
  p=pages.docente;
  await check('Evaluador no accede al proceso ni decide solicitudes',p,async()=>{
   const r=await p.goto(s.base+proc);assert(r.status()===403,'Detalle no restringido');
   await p.goto(s.base+'/');const token=await p.locator('form[action="/salir"] [name=_token]').inputValue();
   const result=await p.context().request.post(s.base+'/participacion/1/decision',{form:{_token:token,approved:'1',reason:'Intento de evaluación sin permiso de autoridad.'}});
   requests.push({perfil:'docente',ruta:'/participacion/1/decision',metodo:'POST',status:result.status(),esperado:403});assert(result.status()===403,'Evaluador decide solicitudes');
   await p.goto(s.base+proc);
  });
  p=pages.carla;
  await check('Postulante solicita retiro; todavía queda pendiente',p,async()=>{await p.goto(s.base+proc);const f=p.locator(`form[action="/postulaciones/${app}/participacion"]`);assert(await f.locator('[name=kind]').inputValue()==='withdrawal','Tipo incorrecto');await reason(f);await submit(f);assert(await p.getByText('Pendiente',{exact:false}).count()>0,'Solicitud no pendiente');assert(await p.locator('form[action^="/participacion/"]').count()===0,'Propietaria puede decidir');});
  p=pages.autoridad;
  await check('Otra autoridad aprueba el retiro conservando la revisión',p,async()=>{await p.goto(s.base+proc);const f=p.locator('form[action^="/participacion/"]');await reason(f);await submit(f,'Aprobar solicitud DEMO');assert(await p.getByText('Retirada',{exact:false}).count()>0,'No retiró');});
  p=pages.estudiante;const appAward=s.fixture.applications[2],offer=s.fixture.offers[2];
  await check('Múltiples adjudicaciones permanecen y se registra manifestación DEMO',p,async()=>{await p.goto(s.base+'/adjudicaciones');assert(await p.getByText('Múltiples adjudicaciones detectadas.',{exact:false}).count()>0,'No advierte múltiples');const f=p.locator(`form[action="/postulaciones/${appAward}/aceptacion"]`);await f.locator('[name=choice]').selectOption('accept');await reason(f);await submit(f);assert(await p.getByText('Aceptación DEMO',{exact:false}).count()>0,'Falta manifestación');});
  await check('Renuncia a una plaza se solicita por separado',p,async()=>{await p.goto(s.base+`/postulaciones/${appAward}/procesos`);const f=p.locator(`form[action="/postulaciones/${appAward}/participacion"]`);assert(await f.locator('[name=kind]').inputValue()==='renunciation','Confunde renuncia y retiro');await reason(f);await submit(f);});
  p=pages.autoridad;
  await check('Autoridad aprueba renuncia y conserva vacante retenida',p,async()=>{await p.goto(s.base+`/postulaciones/${appAward}/procesos`);const f=p.locator('form[action^="/participacion/"]');await reason(f);await submit(f,'Aprobar solicitud DEMO');await p.goto(s.base+`/ofertas/${offer}/resultados`);assert(await p.getByText('1 plaza retenida por renuncia',{exact:false}).count()>0,'Motivo de vacante incorrecto');assert(await p.getByRole('heading',{name:'Publicación conservada · v1',exact:true}).count()===1,'Publicación histórica falta');});
  await check('Nueva aprobación conserva publicación anterior sin adjudicar reemplazo',p,async()=>{const f=p.locator(`form[action="/ofertas/${offer}/aprobar"]`);await text(f,'minutes','Nueva publicación exclusivamente sintética para validar vacante retenida y conservación histórica.');await submit(f);assert(await p.getByRole('heading',{name:'Publicación conservada · v2',exact:true}).count()===1,'Falta v2');assert(await p.getByRole('heading',{name:'Publicación conservada · v1',exact:true}).count()===1,'Se perdió v1');assert(await p.getByText('1 plaza retenida por renuncia',{exact:false}).count()>0,'Vacante no conservada');});
  const reviewer=pages.revisor;await reviewer.goto(s.base+proc);const doc=(await reviewer.locator('a[href^="/documentos/"]').first().getAttribute('href'));
  p=pages.estudiante;
  await check('Otro postulante no accede al expediente ni al documento subsanado',p,async()=>{
   const r=await p.goto(s.base+proc);assert(r.status()===403,'Expediente ajeno visible');
   const d=await p.context().request.get(s.base+doc);requests.push({perfil:'estudiante',ruta:doc,metodo:'GET',status:d.status(),esperado:403});assert(d.status()===403,'Documento ajeno accesible');
  });
  fs.writeFileSync(path.join(s.out,'recorrido-navegador.json'),JSON.stringify({resultado:'PASS',comprobaciones:checks,solicitudes:requests,credenciales_incluidas:false,alcance:'Formularios reales en Chromium sobre copia aislada sintética.'},null,2));
 }finally{await b.close();}
})().catch(e=>{fs.writeFileSync(path.join(s.out,'intento-incompleto.json'),JSON.stringify({resultado:'INCOMPLETO',comprobaciones:checks,solicitudes:requests,error:String(e)},null,2));console.error(e);process.exit(1)});
