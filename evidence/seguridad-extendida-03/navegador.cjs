const fs=require('fs'),path=require('path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE);
const state=JSON.parse(fs.readFileSync(process.argv[2],'utf8'));
const session=JSON.parse(fs.readFileSync(path.join(state.session.private_dir,'session.json'),'utf8'));
const fixture=JSON.parse(fs.readFileSync(path.join(state.output,'fixtures.json'),'utf8'));
const base=session.base_url,out=state.output;if(base!=='http://127.0.0.1:8113')throw Error('Destino no aislado');
const routes={
 invitado:['/login','/registro'],
 admin:['/','/usuarios'],
 autoridad:['/',`/convocatorias/${fixture.base_call}`,`/convocatorias/${fixture.draft}?editar_oferta=${fixture.draft_offer}`,`/convocatorias/${fixture.published}`,`/ofertas/${fixture.offer}/resultados`,`/convocatorias/${fixture.base_call}/auditoria`,'/rectificaciones',`/postulaciones/${fixture.base_app}/rectificaciones`,`/postulaciones/${fixture.observed_app}/procesos`],
 revisor:['/',`/convocatorias/${fixture.base_call}`,`/postulaciones/${fixture.observed_app}`,`/postulaciones/${fixture.observed_app}/procesos`,`/postulaciones/${fixture.base_app}/rectificaciones`,'/rectificaciones'],
 evaluador:['/',`/convocatorias/${fixture.base_call}`,`/postulaciones/${fixture.base_app}`,`/postulaciones/${fixture.base_app}/rectificaciones`,'/rectificaciones'],
 postulante:['/',`/convocatorias/${fixture.published}`,`/postulaciones/${fixture.base_app}`,`/postulaciones/${fixture.observed_app}/procesos`,'/adjudicaciones']
};
const report={scope:'Cobertura ampliada de pantallas y details abiertos, axe WCAG A/AA y reflujo a 320px; sin certificación integral.',screens:[],security:[],throttles:[]};
function save(){fs.writeFileSync(path.join(out,'navegador-ampliado.json'),JSON.stringify(report,null,2));}
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_EXECUTABLE});
 try{
  for(const [role,paths] of Object.entries(routes)){
   const context=await browser.newContext({viewport:{width:1280,height:900},bypassCSP:true});const page=await context.newPage();
   await page.goto(base+'/login');
   if(role!=='invitado'){
    for(let retry=0;retry<3;retry++){
     await page.locator('[name=email]').fill(role+'@recorrido.ficct.test');await page.locator('[name=password]').fill(session.password);
     const [resp]=await Promise.all([page.waitForResponse(r=>r.url()===base+'/login'&&r.request().method()==='POST'),page.locator('form button').first().click()]);
     if(resp.status()!==429)break;
     const wait=Number(resp.headers()['retry-after']||60)+1;report.throttles.push({role,status:429,seconds:wait});await page.waitForTimeout(wait*1000);await page.goto(base+'/login');
    }
    await page.waitForURL(u=>u.pathname!=='/login');
   }
   for(const url of paths){
    await page.setViewportSize({width:1280,height:900});const resp=await page.goto(base+url);if(resp.status()!==200)throw Error(role+' '+url+' HTTP '+resp.status());
    await page.evaluate(()=>document.querySelectorAll('details').forEach(d=>d.open=true));
    await page.addScriptTag({path:process.env.AXE_SOURCE});
    const axe=await page.evaluate(()=>axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21a','wcag21aa','wcag22aa']}}));
    const name=role+'-'+String(report.screens.length+1).padStart(2,'0');
    await page.screenshot({path:path.join(out,'pantallas',name+'-desktop.png'),fullPage:true});
    await page.setViewportSize({width:320,height:900});await page.waitForTimeout(50);
    const reflow=await page.evaluate(()=>({width:innerWidth,documentWidth:document.documentElement.scrollWidth,overflow:document.documentElement.scrollWidth>innerWidth+1,elements:[...document.querySelectorAll('body *')].filter(e=>e.getBoundingClientRect().right>innerWidth+1).slice(0,15).map(e=>({tag:e.tagName,className:e.className,right:e.getBoundingClientRect().right,text:e.textContent.trim().slice(0,60)}))}));
    await page.screenshot({path:path.join(out,'pantallas',name+'-320px.png'),fullPage:true});
    report.screens.push({role,path:url,axe_version:axe.testEngine.version,violations:axe.violations,incomplete:axe.incomplete,reflow,capture:name});save();
   }
   const app=fixture.base_app,doc=fixture.base_document,obs=fixture.observed_app;
   const probes=role==='invitado'?[[`/postulaciones/${app}`,302],['/usuarios',302]]:
    role==='admin'?[[`/postulaciones/${app}`,403],[`/documentos/${doc}`,403],[`/convocatorias/${fixture.base_call}/auditoria`,403]]:
    role==='autoridad'?[[`/documentos/${doc}`,403]]:
    role==='revisor'?[[`/documentos/${doc}`,200],[`/convocatorias/${fixture.base_call}/reporte/pdf`,403]]:
    role==='evaluador'?[[`/documentos/${doc}`,403],[`/postulaciones/${obs}`,403],[`/postulaciones/${app}/procesos`,403]]:
    [[`/usuarios`,403],[`/convocatorias/${fixture.draft}`,403],[`/postulaciones/4`,403]];
   for(const [url,expected] of probes){
    const response=await context.request.get(base+url,{maxRedirects:0});const headers=response.headers();
    const secured=headers['x-content-type-options']==='nosniff'&&headers['x-frame-options']==='DENY'&&(headers['cache-control']||'').includes('no-store')&&(headers['content-security-policy']||'').includes("object-src 'none'");
    report.security.push({role,path:url,status:response.status(),expected,secured});save();
   }
   await context.close();
  }
 }finally{await browser.close();}
 report.result=report.screens.some(s=>s.violations.length||s.reflow.overflow)||report.security.some(s=>s.status!==s.expected||!s.secured)?'HALLAZGOS':'PASS_EN_MUESTRA';save();
 console.log(JSON.stringify({result:report.result,screens:report.screens.length,violations:report.screens.filter(s=>s.violations.length).map(s=>({role:s.role,path:s.path,ids:s.violations.map(v=>v.id)})),overflows:report.screens.filter(s=>s.reflow.overflow).map(s=>({role:s.role,path:s.path,width:s.reflow.documentWidth})),security:report.security.length,security_failures:report.security.filter(s=>s.status!==s.expected||!s.secured)}));
})().catch(e=>{console.error(e.message);process.exit(1)});
