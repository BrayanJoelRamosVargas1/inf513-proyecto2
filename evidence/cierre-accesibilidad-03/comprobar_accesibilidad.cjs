// Ensayo de navegador con cuentas sintéticas. No certifica conformidad WCAG completa.
const fs=require('fs'),path=require('path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const state=JSON.parse(fs.readFileSync(process.argv[2],'utf8'));
const session=JSON.parse(fs.readFileSync(path.join(state.session.private_dir,'session.json'),'utf8'));
const out=state.output,base=session.base_url;
if(!/^http:\/\/127\.0\.0\.1:8113$/.test(base))throw Error('Destino fuera del ensayo aislado');
const http=JSON.parse(fs.readFileSync(path.join(out,'http/recorrido-http.json'),'utf8'));
const partial=path.join(out,'accesibilidad.json');
const report=fs.existsSync(partial)?JSON.parse(fs.readFileSync(partial,'utf8')):{scope:'Detección automatizada axe y controles de teclado/reflujo en seis pantallas; no certificación WCAG ni prueba con lector de pantalla.',screens:[],throttles:[]};
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_EXECUTABLE});
 try{
  for(const role of ['invitado','admin','autoridad','revisor','evaluador','postulante']){
   if(report.screens.some(s=>s.role===role))continue;
   // CSP del servidor sigue intacta; bypass solo en el contexto de inspección para instrumentar axe.
   const context=await browser.newContext({viewport:{width:1280,height:900},bypassCSP:true});
   const page=await context.newPage();await page.goto(base+'/login');
   if(role!=='invitado'){
    for(let attempt=0;attempt<3;attempt++){
     await page.locator('input[name=email]').fill(role+'@recorrido.ficct.test');
     await page.locator('input[name=password]').fill(session.password);
     const resp=await Promise.all([page.waitForResponse(r=>r.url()===base+'/login'&&r.request().method()==='POST'),page.locator('button[type=submit],form button').first().click()]);
     if(resp[0].status()!==429)break;
     const delay=Math.min(61,Number(resp[0].headers()['retry-after']||60)+1);
     report.throttles.push({role,status:429,wait_seconds:delay});await page.waitForTimeout(delay*1000);await page.goto(base+'/login');
    }
    await page.waitForURL(url=>url.pathname!=='/login',{timeout:15000});
   }
   let target='/login';
   if(role==='admin')target='/usuarios';
   if(role==='postulante')target='/adjudicaciones';
   if(role==='autoridad')target=http.http_requests.filter(r=>r.role===role&&r.method==='GET'&&/^\/ofertas\/\d+\/resultados$/.test(r.path)&&r.status===200).at(-1).path;
   if(['revisor','evaluador'].includes(role))target=http.http_requests.find(r=>r.role==='evaluador'&&r.method==='GET'&&/^\/postulaciones\/\d+$/.test(r.path)&&r.status===200).path;
   const response=await page.goto(base+target);if(response.status()!==200)throw Error(role+' HTTP '+response.status());
   if(role!=='invitado'&&new URL(page.url()).pathname==='/login')throw Error('Sin sesión '+role);
   await page.addScriptTag({path:process.env.AXE_SOURCE});
   const axe=await page.evaluate(async()=>await axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21a','wcag21aa','wcag22aa']}}));
   await page.keyboard.press('Tab');
   const firstFocus=await page.evaluate(()=>({tag:document.activeElement.tagName,text:document.activeElement.textContent.trim(),href:document.activeElement.getAttribute('href')}));
   let skipWorking=false;
   if(firstFocus.href==='#contenido'){await page.keyboard.press('Enter');skipWorking=await page.evaluate(()=>document.activeElement.id==='contenido');}
   const keyboard=[];
   for(let i=0;i<10;i++){await page.keyboard.press('Tab');keyboard.push(await page.evaluate(()=>({tag:document.activeElement.tagName,text:(document.activeElement.textContent||document.activeElement.getAttribute('aria-label')||document.activeElement.getAttribute('name')||'').trim().slice(0,70)})));}
   await page.screenshot({path:path.join(out,'pantallas',role+'-desktop.png'),fullPage:true});
   await page.setViewportSize({width:320,height:900});await page.waitForTimeout(100);
   const reflow=await page.evaluate(()=>({viewport:innerWidth,documentWidth:document.documentElement.scrollWidth,overflow:document.documentElement.scrollWidth>innerWidth+1}));
   await page.screenshot({path:path.join(out,'pantallas',role+'-320px.png'),fullPage:true});
   report.screens.push({role,path:target,status:response.status(),axe_version:axe.testEngine.version,violations:axe.violations,incomplete:axe.incomplete,passes:axe.passes.map(x=>x.id),firstFocus,skipWorking,keyboard_sample:keyboard,reflow});
   fs.writeFileSync(path.join(out,'accesibilidad.json'),JSON.stringify(report,null,2));
   await context.close();
  }
 }finally{await browser.close();}
 report.result=report.screens.some(s=>s.violations.length||!s.skipWorking||s.reflow.overflow)?'HALLAZGOS':'SIN_HALLAZGOS_AUTOMATIZADOS_EN_MUESTRA';
 fs.writeFileSync(path.join(out,'accesibilidad.json'),JSON.stringify(report,null,2));
 console.log(JSON.stringify({result:report.result,screens:report.screens.length,violations:report.screens.map(s=>({role:s.role,ids:s.violations.map(v=>v.id),skip:s.skipWorking,reflow:s.reflow.overflow}))}));
})().catch(e=>{console.error(e.message);process.exit(1)});
