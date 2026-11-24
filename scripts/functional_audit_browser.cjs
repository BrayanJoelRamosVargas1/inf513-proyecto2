const fs=require('fs'),path=require('path');
const {chromium}=require(process.env.FICCT_PLAYWRIGHT_MODULE||'playwright');
const s=JSON.parse(fs.readFileSync(process.argv[2],'utf8')),out=process.argv[3],fixture=JSON.parse(fs.readFileSync(path.join(path.dirname(process.argv[2]),'functional-call-private.json'),'utf8'));
if(s.base!=='http://127.0.0.1:8119'||!s.scan_env.DB_DATABASE.startsWith('ficct_applicants_'))throw Error('Isolated acceptance database required');
(async()=>{
 fs.mkdirSync(out,{recursive:true});const browser=await chromium.launch({headless:true,executablePath:s.chromium}),checks=[];
 try{
  for(const role of ['authority','reviewer','evaluator','student','admin']){
   const c=role==='admin'?s.credentials.admin:fixture.actor[role],context=await browser.newContext({viewport:{width:1366,height:900},locale:'es-BO'}),p=await context.newPage();
   await p.goto(s.base+'/p2');await p.getByLabel('Correo electrónico',{exact:true}).fill(c.email);await p.getByLabel('Contraseña',{exact:true}).fill(c.password);await p.getByRole('button',{name:'Entrar al sistema',exact:true}).click();await p.getByRole('navigation',{name:'Principal',exact:true}).waitFor();
   if(role==='authority'){
    await p.goto(s.base+`/p2/convocatorias/${fixture.call}`);await p.getByRole('link',{name:'Consultar historial del proceso →',exact:true}).click();await p.getByRole('heading',{name:'Historial del proceso',exact:true}).waitFor();
    await p.getByText(/^Visitas de esta vista: [0-9]+$/).waitFor();
    await p.getByRole('navigation',{name:'Principal',exact:true}).getByRole('link',{name:'Convocatorias',exact:true}).waitFor();
    const row=p.locator('tbody tr').filter({hasText:'Resultado calculado'}).first();await row.locator('summary').click();await row.getByText(/"final": "74.50"/).waitFor();
    await p.screenshot({path:path.join(out,'auditoria-con-valores-y-contador.png'),fullPage:true});
    await p.setViewportSize({width:390,height:844});
    if(await p.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1))throw Error('Audit mobile overflow');
    await context.route(s.base+'/audit-axe.js',r=>r.fulfill({path:s.axe,contentType:'application/javascript'}));await p.addScriptTag({url:s.base+'/audit-axe.js'});
    const violations=await p.evaluate(async()=>(await axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21a','wcag21aa']}})).violations.map(x=>x.id));if(violations.length)throw Error('Audit accessibility: '+violations.join(','));
    await p.goto(s.base+`/p2/convocatorias/${fixture.call}`);
    for(const [label,name] of [['Descargar consolidado PDF','consolidado-recorrido.pdf'],['Descargar consolidado Excel','consolidado-recorrido.xlsx']]){
     const [download]=await Promise.all([p.waitForEvent('download'),p.getByRole('link',{name:label,exact:true}).click()]);await download.saveAs(path.join(out,name));if(await download.failure())throw Error('Report download failed');
    }
    await p.setViewportSize({width:1366,height:900});
    await p.goto(s.base+`/p2/ofertas/${fixture.offers[0]}/resultados`);
    await p.getByRole('heading',{name:/^Clasificación y selección/}).waitFor();
    await p.getByRole('navigation',{name:'Principal',exact:true}).getByRole('link',{name:'Convocatorias',exact:true}).waitFor();
    await p.getByText(/^Visitas de esta vista: [0-9]+$/).waitFor();
    await p.screenshot({path:path.join(out,'clasificacion-con-navegacion-cargada.png'),fullPage:true});
    checks.push({role,result:'FUNCIONA Y VERIFICADO',checks:['Discoverable link','Real persisted before/after values','Footer counter available','390px reflow','Selected axe checks with CSP active']});
   }else{
    await p.goto(s.base+`/p2/convocatorias/${fixture.call}/auditoria`);await p.getByRole('alert').waitFor();if(!(await p.getByRole('alert').innerText()).includes('permiso'))throw Error('Unexpected denial');if(await p.getByRole('heading',{name:'Historial del proceso',exact:true}).count())throw Error('Audit data exposed');checks.push({role,result:'FUNCIONA Y VERIFICADO',checks:['Unauthorized direct route denied without audit data']});
    if(role==='student'||role==='reviewer'){
     const response=await p.request.get(s.base+'/api/v1/applications?workspace='+(role==='student'?'own':'reviewer')),data=await response.json(),a=data.applications.find(x=>fixture.offers.includes(x.offer_id));if(!a)throw Error('Owned application absent');await p.goto(s.base+'/p2/postulaciones/'+a.id);
     const [download]=await Promise.all([p.waitForEvent('download'),p.getByRole('link',{name:/^Descargar original:/}).first().click()]);const target=path.join(out,role+'-documento-original.pdf');await download.saveAs(target);if(Buffer.compare(fs.readFileSync(target),fs.readFileSync(s.clean)))throw Error('Downloaded original changed');checks.push({role,result:'FUNCIONA Y VERIFICADO',checks:['Original PDF downloaded by actual browser link with identical bytes']});
    }
   }
   await context.close();
  }
  fs.writeFileSync(path.join(out,'audit-ui.json'),JSON.stringify({result:'PASS',checks,scope:'Synthetic call only; no expansion of academic permissions'},null,2));console.log(JSON.stringify({result:'PASS',roles:new Set(checks.map(c=>c.role)).size,checks:checks.length}));
 }catch(e){fs.writeFileSync(path.join(out,'failure.json'),JSON.stringify({error:e.message,checks},null,2));throw e;}finally{await browser.close();}
})().catch(e=>{console.error(e.message);process.exit(1)});
