// Mutations use visible forms only. HTTP reads independently check persisted state.
const fs=require('fs'),path=require('path');
const {chromium}=require(process.env.FICCT_PLAYWRIGHT_MODULE||'playwright');
const statePath=process.argv[2],s=JSON.parse(fs.readFileSync(statePath,'utf8')),out=process.argv[3];
if(s.base!=='http://127.0.0.1:8119'||!s.scan_env.DB_DATABASE.startsWith('ficct_applicants_'))throw Error('Isolated database required');
const fixture=JSON.parse(fs.readFileSync(path.join(path.dirname(statePath),'functional-call-private.json'),'utf8'));
const checks=[],assert=(ok,msg)=>{if(!ok)throw Error(msg);};
const section=(p,title)=>p.locator('section').filter({has:p.getByRole('heading',{name:title,exact:true})}).last();
async function send(p,container,endpoint,values){
 for(const [name,value] of Object.entries(values)){const f=container.locator(`[name="${name}"]`);if(await f.evaluate(e=>e.tagName==='SELECT'))await f.selectOption(value);else await f.fill(value);}
 const [r]=await Promise.all([p.waitForResponse(r=>r.url().endsWith(endpoint)&&r.request().method()==='POST'),container.getByRole('button',{name:'Guardar',exact:true}).click()]);
 assert(r.ok(),'Form rejected: '+r.status()+' '+(await r.text()).slice(0,250));
}
(async()=>{
 fs.mkdirSync(out,{recursive:true});const browser=await chromium.launch({headless:true,executablePath:s.chromium}),contexts=[];
 async function login(role){const ctx=await browser.newContext({viewport:{width:1366,height:1000},locale:'es-BO'});contexts.push(ctx);const p=await ctx.newPage(),c=fixture.actor[role];await p.goto(s.base+'/p2');await p.getByLabel('Correo electrónico',{exact:true}).fill(c.email);await p.getByLabel('Contraseña',{exact:true}).fill(c.password);await p.getByRole('button',{name:'Entrar al sistema',exact:true}).click();await p.getByRole('navigation',{name:'Principal',exact:true}).waitFor();return p;}
 try{
  const student=await login('student'),authority=await login('authority');
  const read=async(p,url)=>{const r=await p.request.get(s.base+'/api/v1'+url);assert(r.ok(),'Read denied '+url);return r.json();};
  const awards=(await read(student,'/awards')).awards.filter(a=>a.call_id===fixture.call);
  assert(awards.length===2&&awards.every(a=>a.participation==='active'),'Requires two active awards; never reset history');
  const before={};for(const a of awards)before[a.id]=await read(authority,`/offers/${fixture.offers[awards.indexOf(a)]}/ranking`);
  for(const [index,a] of awards.entries()){
   await student.goto(s.base+'/p2/adjudicaciones');await student.getByRole('heading',{name:'Mis adjudicaciones',exact:true}).waitFor();
   const card=student.locator('section.card').filter({has:student.getByRole('heading',{name:a.name+' · '+a.title,exact:true})});
   if(a.choice)assert(a.choice.choice===(index?'defer':'accept')&&a.choices.length===1,'Unexpected existing choice; do not overwrite history');
   else await send(student,card,`/applications/${a.id}/choices`,{choice:index?'defer':'accept',reason:'Manifestacion sintetica de prueba desde el formulario visible.',evidence:'Referencia sintetica de la decision del postulante.'});
   await student.reload();const persisted=(await read(student,'/awards')).awards.find(x=>x.id===a.id);assert(persisted.choice.choice===(index?'defer':'accept')&&persisted.choices.length===1,'Choice not persisted');
   await card.locator('summary').click();await student.getByText(index?'v1 · Decisión diferida':'v1 · Aceptación DEMO',{exact:false}).waitFor();checks.push({action:index?'Diferir segunda plaza sin incompatibilidad automática':'Aceptar primera plaza y conservar versión',result:'FUNCIONA Y VERIFICADO'});
  }
  const a=awards[0];await student.goto(s.base+`/p2/postulaciones/${a.id}/participacion`);
  await send(student,section(student,'Solicitar renuncia a plaza'),`/applications/${a.id}/participation-requests`,{reason:'Solicitud sintetica de renuncia para verificar preservacion historica.',evidence:'Constancia sintetica presentada por el titular de la plaza.'});
  await student.reload();let participation=await read(student,`/applications/${a.id}/participation`);assert(participation.app.participation==='active','Request changed participation before approval');const request=participation.requests.find(r=>r.status==='pending');assert(request,'Request missing');checks.push({action:'Solicitar renuncia sin alterar adjudicación antes de decisión',result:'FUNCIONA Y VERIFICADO'});
  await authority.goto(s.base+`/p2/postulaciones/${a.id}/participacion`);
  await send(authority,section(authority,'Decidir participación'),`/participation-requests/${request.id}/decision`,{approved:'1',reason:'Decision sintetica de autoridad; conservar plaza retenida sin reemplazante.'});
  await authority.reload();participation=await read(authority,`/applications/${a.id}/participation`);assert(participation.app.participation==='resigned','Renunciation not persisted');
  for(const [index,award] of awards.entries()){
   const ranking=await read(authority,`/offers/${fixture.offers[index]}/ranking`);assert(JSON.stringify(ranking.publications)===JSON.stringify(before[award.id].publications),'Historical publication changed');
   if(index===0){assert(ranking.offer.held_vacancies===1&&ranking.offer.needs_publication_review,'Held vacancy/review missing');await authority.goto(s.base+`/p2/ofertas/${fixture.offers[index]}/resultados`);await authority.getByText('Vacantes totales: 1 · Retenidas: 1 · Por insuficiencia: 0',{exact:true}).waitFor();await authority.screenshot({path:path.join(out,'renuncia-vacante-retenida.png'),fullPage:true});}
   else assert(ranking.offer.held_vacancies===0,'Other award altered');
  }
  checks.push({action:'Aprobar renuncia, retener vacante y preservar ambas publicaciones',result:'FUNCIONA Y VERIFICADO'});
  const receipt={result:'PASS',call:fixture.call,checks,scope:'Two concurrent awards, versioned choices and one approved renunciation. No replacement, publication update or database fixture mutation.'};fs.writeFileSync(path.join(out,'participation-ui.json'),JSON.stringify(receipt,null,2));console.log(JSON.stringify(receipt));
 }catch(e){fs.writeFileSync(path.join(out,'failure.json'),JSON.stringify({error:e.message,checks},null,2));throw e;}finally{await browser.close();}
})().catch(e=>{console.error(e.message);process.exit(1)});
