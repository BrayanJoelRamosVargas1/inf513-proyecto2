// Read-only role walkthrough against a private, isolated acceptance database.
const fs=require('fs'),path=require('path');
const {chromium}=require(process.env.FICCT_PLAYWRIGHT_MODULE||'playwright');
const s=JSON.parse(fs.readFileSync(process.argv[2],'utf8'));
const out=process.argv[3],checks=[],requests=[],errors=[];
const assert=(ok,message)=>{if(!ok)throw Error(message)};
const expected={student:'/postulaciones',reviewer:'/revision',evaluator:'/evaluaciones',authority:'/expedientes',admin:'/administracion'};
(async()=>{
 fs.mkdirSync(out,{recursive:true});
 const browser=await chromium.launch({headless:true,executablePath:s.chromium});
 try{
  for(const role of Object.keys(expected)){
   const context=await browser.newContext({locale:'es-BO',viewport:{width:1440,height:960}});
   const p=await context.newPage();
   p.on('pageerror',e=>errors.push({role,message:e.message}));
   p.on('response',r=>{if(r.url().startsWith(s.base))requests.push({role,path:new URL(r.url()).pathname,status:r.status()})});
   await p.goto(s.base+'/p2');
   await p.getByLabel('Correo electrónico',{exact:true}).fill(s.credentials[role].email);
   await p.getByLabel('Contraseña',{exact:true}).fill(s.credentials[role].password);
   await p.getByRole('button',{name:'Entrar al sistema',exact:true}).click();
   const nav=p.getByRole('navigation',{name:'Principal',exact:true});
   await nav.waitFor();
   await p.locator('main h1').waitFor();
   await p.waitForFunction(()=>![...document.querySelectorAll('p[role=status]')].some(e=>e.textContent==='Cargando información…'));
   const links=await nav.locator('a').evaluateAll(es=>es.map(e=>({label:e.textContent,path:new URL(e.href).pathname})));
   assert(links.some(l=>l.path==='/p2'+expected[role]),'Missing workspace for '+role);
   if(role!=='student')assert(!links.some(l=>l.path==='/p2/postulaciones'||l.path==='/p2/simulacion'),'Irrelevant personal menu: '+role);
   for(const [other,target] of Object.entries(expected)){
    if(other!==role)assert(!links.some(l=>l.path==='/p2'+target),'Cross-role menu '+role+' -> '+other);
   }
   const home=await p.locator('main').innerText();
   if(role!=='student')assert(!home.includes('Mis postulaciones'),'Student dashboard shown to '+role);
   if(role==='admin')assert(!home.includes('Revisar expediente')&&!home.includes('Abrir evaluación'),'Admin inherited academic tasks');
   await p.screenshot({path:path.join(out,role+'-inicio.png'),fullPage:true});
   if(['student','reviewer'].includes(role)){
    const list=await context.request.get(s.base+'/api/v1/applications?workspace='+(role==='student'?'own':'reviewer'));
    assert(list.status()===200,'Cannot inspect authorized dossier');
    const application=(await list.json()).applications.find(a=>a.offer_id===s.offer);
    assert(application,'Synthetic dossier missing');
    await p.goto(s.base+'/p2/postulaciones/'+application.id);
    await p.getByRole('heading',{name:'Observaciones y fundamentos de revisión',exact:true}).waitFor();
    await p.getByRole('button',{name:/^Ver detalles:/}).first().click();
    await p.getByText('Original cargado, sin conversión ni reducción',{exact:true}).waitFor();
    assert(await p.getByRole('link',{name:/^Descargar original:/}).count()>0,'Missing original download');
    await p.screenshot({path:path.join(out,role+'-documentos-y-observaciones.png'),fullPage:true});
    await p.setViewportSize({width:390,height:844});
    assert(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Document details overflow on mobile');
    await p.setViewportSize({width:1440,height:960});
    checks.push({role,check:'Authorized original download, document metadata, review grounds and mobile document layout',result:'PASS'});
   }

   for(const link of links){
    await p.goto(s.base+link.path);
    await p.locator('main h1,main h2').first().waitFor();
    await p.waitForFunction(()=>![...document.querySelectorAll('p[role=status]')].some(e=>e.textContent==='Cargando información…'));
    assert(await p.getByRole('alert').count()===0,'Menu leads to an error for '+role+': '+link.path);
    checks.push({role,path:link.path,result:'PASS'});
   }
   await p.goto(s.base+'/p2'+expected[role]);
   await p.locator('main h1').waitFor();
   await p.waitForFunction(()=>![...document.querySelectorAll('p[role=status]')].some(e=>e.textContent==='Cargando información…'));
   // Keep the application CSP enabled; serve only the test instrument from an allowed same-origin URL.
   await context.route(s.base+'/audit-axe.js',route=>route.fulfill({path:s.axe,contentType:'application/javascript'}));
   await p.addScriptTag({url:s.base+'/audit-axe.js'});
   const violations=await p.evaluate(async()=>{const r=await axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21a','wcag21aa']}});return r.violations.map(v=>({id:v.id,nodes:v.nodes.length}))});
   assert(!violations.length,'Accessibility '+role+': '+JSON.stringify(violations));
   await p.setViewportSize({width:390,height:844});
   await p.getByRole('button',{name:'Abrir menú',exact:true}).click();
   await nav.getByRole('link').first().waitFor({state:'visible'});
   await p.locator('aside').getByRole('button',{name:'Cerrar menú',exact:true}).click();
   await p.screenshot({path:path.join(out,role+'-movil.png'),fullPage:true});
   assert(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Mobile overflow '+role);
   checks.push({role,check:'Workspace, mobile navigation, 390px and selected axe A/AA checks',result:'PASS'});
   const denied=role==='student'?'/revision':role==='reviewer'?'/evaluaciones':role==='evaluator'?'/expedientes':role==='authority'?'/administracion':'/revision';
   await p.goto(s.base+'/p2'+denied);
   await p.getByRole('alert').waitFor();
   assert((await p.getByRole('alert').innerText()).includes('permiso'),'Missing restricted-route message');
   checks.push({role,check:'Direct route for another responsibility denied',path:denied,result:'PASS'});
   await context.close();
  }
  assert(!errors.length,'JavaScript errors: '+JSON.stringify(errors));
  assert(!requests.some(r=>r.status>=500),'HTTP server error');
  fs.writeFileSync(path.join(out,'roles-browser.json'),JSON.stringify({result:'PASS',checks,requests,errors,scope:'Five synthetic profiles, menu pages, direct unauthorized routes, five workspace accessibility scans and mobile navigation. No institutional acceptance.'},null,2));
  console.log(JSON.stringify({result:'PASS',checks:checks.length,requests:requests.length}));
 }catch(e){fs.writeFileSync(path.join(out,'failure.json'),JSON.stringify({error:e.message,checks,requests,errors},null,2));throw e;}
 finally{await browser.close();}
})().catch(e=>{console.error(e.message);process.exit(1)});
