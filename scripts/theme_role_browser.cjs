// Synthetic-role accessibility matrix; preferences change only inside the isolated acceptance database.
const fs=require('fs'),path=require('path');
const {chromium}=require(process.env.FICCT_PLAYWRIGHT_MODULE||'playwright');
const state=JSON.parse(fs.readFileSync(process.argv[2],'utf8')),out=process.argv[3];
if(state.base!=='http://127.0.0.1:8119'||!state.scan_env.DB_DATABASE.startsWith('ficct_applicants_'))throw Error('Isolated acceptance state required');
const routes={admin:'/administracion',student:'/postulaciones',reviewer:'/revision',evaluator:'/evaluaciones',authority:'/expedientes'};
(async()=>{
 fs.mkdirSync(out,{recursive:true});const browser=await chromium.launch({headless:true,executablePath:state.chromium});const checks=[];
 try{
  for(const [role,route] of Object.entries(routes)){
   const context=await browser.newContext({locale:'es-BO',viewport:{width:320,height:900}});
   await context.route(state.base+'/audit-axe.js',r=>r.fulfill({path:state.axe,contentType:'application/javascript'}));
   const page=await context.newPage();await page.goto(state.base+'/p2');
   await page.getByLabel('Correo electrónico',{exact:true}).fill(state.credentials[role].email);
   await page.getByLabel('Contraseña',{exact:true}).fill(state.credentials[role].password);
   const login=page.waitForResponse(r=>r.url().endsWith('/login')&&r.request().method()==='POST');
   await page.getByRole('button',{name:'Entrar al sistema',exact:true}).click();
   const loginResult=await login;if(loginResult.status()!==200)throw Error('Login rejected for '+role+': HTTP '+loginResult.status());
   await page.locator('nav[aria-label=Principal]').waitFor({state:'attached'});
   await page.goto(state.base+'/p2'+route);await page.locator('main h1').waitFor();
   for(const theme of ['ninos','jovenes','adultos'])for(const mode of ['dia','noche'])for(const contrast of ['0','1']){
    const preferences=page.locator('.preferences');
    await preferences.locator('summary').waitFor();
    if(!await preferences.locator('select[name="theme"]').isVisible())await preferences.locator('summary').click();
    await preferences.locator('select[name="theme"]').selectOption(theme);
    await preferences.locator('select[name="mode"]').selectOption(mode);
    await preferences.locator('select[name="font"]').selectOption('200');
    await preferences.locator('select[name="contrast"]').selectOption(contrast);
    const response=page.waitForResponse(r=>r.url().endsWith('/me/preferences')&&r.request().method()==='PUT');
    await preferences.getByRole('button',{name:'Aplicar preferencias',exact:true}).click();
    if((await response).status()!==200)throw Error('Preferences rejected for '+role);
    await page.waitForFunction(v=>document.documentElement.dataset.theme===v.theme&&document.documentElement.dataset.mode===v.mode&&document.documentElement.dataset.font==='200'&&document.documentElement.dataset.contrast===(v.contrast==='1'?'alto':'normal'),{theme,mode,contrast});
    await page.getByRole('button',{name:'Aplicar preferencias',exact:true}).waitFor();
    // Measure the settled theme, rather than the intermediate colors of CSS transitions.
    await page.waitForFunction(()=>document.getAnimations().every(a=>a.playState!=='running'));
    await page.addScriptTag({url:state.base+'/audit-axe.js'});
    const result=await page.evaluate(async()=>({overflow:document.documentElement.scrollWidth>innerWidth+1,violations:(await axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21a','wcag21aa']}})).violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>({target:n.target,summary:n.failureSummary}))}))}));
    if(result.overflow)result.outside=await page.evaluate(()=>[...document.querySelectorAll('main *,header *,footer *')].filter(e=>e.getBoundingClientRect().right>innerWidth+1&&!e.closest('.table-wrap')).slice(0,12).map(e=>({tag:e.tagName,class:e.className,width:e.getBoundingClientRect().width,text:e.textContent.slice(0,60)})));
    checks.push({role,route,theme,mode,contrast:contrast==='1'?'alto':'normal',font:200,width:320,...result});
    if(result.overflow||result.violations.length)throw Error('Accessibility matrix failure: '+JSON.stringify(checks.at(-1)));
   }
   await page.screenshot({path:path.join(out,role+'-320px-200.png'),fullPage:true});
   await context.close();
  }
  fs.writeFileSync(path.join(out,'theme-matrix.json'),JSON.stringify({result:'PASS',checks,scope:'Five synthetic role workspaces; 60 combinations at 320 px and 200% font, six theme/mode combinations and two contrasts. CSP active. Selected automated WCAG checks; not human certification.'},null,2));
  console.log(JSON.stringify({result:'PASS',combinations:checks.length}));
 }catch(error){for(const [i,context] of browser.contexts().entries())for(const [j,page] of context.pages().entries()){await page.screenshot({path:path.join(out,`failure-${i}-${j}.png`),fullPage:true});fs.writeFileSync(path.join(out,`failure-${i}-${j}.txt`),await page.locator('body').innerText());}fs.writeFileSync(path.join(out,'failure.json'),JSON.stringify({error:error.message,checks},null,2));throw error;}
 finally{await browser.close();}
})().catch(e=>{console.error(e.message);process.exit(1)});
