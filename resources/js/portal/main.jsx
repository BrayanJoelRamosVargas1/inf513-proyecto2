import {InstitutionalRegister,CpdRecord} from './institutional-register';
import {DocumentaryCall} from './documentary';
import {DocumentLink,DocumentValidation} from './shared';
import React, {useEffect, useState, useContext, useRef} from 'react';
import {createRoot} from 'react-dom/client';
import {BrowserRouter, Routes, Route, Link, useLocation, useNavigate, useParams} from 'react-router-dom';
import {api,base,csrf,message,label} from './api';
import './portal.css';
import './workspace.css';
import {Account,TrainingSamples} from './account';
import {CallAudit} from './audit';
import {Home,Calls,Applications,AppTable,roleName} from './workspaces';
import {InstitutionalIndex,InstitutionalDossier} from './institutional';
import {Search,Metrics} from './analytics';
import {Management,CallConfiguration,CreateCall,Ranking,Corrections,Participation,Awards} from './processes';
import {Session,ErrorBoundary,Alert,useData,Loading,Form,uuid} from './shared';
/* F-14: orden de etapas de una convocatoria; el servidor lo valida (Selection::transition), la interfaz solo ofrece el siguiente paso. */
/* F-21: el título del documento cambia con la pantalla, para que lectores de pantalla y pestañas distingan dónde está la persona. */
const PAGE_TITLES=[[/^\/convocatorias\/nueva/,'Nueva convocatoria'],[/^\/convocatorias\/\d+\/auditoria/,'Auditoría de la convocatoria'],[/^\/convocatorias\/\d+\/configuracion/,'Configuración de la convocatoria'],[/^\/convocatorias\/\d+/,'Convocatoria'],[/^\/convocatorias/,'Convocatorias'],[/^\/postulaciones\/\d+\/rectificaciones/,'Rectificaciones'],[/^\/postulaciones\/\d+\/participacion/,'Participación'],[/^\/postulaciones\/\d+/,'Postulación'],[/^\/postulaciones/,'Postulaciones'],[/^\/ofertas\/\d+\/resultados/,'Resultados de la oferta'],[/^\/revision/,'Revisión'],[/^\/evaluacion/,'Evaluación'],[/^\/expedientes/,'Expedientes'],[/^\/estadisticas/,'Estadísticas'],[/^\/simulacion/,'Simulación de pagos'],[/^\/cuenta/,'Mi cuenta'],[/^\/buscar/,'Búsqueda'],[/^\/administracion/,'Administración'],[/^\/adjudicaciones/,'Adjudicaciones'],[/^\/entrenamiento/,'Materiales de entrenamiento'],[/^\/institucional/,'Registro institucional']];
export function pageTitle(path){const hit=PAGE_TITLES.find(([re])=>re.test(path));return (hit?hit[1]+' · ':'Inicio · ')+'FICCT · Proyecto 2';}
const NEXT_STAGE={draft:'published',published:'closed',closed:'evaluation',evaluation:'results',results:'archived'};
export function Auth(){
  const {setSession}=useContext(Session);
  const [register,setRegister]=useState(false);
  return <div className="auth-page">
    <a className="skip" href="#contenido">Ir al formulario</a>
    <div className="auth-shell">
      <section className="auth-story" aria-label="Presentación del portal">
        <div>
          <div className="sidebar-brand"><span className="brand-emblem">F</span><span className="brand-copy"><strong>FICCT</strong><small>Universidad Autónoma Gabriel René Moreno</small></span></div>
          <p className="eyebrow">Portal de selección de auxiliares</p>
          <h1>Tu postulación.<br/>Cada paso, más claro.</h1>
          <p>Un espacio para consultar convocatorias, organizar tus documentos y acompañar tu proceso de selección.</p>
          <ol className="auth-journey"><li><span>01</span><div><strong>Encuentra tu convocatoria</strong><small>Revisa las plazas, fechas y requisitos.</small></div></li><li><span>02</span><div><strong>Prepara tu expediente</strong><small>Adjunta tus documentos y confirma el envío.</small></div></li><li><span>03</span><div><strong>Sigue tu proceso</strong><small>Consulta observaciones y resultados autorizados.</small></div></li></ol>
        </div>
        <p className="auth-note">Fuentes institucionales reales y procesos de demostración. Las operaciones DEMO no tienen validez oficial.</p>
      </section>
      <main className="auth-stage" id="contenido" tabIndex="-1">
        <section className="auth-card" aria-label={register?'Registro de postulante':'Ingreso al portal'}>
          <p className="eyebrow">Bienvenido al portal</p>
          <h2>{register?'Crea tu cuenta':'Iniciar sesión'}</h2>
          <p className="auth-intro">{register?'Regístrate como postulante para utilizar el entorno de demostración.':'Ingresa con tu cuenta para continuar en tu espacio de trabajo.'}</p>
          <Form button={register?'Crear cuenta de postulante':'Entrar al sistema'} onSubmit={async f=>{
            await csrf();
            const {data}=await api.post(register?'/register':'/login',Object.fromEntries(f));
            api.defaults.headers.common['X-CSRF-TOKEN']=data.token;
            setSession(data);
          }}>
            {register&&<><label>Nombre completo<input name="name" required maxLength="100" autoComplete="name"/></label><label>Facultad<input name="faculty" required maxLength="80"/></label><label>Carrera<input name="career" required maxLength="80"/></label></>}
            <label>Correo electrónico<input type="email" name="email" required maxLength="150" autoComplete="username" placeholder="nombre@correo.com"/></label>
            <label>Contraseña<input name="password" type="password" required minLength={register?12:1} autoComplete={register?'new-password':'current-password'} placeholder="Tu contraseña"/></label>
            {register&&<label>Confirmar contraseña<input name="password_confirmation" type="password" required minLength="12" autoComplete="new-password"/></label>}
          </Form>
          <p className="auth-switch">{register?'¿Ya tienes una cuenta?':'¿Todavía no tienes cuenta?'}</p>
          <button type="button" className="secondary" onClick={()=>setRegister(!register)}>{register?'Volver a ingresar':'Crear cuenta de postulante'}</button>
        </section>
      </main>
    </div>
    <FooterVisitCounter pathOverride={register?'/registro':'/ingresar'} isPublic/>
  </div>;
}
export function FooterVisitCounter({pathOverride,isPublic=false}){
  const {pathname,key:locationKey}=useLocation();
  const path=pathOverride||pathname;
  const receipt=useRef(null);
  const visitId=`${locationKey}:${isPublic}:${path}`;
  if(receipt.current?.id!==visitId)receipt.current={id:visitId,key:uuid()};
  const [total,setTotal]=useState(null),[error,setError]=useState('');
  useEffect(()=>{
    let active=true;
    setTotal(null);setError('');
    const endpoint=isPublic?'/visits/public':'/visits';
    const payload={path,key:receipt.current.key};
    api.post(endpoint,payload)
      .catch(async e=>{
        if(!isPublic||e.response?.status!==419)throw e;
        await csrf();
        if(!active)throw e;
        return api.post(endpoint,payload);
      })
      .then(r=>{if(active)setTotal(r.data.total);})
      .catch(()=>{if(active)setError('Contador temporalmente no disponible');});
    return ()=>{active=false;};
  },[path,isPublic,visitId]);
  return <footer className={isPublic?'auth-footer':''}>
    <span className="footer-legal"><span className="footer-brand">FICCT · Grupo 09</span><span>Proyecto académico · Sin validez institucional</span></span>
    <span aria-live="polite">{error||`Visitas de esta vista: ${total??'…'}`}</span>
  </footer>;
}
function Preferences(){const [d,error,reload]=useData('/me/preferences');const [status,set]=useState('');const [expanded,setExpanded]=useState(false);useEffect(()=>{if(!d)return;const p=d.preferences;document.documentElement.dataset.theme=p.theme;document.documentElement.dataset.mode=p.mode==='auto'?(matchMedia('(prefers-color-scheme: dark)').matches?'noche':'dia'):p.mode;document.documentElement.dataset.font=String(p.font);document.documentElement.dataset.contrast=p.contrast?'alto':'normal';},[d]);if(!d)return <Loading error={error}/>;return <details className="preferences" open={expanded} onToggle={e=>setExpanded(e.currentTarget.open)}><summary>Apariencia y accesibilidad</summary><Form button="Aplicar preferencias" onSubmit={async f=>{const values=Object.fromEntries(f);values.font=Number(values.font);values.contrast=values.contrast==='1';await api.put('/me/preferences',values);reload();set('Preferencias guardadas.');}}><div className="grid"><label>Tema<select name="theme" defaultValue={d.preferences.theme}><option value="ninos">Niños</option><option value="jovenes">Jóvenes</option><option value="adultos">Adultos</option></select></label><label>Modo<select name="mode" defaultValue={d.preferences.mode}><option value="auto">Según el equipo</option><option value="dia">Día</option><option value="noche">Noche</option></select></label><label>Tamaño de letra<select name="font" defaultValue={d.preferences.font}>{[100,125,150,175,200].map(n=><option key={n} value={n}>{n}%</option>)}</select></label><label>Contraste<select name="contrast" defaultValue={d.preferences.contrast?'1':'0'}><option value="0">Normal</option><option value="1">Alto contraste</option></select></label></div><p role="status">{status}</p></Form></details>;}
function NavIcon({path}){
  const type=path==='/'?'home':path.includes('convocatorias')?'calendar':path.includes('postulaciones')?'folder':path.includes('estadisticas')?'chart':path.includes('buscar')?'search':path.includes('cuenta')?'user':path.includes('simulacion')?'flask':path.includes('administracion')?'settings':path.includes('adjudicaciones')?'award':'grid';
  const segments={home:<><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-6v-7h-4v7H4a1 1 0 0 1-1-1z"/></>,calendar:<><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/></>,folder:<><path d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v10H3z"/></>,chart:<><path d="M4 20V10m5 10V4m5 16v-7m5 7V8"/></>,search:<><circle cx="10.5" cy="10.5" r="6"/><path d="m15 15 5 5"/></>,user:<><circle cx="12" cy="8" r="4"/><path d="M4 21c0-5 16-5 16 0"/></>,flask:<><path d="M9 3h6m-5 0v6L4 19a2 2 0 0 0 2 3h12a2 2 0 0 0 2-3L14 9V3M7 16h10"/></>,settings:<><circle cx="12" cy="12" r="3"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3M4.9 4.9 7 7m10 10 2.1 2.1M19.1 4.9 17 7M7 17l-2.1 2.1"/></>,award:<><circle cx="12" cy="8" r="5"/><path d="m8 12-2 9 6-3 6 3-2-9"/></>,grid:<><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></>};
  return <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{segments[type]}</svg>;
}
export function AppShell(){
  const {session,setSession}=useContext(Session);
  const [nav,error]=useData('/me/navigation');
  const [menuOpen,setMenuOpen]=useState(false);
  const [logoutError,setLogoutError]=useState(''),[loggingOut,setLoggingOut]=useState(false);
  const menuRef=useRef(null),menuButtonRef=useRef(null);
  const pageLocation=useLocation();useEffect(()=>{document.title=pageTitle(pageLocation.pathname);},[pageLocation.pathname]);
  useEffect(()=>{
    if(!menuOpen)return;
    const menu=menuRef.current;
    const focusable=()=>[...menu.querySelectorAll('a[href],button:not([disabled])')];
    focusable()[0]?.focus();
    const handle=e=>{
      if(e.key==='Escape'){e.preventDefault();setMenuOpen(false);}
      if(e.key==='Tab'){
        const items=focusable(),first=items[0],last=items.at(-1);
        if(e.shiftKey&&document.activeElement===first){e.preventDefault();last?.focus();}
        else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first?.focus();}
      }
    };
    document.addEventListener('keydown',handle);
    return()=>{document.removeEventListener('keydown',handle);menuButtonRef.current?.focus();};
  },[menuOpen]);
  const navigate=useNavigate(),loc=useLocation();
  useEffect(()=>setMenuOpen(false),[loc.pathname]);
  return <div className="portal-layout">
    <a className="skip" href="#contenido">Ir al contenido principal</a>
    {menuOpen&&<button tabIndex="-1" className="sidebar-scrim" aria-label="Cerrar menú" onClick={()=>setMenuOpen(false)}/>}
    <aside ref={menuRef} id="portal-navigation" className={`sidebar ${menuOpen?'is-open':''}`} aria-label="Navegación lateral">
      <button type="button" className="secondary sidebar-close" onClick={()=>setMenuOpen(false)}>Cerrar menú</button>
      <Link to="/" className="sidebar-brand"><span className="brand-emblem">F</span><span className="brand-copy"><strong>FICCT</strong><small>Selección de auxiliares</small></span></Link>
      <p className="sidebar-section">Menú de navegación</p>
      <nav className="sidebar-nav" aria-label="Principal">
        {nav?.items.map(n=><Link key={n.id} to={n.path} aria-current={loc.pathname===n.path?'page':undefined} onClick={()=>setMenuOpen(false)}><NavIcon path={n.path}/><span>{n.label}</span></Link>)}
      </nav>
      <Alert text={error}/>
      <div className="sidebar-footer"><strong>FICCT · UAGRM</strong><p>Selección de auxiliares</p><p>Proyecto académico · Grupo 09</p></div>
    </aside>
    <div className="shell">
      <header className="topbar">
        <div className="topbar-leading">
          <button ref={menuButtonRef} aria-controls="portal-navigation" className="secondary mobile-nav-button" type="button" aria-label="Abrir menú" aria-expanded={menuOpen} onClick={()=>setMenuOpen(true)}><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
          <form className="topbar-search" role="search" onSubmit={e=>{e.preventDefault();const q=new FormData(e.currentTarget).get('q');navigate('/buscar?q='+encodeURIComponent(q));}}>
            <label className="sr-only" htmlFor="global-search">Buscar convocatorias y ofertas</label>
            <span className="search-symbol" aria-hidden="true"><NavIcon path="/buscar"/></span><input id="global-search" name="q" placeholder="Buscar convocatorias y ofertas…" minLength="2" maxLength="80" required/>
            <button type="submit">Buscar</button>
          </form>
        </div>
        <div className="topbar-account">
          <span className="user-avatar" aria-hidden="true">{session.user.name.trim().slice(0,1).toUpperCase()}</span>
          <span className="user-meta"><strong>{session.user.name}</strong><small>{roleName(session)}</small></span>
          <button type="button" className="secondary" disabled={loggingOut} onClick={async()=>{if(loggingOut)return;setLogoutError('');setLoggingOut(true);try{await api.post('/logout');setSession(null);}catch(e){setLogoutError(message(e));}finally{setLoggingOut(false);}}}>{loggingOut?'Cerrando sesión…':'Salir'}</button>
        </div>
      </header>
      <main className="page-content" id="contenido" tabIndex="-1">
        <div className="demo-banner"><span className="demo-dot" aria-hidden="true"/><span><strong>{session.training_enabled?'Entrenamiento y archivo documental.':'Preparación institucional.'}</strong> {session.training_enabled?'Los procesos ficticios están identificados como DEMO.':'Los procesos oficiales requieren autorización institucional.'}</span></div>
        <Alert text={logoutError}/>
        <div className="page-toolbar"><nav aria-label="Ruta actual"><Link to="/">Mi espacio</Link>{loc.pathname!=='/'&&<><span aria-hidden="true">/</span><span aria-current="page">{nav?.items.find(n=>n.path!=='/'&&(loc.pathname===n.path||loc.pathname.startsWith(n.path+'/')))?.label||'Detalle del proceso'}</span></>}</nav><Preferences/></div>
        <Routes><Route path="/entrenamiento" element={<TrainingSamples/>}/><Route path="/institucional" element={<InstitutionalIndex/>}/><Route path="/institucional/:id" element={<InstitutionalDossier/>}/><Route path="/" element={<Home/>}/><Route path="/convocatorias" element={<Calls/>}/><Route path="/convocatorias/:id" element={<Call/>}/><Route path="/postulaciones" element={<Applications workspace="own"/>}/><Route path="/revision" element={<Applications workspace="reviewer"/>}/><Route path="/evaluaciones" element={<Applications workspace="evaluator"/>}/><Route path="/expedientes" element={<Applications workspace="authority"/>}/><Route path="/postulaciones/:id" element={<Application/>}/><Route path="/estadisticas" element={<Metrics/>}/><Route path="/buscar" element={<Search/>}/><Route path="/simulacion" element={<Sandbox/>}/><Route path="/cuenta" element={<Account/>}/><Route path="/administracion" element={<Management/>}/><Route path="/adjudicaciones" element={<Awards/>}/><Route path="/convocatorias/:id/auditoria" element={<CallAudit/>}/><Route path="/convocatorias/:id/configuracion" element={<CallConfiguration/>}/><Route path="/convocatorias/nueva" element={<CreateCall/>}/><Route path="/ofertas/:id/resultados" element={<Ranking/>}/><Route path="/postulaciones/:id/rectificaciones" element={<Corrections/>}/><Route path="/postulaciones/:id/participacion" element={<Participation/>}/><Route path="*" element={<p>Página no encontrada. <Link to="/">Volver</Link></p>}/></Routes>
      </main>
      <FooterVisitCounter/>
    </div>
  </div>;
}
export function Call(){const {id}=useParams(),navigate=useNavigate();const {session}=useContext(Session);const [d,error,reload]=useData('/calls/'+id);const [err,set]=useState('');if(!d)return <Loading error={error}/>;return <><Link to="/convocatorias">← Convocatorias</Link><h1>{d.call.title}</h1><p>{label(d.call.status)} · {d.call.mode}</p><p>{d.call.source}</p><Alert text={err}/>{d.documentary&&<DocumentaryCall data={d.documentary}/>}{d.authority&&<section className="card"><h2>Control del proceso</h2>{d.call.mode==='DEMO'&&NEXT_STAGE[d.call.status]&&<Form button={'Pasar a '+label(NEXT_STAGE[d.call.status])} onSubmit={async()=>{await api.post('/calls/'+id+'/transition',{status:NEXT_STAGE[d.call.status]});reload();}}/>}<p><Link to={`/convocatorias/${id}/configuracion`}>Configurar convocatoria y responsables →</Link></p><p><Link to={`/convocatorias/${id}/auditoria`}>Consultar historial del proceso →</Link></p><p><a href={`${base}/api/v1/calls/${id}/reports/pdf`}>Descargar consolidado PDF</a> · <a href={`${base}/api/v1/calls/${id}/reports/xlsx`}>Descargar consolidado Excel</a></p></section>}<div className="grid">{d.offers.filter(o=>!d.documentary||!o.is_subject).map(o=><article className="card" key={o.id}><span className="badge">{o.seats===null?'Plazas no especificadas':`${o.seats} ${o.seats===1?'plaza':'plazas'}`}</span><h2>{o.title}</h2><p>{Object.entries(o.weights).map(([k,v])=>`${k}: ${v}%`).join(' · ')}</p>{d.documentary?<p>Consulta los documentos, requisitos y calendario de esta modalidad en los apartados de la fuente original.</p>:<details><summary>Documentos y requisitos</summary><ul>{Object.values(o.documents).concat(Object.values(o.requirements)).map((t,i)=><li key={i}>{t}</li>)}</ul></details>}{d.authority&&<p><Link to={`/ofertas/${o.id}/resultados`}>Clasificación y publicación →</Link></p>}{session.user.role==='student'&&(o.own_application?<Link className="button" to={'/postulaciones/'+o.own_application}>Abrir mi postulación →</Link>:o.application_block?<p>{o.application_block}</p>:<button onClick={async()=>{try{const r=await api.post('/offers/'+o.id+'/applications');navigate('/postulaciones/'+r.data.id);}catch(e){set(message(e));}}}>Iniciar postulación</button>)}</article>)}</div>{d.authority&&<InstitutionalRegister callId={id}/>}{!d.documentary&&<section className="card"><h2>Expedientes visibles</h2><AppTable items={d.applications}/></section>}</>;}
export function Application(){const {id}=useParams();const [d,error,reload]=useData('/applications/'+id);if(!d)return <Loading error={error}/>;const a=d.application;const missing=Object.keys(d.offer.documents).filter(k=>!d.documents.some(doc=>doc.kind===k));const submit=async(action,data)=>{await api.post(`/applications/${id}/${action}`,data);reload();};return <><Link to={'/convocatorias/'+d.call.id}>← {d.call.title}</Link><p className="eyebrow">{d.owner?'Mi postulación':d.reviewer?'Revisión de expediente':d.authority?'Seguimiento de resultados':'Evaluación asignada'}</p><h1>{d.offer.title}</h1><p>Expediente #{id} · {d.student.name} · {label(a.status)} · {label(a.eligibility)}</p><p>{label(a.participation)}</p><DocumentValidation validation={d.document_validation}/>{(d.owner||d.authority)&&<div className="grid"><section className="card">Méritos /40: {a.merit??'Pendiente'}</section><section className="card">Examen /60: {a.exam??'Pendiente'}</section><section className="card">Final /100: {a.final??'Pendiente'}</section></div>}{(d.owner||d.reviewer)&&<section className="card"><h2>Expediente digital</h2>{a.status==='submitted'&&<p><a href={`${base}/api/v1/applications/${id}/receipt`}>Descargar constancia interna de recepción</a></p>}<p>{d.owner?'Carga los documentos requeridos y confirma el envío cuando estén completos. El análisis de seguridad puede continuar después del envío; espera su validación para abrir los archivos.':'Abre los documentos disponibles y contrástalos con cada requisito. Registra las observaciones y su fundamento; el análisis de seguridad no sustituye la revisión académica.'}</p><button type="button" onClick={reload}>Actualizar estado de archivos</button><ul>{Object.entries(d.offer.documents).map(([k,title])=>{const doc=d.documents.find(v=>v.kind===k);return <li key={k}>{title}: {doc?<><DocumentLink doc={doc}/></>:'Falta adjuntar'}</li>;})}</ul>{d.can_upload&&<><Form button="Guardar archivo" onSubmit={f=>submit('archivo',f)}><label>Documento<select name="kind">{Object.entries(d.offer.documents).map(([k,t])=><option key={k} value={k}>{t}</option>)}</select></label><label>Archivo PDF, JPG o PNG · hasta 5 MB<input name="file" type="file" accept=".pdf,.png,.jpg,.jpeg" required/></label></Form>{missing.length?<p>Faltan {missing.length} documentos. Completa la carga para enviar.</p>:<Form button="Confirmar envío definitivo" onSubmit={()=>submit('enviar',{})}><p>Al enviar, el expediente queda conservado para revisión.</p></Form>}</>}</section>}{d.criteria&&Object.keys(d.criteria).length>0&&<section className="card"><h2>Revisión y habilitación</h2>{a.eligibility_reason&&<p>{a.eligibility_reason}</p>}{d.can_review?<><Form button="Guardar revisión por criterio" onSubmit={f=>{const values=Object.fromEntries(f);const decisions=Object.fromEntries(Object.keys(d.criteria).map(k=>[k,values[k]]));return submit('revision',{decisions,reason:values.reason});}}>{Object.entries(d.criteria).map(([k,t])=><label key={k}>{t}<select name={k} defaultValue={d.reviews.find(x=>x.criterion===k)?.decision??'observed'}><option value="observed">Observado</option><option value="meets">Cumple</option><option value="fails">No cumple</option>{d.conditional_criteria?.includes(k)&&<option value="not_applicable">No aplica: requiere fundamento</option>}</select></label>)}<label>Motivo y evidencia<textarea name="reason" minLength="10" maxLength="3000" required/></label></Form>{d.can_enable===false&&<p>Para habilitar, guarda los criterios obligatorios como Cumple, fundamenta los condicionales que no apliquen y espera una validación documental vigente. Puedes registrar una inhabilitación motivada cuando corresponda.</p>}<Form button="Confirmar habilitación" onSubmit={f=>submit('habilitacion',Object.fromEntries(f))}><label>Decisión<select name="enabled" required defaultValue=""><option value="" disabled>Seleccionar decisión</option><option value="1" disabled={d.can_enable===false}>Habilitar</option><option value="0">Inhabilitar</option></select></label><label>Motivo<textarea name="reason" minLength="10" maxLength="3000" required/></label></Form></>:<div>{d.reviews.length>0&&<h3>Observaciones y fundamentos de revisión</h3>}<ul>{Object.entries(d.criteria).map(([k,t])=>{const review=d.reviews.find(x=>x.criterion===k);return <li key={k}><strong>{t}: {label(review?.decision??'pending')}</strong>{review&&<><p>{review.reason}</p><small>Revisado por cuenta #{review.user_id} · {review.updated_at}</small></>}</li>;})}</ul></div>}</section>}{d.can_review&&d.reviews.length>0&&<section className="card"><h2>Observaciones y fundamentos de revisión</h2><ul>{d.reviews.map(r=><li key={r.criterion}><strong>{d.criteria[r.criterion]??r.criterion}: {label(r.decision)}</strong><p>{r.reason}</p><small>Revisado por cuenta #{r.user_id} · {r.updated_at}</small></li>)}</ul></section>}<CpdRecord data={d} reload={reload}/>{d.reviewer&&!d.owner&&a.merit!=null&&<section className="card"><h2>Méritos CPD registrados</h2><p>{a.merit} / 40 puntos. Un cambio requiere rectificación autorizada.</p></section>}{d.can_merit&&<section className="card"><h2>Registro de méritos CPD</h2><Form button="Confirmar méritos" onSubmit={f=>submit('meritos',Object.fromEntries(f))}><label>Referencia de respaldo<input name="reference" required maxLength="150"/></label><label>Mérito certificado sobre 40<input name="score" type="number" min="0" max="40" step="0.01" required/></label></Form></section>}{d.can_grade&&d.assignments.map(as=>{const grade=d.grades.find(g=>g.assignment_id===as.id);if(grade&&grade.status!=='draft')return null;return <section className="card" key={as.id}><h2>Mi evaluación: {as.area}</h2><Form button="Registrar nota" onSubmit={f=>submit('nota',{...Object.fromEntries(f),assignment_id:as.id})}><label>Nota sobre 100<input type="number" name="score" min="0" max="100" step="0.01" required defaultValue={grade?.score??''}/></label><label>Acción<select name="intent"><option value="draft">Guardar borrador</option><option value="confirm">Confirmar nota</option></select></label></Form></section>;})}{d.grades.length>0&&<section className="card"><h2>Calificaciones visibles</h2><ul>{d.grades.map(g=><li key={g.id}>{g.area}: {g.score}/100 · {label(g.status)}{g.user_id&&<small> · Registrada por cuenta #{g.user_id}. La reasignación conserva la autoría original.</small>}</li>)}</ul></section>}{d.can_calculate&&<section className="card"><Form button="Calcular resultado" onSubmit={()=>submit('calcular',{})}><p>Se requieren las notas confirmadas de todas las áreas. Las faltantes no equivalen a cero.</p></Form></section>}{!d.owner&&!d.reviewer&&!d.authority&&d.assignments.length>0&&d.call.status!=='evaluation'&&<section className="card"><h2>Disponibilidad de mi evaluación</h2><p>El registro de notas se habilita durante la etapa de evaluación, para postulantes habilitados y áreas asignadas. Etapa actual: {label(d.call.status)}.</p></section>}<section className="card"><h2>Seguimiento e historial</h2>{(d.owner||d.reviewer||d.authority)&&<p><Link to={`/postulaciones/${id}/participacion`}>Subsanaciones, retiro o renuncia →</Link></p>}{(d.can_view_corrections??(d.reviewer||d.authority||(!d.owner&&d.assignments.length>0&&d.grades.length>0)))&&<p><Link to={`/postulaciones/${id}/rectificaciones`}>Rectificaciones e historial de calificaciones →</Link></p>}</section></>;}
export function Sandbox(){const [d,error,reload]=useData('/sandbox');const [actionError,setActionError]=useState(''),[deactivating,setDeactivating]=useState(null);const [key,setKey]=useState(()=>uuid());if(!d)return <Loading error={error}/>;const methods=d.methods.filter(m=>m.active);return <><h1>Laboratorio de pagos simulados</h1><Alert text={actionError}/><div className="notice">Simulación académica — no se procesa dinero real. No condiciona postulación, examen ni plaza. No introduzcas tarjetas ni datos bancarios.</div><section className="card"><h2>Métodos ficticios</h2><Form button="Registrar método de práctica" onSubmit={async f=>{await api.post('/sandbox/methods',Object.fromEntries(f));reload();}}><label>Método<select name="label"><option>Método de práctica A</option><option>Método de práctica B</option></select></label></Form>{methods.map(m=><p key={m.id}>{m.label} <button type="button" className="secondary" disabled={deactivating!==null} onClick={async()=>{if(deactivating!==null)return;setActionError('');setDeactivating(m.id);try{await api.delete('/sandbox/methods/'+m.id);reload();}catch(e){setActionError(message(e));}finally{setDeactivating(null);}}}>{deactivating===m.id?'Desactivando…':`Desactivar #${m.id}`}</button></p>)}</section>{methods.length>0&&<section className="card"><h2>Pago único de prueba</h2><Form button="Procesar simulación" onSubmit={async f=>{await api.post('/sandbox/payments',{...Object.fromEntries(f),key});setKey(uuid());reload();}}><label>Método<select name="method_id">{methods.map(m=><option key={m.id} value={m.id}>{m.label} #{m.id}</option>)}</select></label><label>Importe ficticio (Bs)<input name="amount" type="number" min="0.01" max="99999" step="0.01" required/></label><label>Resultado a simular<select name="outcome"><option value="paid">Éxito</option><option value="failed">Fallo</option></select></label><label className="check"><input name="confirmed" type="checkbox" value="1" required/>Confirmo que es una simulación sin dinero real</label></Form></section>}<section className="card"><h2>Plan de cuotas ficticias</h2><Form button="Crear plan" onSubmit={async f=>{await api.post('/sandbox/plans',{...Object.fromEntries(f),description:'Plan de práctica',key});setKey(uuid());reload();}}><label>Total ficticio (Bs)<input name="total" type="number" min="0.02" max="99999" step="0.01" required/></label><label>Número de cuotas<input name="count" type="number" min="2" max="12" required/></label></Form>{d.plans.map(p=><article key={p.id}><h3>Plan #{p.id} · Total {p.total} Bs ficticios</h3>{d.installments.filter(i=>i.plan_id===p.id).map(i=>{const paid=d.payments.some(pay=>pay.installment_id===i.id&&pay.status==='paid');return <p key={i.id}>Cuota {i.number} · {i.amount} · Vence {i.due_at} · {paid?'Pagada en simulación':<button disabled={!methods.length} onClick={async()=>{try{await api.post('/sandbox/payments',{method_id:methods[0].id,amount:i.amount,installment_id:i.id,outcome:'paid',key:uuid(),confirmed:true});reload();}catch(e){setActionError(message(e));}}}>Simular pago de cuota</button>}</p>;})}</article>)}</section><section className="card"><h2>Resumen de la simulación</h2><p>Éxitos registrados: {d.summary.paid} Bs ficticios · Intentos fallidos: {d.summary.failed} Bs ficticios · Cuotas pendientes: {d.summary.pending_installments} Bs ficticios</p><a href={`${base}/api/v1/sandbox/report`}>Descargar mi reporte CSV de simulación</a></section><section className="card"><h2>Historial de transacciones</h2><ul>{d.payments.map(p=><li key={p.id}>#{p.id} · {p.amount} Bs ficticios · {label(p.status)} · {p.created_at}</li>)}</ul>{!d.payments.length&&<p>Sin transacciones.</p>}</section></>;}
function Root(){const [session,setSession]=useState(null),[loading,setLoading]=useState(true),[bootError,setBootError]=useState('');useEffect(()=>{csrf().then(()=>api.get('/me')).then(r=>{api.defaults.headers.common['X-CSRF-TOKEN']=r.data.token;setSession(r.data);}).catch(e=>{if(!e.response||e.response.status>=500)setBootError('No se pudo conectar con el servidor. Compruebe su conexión e intente de nuevo en unos minutos.');}).finally(()=>setLoading(false));const interceptor=api.interceptors.response.use(r=>r,e=>{if(e.response?.status===401)setSession(null);return Promise.reject(e);});return()=>api.interceptors.response.eject(interceptor);},[]);return <Session.Provider value={{session,setSession}}>{loading?<main><p role="status">Comprobando sesión…</p></main>:session?<AppShell/>:<><Alert text={bootError}/><Auth/></>}</Session.Provider>;}
if(document.getElementById('root')) createRoot(document.getElementById('root')).render(<ErrorBoundary><BrowserRouter basename={`${base}/p2`}><Root/></BrowserRouter></ErrorBoundary>);
