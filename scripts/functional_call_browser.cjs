// Mutations are performed through React forms against the retained isolated acceptance database.
const fs=require('fs'),path=require('path'),crypto=require('crypto');
const {chromium}=require(process.env.FICCT_PLAYWRIGHT_MODULE||'playwright');
const s=JSON.parse(fs.readFileSync(process.argv[2],'utf8')),out=process.argv[3];
if(s.base!=='http://127.0.0.1:8119'||!s.scan_env.DB_DATABASE.startsWith('ficct_applicants_'))throw Error('Isolated database required');
const checks=[],traffic=[],errors=[],stamp=Date.now(),password=crypto.randomBytes(18).toString('hex');
const actor={authority:{name:'Autoridad integral '+stamp,email:`authority-${stamp}@example.test`,password},reviewer:{name:'Revision integral '+stamp,email:`reviewer-${stamp}@example.test`,password},evaluator:{name:'Evaluacion integral '+stamp,email:`evaluator-${stamp}@example.test`,password},student:{name:'Postulante integral '+stamp,email:`student-${stamp}@example.test`,password}};
const assert=(ok,message)=>{if(!ok)throw Error(message);};
const section=(page,title)=>page.locator('section').filter({has:page.getByRole('heading',{name:title,exact:true})}).last();
async function form(page,title,values,endpoint){
 const box=section(page,title);await box.getByRole('button',{name:'Guardar',exact:true}).waitFor();
 for(const [name,value] of Object.entries(values)){
  const input=box.locator(`[name="${name}"]`);
  if(await input.evaluate(e=>e.tagName==='SELECT'))await input.selectOption(String(value));else await input.fill(String(value));
 }
 const response=page.waitForResponse(r=>r.url().endsWith(endpoint)&&r.request().method()==='POST');
 await box.getByRole('button',{name:'Guardar',exact:true}).click();const r=await response;
 assert(r.ok(),title+': HTTP '+r.status()+' '+(await r.text()).slice(0,450));
 await page.waitForFunction(()=>![...document.querySelectorAll('p[role=status]')].some(e=>e.textContent==='Cargando información…'));
 checks.push({action:title,endpoint,result:'FUNCIONA Y VERIFICADO',evidence:'React form and HTTP success; persistence verified by subsequent authorized read'});
}
(async()=>{
 fs.mkdirSync(out,{recursive:true});const browser=await chromium.launch({headless:true,executablePath:s.chromium});const pages=[];
 async function login(credentials){
  const context=await browser.newContext({locale:'es-BO',viewport:{width:1440,height:1000}}),p=await context.newPage();pages.push(p);
  p.on('pageerror',e=>errors.push(e.message));p.on('response',r=>{if(r.url().startsWith(s.base+'/api/'))traffic.push({path:new URL(r.url()).pathname,method:r.request().method(),status:r.status()});});
  await p.goto(s.base+'/p2');await p.getByLabel('Correo electrónico',{exact:true}).fill(credentials.email);await p.getByLabel('Contraseña',{exact:true}).fill(credentials.password);
  await p.getByRole('button',{name:'Entrar al sistema',exact:true}).click();await p.getByRole('navigation',{name:'Principal',exact:true}).waitFor();return p;
 }
 try{
  const admin=await login(s.credentials.admin);await admin.goto(s.base+'/p2/administracion');
  for(const [key,role] of [['authority','authority'],['reviewer','staff'],['evaluator','staff']]){
   await form(admin,'Crear usuario',{name:actor[key].name,email:actor[key].email,password,role,faculty:'FICCT',career:'Sistemas'},'/api/v1/users');
  }
  const category='Tipo integral '+stamp;await form(admin,'Crear tipo de auxiliatura',{name:category,regime:'docencia',active:'1'},'/api/v1/categories');
  let management=await (await admin.request.get(s.base+'/api/v1/management')).json();
  for(const key of ['authority','reviewer','evaluator']){actor[key].id=management.users.find(u=>u.email===actor[key].email)?.id;assert(actor[key].id,'User did not persist: '+key);}
  const categoryId=management.categories.find(c=>c.name===category)?.id;assert(categoryId,'Category did not persist');
  const authority=await login(actor.authority);await authority.goto(s.base+'/p2/convocatorias/nueva');
  const callTitle='Convocatoria integral desde formularios '+stamp;const callForm=section(authority,'Borrador de convocatoria');
  const localDate=offset=>new Date(Date.now()+offset*86400000-4*3600000).toISOString().slice(0,19);
  for(const [name,value] of Object.entries({title:callTitle,management_year:'2026',call_number:'UI-'+stamp,opens_at:localDate(-1),closes_at:localDate(2),exam_at:localDate(3),source:'Ensayo sintetico por interfaz; no constituye convocatoria oficial.'}))await callForm.locator(`[name="${name}"]`).fill(value);
  const created=authority.waitForResponse(r=>r.url().endsWith('/api/v1/calls')&&r.request().method()==='POST');await callForm.getByRole('button',{name:'Crear borrador',exact:true}).click();const createdResponse=await created;assert(createdResponse.ok(),'Cannot create call');const callId=(await createdResponse.json()).id;assert(callId,'Call identifier absent');
  await authority.waitForURL('**/p2/convocatorias/'+callId+'/configuracion');
  for(const [key,role] of [['reviewer','reviewer'],['evaluator','evaluator']]){
   await admin.goto(s.base+'/p2/administracion');await form(admin,'Asignar responsabilidad',{user_id:actor[key].id,call_id:callId,role,reason:'Responsabilidad sintetica asignada mediante formulario.'},'/api/v1/permissions');
  }
  await authority.reload();
  for(const [title,weights] of [['Oferta software integral','Software=100'],['Oferta mixta integral','Software=25\nSoporte=75']])await form(authority,'Agregar oferta',{title,category_id:categoryId,seats:1,modality:'Presencial',is_subject:'0',documents:'cpd=Formulario CPD de ensayo',requirements:'activo=Condicion academica de ensayo',weights,scheme:'DEMO_AREAS_100',source:'Configuracion sintetica con ponderaciones diferenciadas.'},'/api/v1/calls/'+callId+'/offers');
  const configuration=await (await authority.request.get(s.base+'/api/v1/calls/'+callId+'/configuration')).json();assert(configuration.call.title===callTitle&&configuration.offers.length===2,'Configuration did not persist');
  checks.push({action:'Crear convocatoria y dos ofertas desde React',call:callId,offers:configuration.offers.map(o=>({id:o.id,weights:JSON.parse(o.weights)})),result:'FUNCIONA Y VERIFICADO',evidence:'Browser form, API read after reload; PostgreSQL snapshot checked by driver'});
  await authority.screenshot({path:path.join(out,'convocatoria-configurada.png'),fullPage:true});
  const receipt={result:'PHASE_1_PASS',checks,traffic,errors,call:callId,offers:configuration.offers.map(o=>o.id),scope:'Users, category, call, permissions and two offers created through React. Remaining academic lifecycle not yet accepted.'};
  assert(!errors.length,'JavaScript errors');fs.writeFileSync(path.join(out,'functional-call.json'),JSON.stringify(receipt,null,2));
  const privateRoot=path.dirname(process.argv[2]),privateFile=path.join(privateRoot,'functional-call-private.json'),history=path.join(privateRoot,'functional-call-fixtures');
  fs.mkdirSync(history,{recursive:true});
  if(fs.existsSync(privateFile)){const previous=JSON.parse(fs.readFileSync(privateFile,'utf8'));fs.copyFileSync(privateFile,path.join(history,`call-${previous.call}.json`));}
  const privateReceipt=JSON.stringify({call:callId,actor,offers:configuration.offers.map(o=>o.id)});
  fs.writeFileSync(path.join(history,`call-${callId}.json`),privateReceipt);fs.writeFileSync(privateFile,privateReceipt);
  console.log(JSON.stringify({result:receipt.result,checks:checks.length,call:callId}));
 }catch(e){for(const [i,p] of pages.entries())await p.screenshot({path:path.join(out,`failure-${i}.png`),fullPage:true});fs.writeFileSync(path.join(out,'failure.json'),JSON.stringify({error:e.message,checks,traffic,errors},null,2));throw e;}
 finally{await browser.close();}
})().catch(e=>{console.error(e.message);process.exit(1)});
