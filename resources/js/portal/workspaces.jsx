import React,{useContext,useState} from 'react';
import {Link} from 'react-router-dom';
import {Session,useData,Loading} from './shared';
import {label} from './api';

export const workspaces={
  own:{title:'Mis postulaciones',path:'/postulaciones',description:'Prepara tu expediente, confirma el envío y consulta el seguimiento de tus postulaciones.'},
  reviewer:{title:'Revisión de expedientes',path:'/revision',description:'Coteja documentos y requisitos, atiende subsanaciones y registra habilitación y méritos CPD.'},
  evaluator:{title:'Mis evaluaciones',path:'/evaluaciones',description:'Consulta tus áreas asignadas y registra sus notas. Los documentos privados y su revisión corresponden al revisor.'},
  authority:{title:'Seguimiento de expedientes',path:'/expedientes',description:'Supervisa habilitaciones y resultados; gestiona solicitudes y conduce la publicación con acta.'},
};

export function roleName(session){
  const names=[];
  if(session.user.role==='admin')names.push('Administrador técnico');
  if(session.user.role==='student')names.push('Postulante');
  if(session.user.role==='authority'||session.roles.includes('authority'))names.push('Autoridad');
  if(session.roles.includes('reviewer'))names.push('Revisor');
  if(session.roles.includes('evaluator'))names.push('Evaluador');
  return names.join(' · ')||'Sin responsabilidad asignada';
}

export function AppTable({items,workspace}){
  const own=workspace==='own';
  const correctionActions={correct_documents:'Subsanar documentos',review_amendment:'Revisar subsanación',close_expired_amendment:'Resolver plazo vencido'};
  return <div className="table-wrap" role="region" aria-label={workspaces[workspace]?.title||'Expedientes visibles'} tabIndex="0">
    {items.length?<table><thead><tr><th>Expediente</th>{!own&&<th>Postulante</th>}<th>Oferta y convocatoria</th><th>Estado</th><th>Habilitación</th>{workspace==='evaluator'&&<th>Mis áreas</th>}<th>Acción</th></tr></thead>
      <tbody>{items.map(a=><tr key={a.id}>
        <td data-label="Expediente">#{a.id}</td>{!own&&<td data-label="Postulante">{a.name}</td>}<td data-label="Oferta">{a.title}{a.call_title&&<small className="workspace-call">{a.call_title}</small>}</td>
        <td data-label="Estado">{label(a.status)} · {label(a.participation??'active')}</td><td data-label="Habilitación">{label(a.eligibility)}</td>
        {workspace==='evaluator'&&<td data-label="Mis áreas">{(a.assigned_areas||[]).join(', ')||'Sin área asignada'}{a.pending_areas?.length>0&&<small className="workspace-call">Por confirmar: {a.pending_areas.join(', ')}</small>}</td>}
        <td data-label="Acción"><Link to={'/postulaciones/'+a.id+(correctionActions[a.pending_action]?'/participacion':'')}>{correctionActions[a.pending_action]||(own?(a.status==='draft'?'Completar postulación':'Consultar seguimiento'):workspace==='reviewer'?'Revisar expediente':workspace==='evaluator'?'Abrir evaluación':'Consultar expediente')}</Link></td>
      </tr>)}</tbody></table>:<p>No hay expedientes para este filtro.</p>}
  </div>;
}

export function Applications({embedded=false,workspace='own'}){
  const [d,error]=useData('/applications?workspace='+workspace);
  const [filter,setFilter]=useState('all');
  if(!d)return <Loading error={error}/>;
  const info=workspaces[workspace];
  const pending=a=>a.participation==='active'&&(workspace==='own'||workspace==='reviewer'?Boolean(a.pending_action):workspace==='evaluator'?a.status==='submitted'&&a.eligibility==='enabled'&&a.call_status==='evaluation'&&a.pending_areas?.length>0:false);
  const items=filter==='pending'?d.applications.filter(pending):d.applications;
  return <section className="card workspace-list" aria-label={info.title}>
    {!embedded&&<><h1>{info.title}</h1><p>{info.description}</p></>}
    <div className="workspace-list-toolbar"><p>{d.applications.length} {workspace==='own'?(d.applications.length===1?'postulación propia':'postulaciones propias'):(d.applications.length===1?'expediente en esta responsabilidad':'expedientes en esta responsabilidad')}.</p>
    {workspace!=='authority'&&<label>Mostrar expedientes<select value={filter} onChange={e=>setFilter(e.target.value)}><option value="all">Todos, incluido el historial</option><option value="pending">{workspace==='own'?'Borradores y subsanaciones por completar':workspace==='evaluator'?'Notas por confirmar en evaluación':'Revisiones y plazos pendientes'}</option></select></label>}</div>
    <AppTable items={embedded?items.slice(0,5):items} workspace={workspace}/>
    {embedded&&items.length>5&&<p><Link to={info.path}>Ver los {items.length} expedientes</Link></p>}
  </section>;
}

export function Calls({embedded=false}){
  const {session}=useContext(Session);
  const [d,error]=useData('/calls');
  const [filter,setFilter]=useState('all');
  if(!d)return <Loading error={error}/>;
  const receiving=c=>c.mode==='DEMO'&&c.status==='published'&&Date.now()>=Date.parse(c.opens_at)&&Date.now()<=Date.parse(c.closes_at);
  const calls=session.user.role==='student'?[...d.calls].sort((a,b)=>Number(receiving(b))-Number(receiving(a))):d.calls;
  const visible=calls.filter(c=>filter==='all'||(filter==='open'?receiving(c):c.mode==='HISTORICAL'));
  return <section className="calls-section">{!embedded&&<div className="page-intro"><div><p className="eyebrow">Explora las oportunidades</p><h1>Convocatorias</h1><p>Encuentra las fechas y requisitos de cada proceso. Las referencias históricas se conservan para consulta.</p></div>{session.user.role==='authority'&&<Link className="button" to="/convocatorias/nueva">Crear convocatoria DEMO</Link>}</div>}
    {!embedded&&<div className="filter-tabs" role="group" aria-label="Filtrar convocatorias">{[['all','Todas'],['open','Recepción vigente'],['historical','Históricas']].map(([value,text])=>{const count=calls.filter(c=>value==='all'||(value==='open'?receiving(c):c.mode==='HISTORICAL')).length;return <button key={value} type="button" aria-label={`${text}: ${count}`} aria-pressed={filter===value} onClick={()=>setFilter(value)}><span className="filter-label">{value==='open'?'Vigentes':text}</span><span className="filter-count">{count}</span></button>;})}</div>}
    <div className="grid call-cards">{(embedded?visible.slice(0,3):visible).map(c=><article className="card call-card" key={c.id}>
      <div className="call-card-top"><span className="call-mark" aria-hidden="true">{c.mode==='HISTORICAL'?'H':'C'}</span><span className="badge">{c.status==='published'?(receiving(c)?'Recepción vigente':'Recepción fuera de plazo'):label(c.status)} · {c.mode}</span></div>
      <p className="eyebrow">{c.mode==='HISTORICAL'?'Referencia histórica':'Proceso de demostración'}</p><h2><Link to={'/convocatorias/'+c.id}>{c.title}</Link></h2>
      <dl className="call-dates"><div><dt>Apertura de recepción</dt><dd>{c.mode==='HISTORICAL'?'No especificada en la fuente':new Date(c.opens_at).toLocaleString('es-BO')}</dd></div><div><dt>Cierre de recepción</dt><dd>{new Date(c.closes_at).toLocaleString('es-BO')}</dd></div></dl>
      <div className="call-card-bottom"><span>{label(c.scope)} {c.scope_value}</span><Link className="call-detail-link" to={'/convocatorias/'+c.id}>Ver detalle <span aria-hidden="true">↗</span></Link></div>
    </article>)}</div>{!d.calls.length?<p className="empty-state">No hay convocatorias visibles en tu ámbito.</p>:!visible.length&&<p className="empty-state">No hay convocatorias que coincidan con este filtro.</p>}
  </section>;
}

export function Home(){
  const {session}=useContext(Session);
  const student=session.user.role==='student',admin=session.user.role==='admin';
  const authority=session.user.role==='authority'||session.roles.includes('authority');
  const responsibilities=['reviewer','evaluator','authority'].filter(r=>session.roles.includes(r));
  const titles={student:'Mi panel de postulación',admin:'Administración del sistema',authority:'Gestión de convocatorias'};
  const title=titles[session.user.role]||(responsibilities.length===1?workspaces[responsibilities[0]].title:responsibilities.length?'Mis responsabilidades académicas':'Cuenta sin responsabilidad académica');
  const shortcuts=admin?[['Usuarios y permisos','Organiza cuentas y responsabilidades.','/administracion'],['Actividad del portal','Consulta los accesos y las métricas.','/estadisticas']]:student?[['Explorar oportunidades','Consulta las convocatorias disponibles.','/convocatorias'],['Preparar mi expediente','Continúa tus borradores y su seguimiento.','/postulaciones'],['Consultar adjudicaciones','Revisa las decisiones sobre tus plazas.','/adjudicaciones']]:[...(authority?[['Configurar procesos','Revisa tus convocatorias y sus etapas.','/convocatorias']]:[]),...responsibilities.map(r=>[r==='reviewer'?'Revisar documentos':r==='evaluator'?'Registrar mis notas':'Supervisar expedientes',workspaces[r].description,workspaces[r].path])];
  return <>
    <section className="workspace-heading" aria-label="Bienvenida al portal"><div>
      <p className="eyebrow">{roleName(session)}</p><h1>{title}</h1>
      <p>{admin?'Administra usuarios, accesos y catálogos. El perfil técnico no autoriza a calificar ni a aprobar resultados.':student?'Explora convocatorias, prepara tus documentos y sigue cada postulación.':authority?'Configura tus convocatorias y supervisa las etapas y decisiones de sus responsables.':'Las bandejas siguientes corresponden únicamente a tus responsabilidades vigentes por convocatoria.'}</p>
    </div><Link className="button workspace-primary" to={admin?'/administracion':student||authority?'/convocatorias':responsibilities.length?workspaces[responsibilities[0]].path:'/cuenta'}><span aria-hidden="true">＋</span>{admin?'Gestionar accesos':student?'Ver convocatorias':authority?'Gestionar convocatorias':responsibilities.length?'Abrir mi bandeja':'Consultar mi cuenta'}</Link></section>
    {shortcuts.length>0&&<nav className="workspace-shortcuts" aria-label="Accesos de mi responsabilidad">{shortcuts.map(([name,description,path],i)=><Link className="shortcut-card" key={path} to={path}><span className="shortcut-index" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round">{i===0?<><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4m8-4v4M4 10h16"/></>:i===1?<><path d="M4 7h6l2 2h8v11H4zM4 7V5h6l2 2"/><path d="m9 14 2 2 4-4"/></>:<><circle cx="12" cy="9" r="5"/><path d="m9 14-1 7 4-2 4 2-1-7"/></>}</svg></span><span className="shortcut-copy"><strong>{name}</strong><span>{description}</span></span><span className="shortcut-arrow" aria-hidden="true">→</span></Link>)}</nav>}
    {admin&&<section className="card"><h2>Control técnico de la instalación</h2><p>Gestiona usuarios y responsabilidades desde Administración.</p><Link to="/estadisticas">Consultar estadísticas de acceso →</Link></section>}
    {student&&<><div className="dashboard-section-title"><h2>Mis postulaciones</h2><Link to="/postulaciones">Ver seguimiento</Link></div><Applications workspace="own" embedded/></>}
    {responsibilities.map(role=><section key={role}>
      <div className="dashboard-section-title"><h2>{workspaces[role].title}</h2><Link to={workspaces[role].path}>Abrir bandeja</Link></div>
      <p>{workspaces[role].description}</p><Applications workspace={role} embedded/>
    </section>)}
    {(student||authority)&&<><div className="dashboard-section-title"><h2>{student?'Convocatorias para postular y consultar':'Convocatorias a mi cargo'}</h2><Link to="/convocatorias">Ver convocatorias</Link></div><Calls embedded/></>}
    {!student&&!admin&&!authority&&!responsibilities.length&&<section className="card"><h2>Sin tareas asignadas</h2><p>Tu cuenta está activa, pero no tiene una responsabilidad académica vigente. Solicita al administrador la asignación correspondiente; no puedes revisar ni calificar por el solo perfil de personal.</p></section>}
  </>;
}
