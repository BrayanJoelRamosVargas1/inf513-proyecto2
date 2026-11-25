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
  return <div className="table-wrap" role="region" aria-label={workspaces[workspace]?.title||'Expedientes visibles'} tabIndex="0">
    {items.length?<table><thead><tr><th>Expediente</th>{!own&&<th>Postulante</th>}<th>Oferta y convocatoria</th><th>Estado</th><th>Habilitación</th>{workspace==='evaluator'&&<th>Mis áreas</th>}<th>Acción</th></tr></thead>
      <tbody>{items.map(a=><tr key={a.id}>
        <td>#{a.id}</td>{!own&&<td>{a.name}</td>}<td>{a.title}{a.call_title&&<small className="workspace-call">{a.call_title}</small>}</td>
        <td>{label(a.status)} · {label(a.participation??'active')}</td><td>{label(a.eligibility)}</td>
        {workspace==='evaluator'&&<td>{(a.assigned_areas||[]).join(', ')||'Sin área asignada'}{a.pending_areas?.length>0&&<small className="workspace-call">Por confirmar: {a.pending_areas.join(', ')}</small>}</td>}
        <td><Link to={'/postulaciones/'+a.id}>{own?(a.status==='draft'?'Completar postulación':'Consultar seguimiento'):workspace==='reviewer'?'Revisar expediente':workspace==='evaluator'?'Abrir evaluación':'Consultar expediente'}</Link></td>
      </tr>)}</tbody></table>:<p>No hay expedientes para este filtro.</p>}
  </div>;
}

export function Applications({embedded=false,workspace='own'}){
  const [d,error]=useData('/applications?workspace='+workspace);
  const [filter,setFilter]=useState('all');
  if(!d)return <Loading error={error}/>;
  const info=workspaces[workspace];
  const pending=a=>a.participation==='active'&&(workspace==='own'?a.status==='draft':workspace==='reviewer'?a.status==='submitted'&&['published','closed'].includes(a.call_status)&&a.eligibility==='pending':workspace==='evaluator'?a.status==='submitted'&&a.eligibility==='enabled'&&a.call_status==='evaluation'&&a.pending_areas?.length>0:false);
  const items=filter==='pending'?d.applications.filter(pending):d.applications;
  return <section className="card" aria-label={info.title}>
    {!embedded&&<><h1>{info.title}</h1><p>{info.description}</p></>}
    <p>{d.applications.length} {workspace==='own'?(d.applications.length===1?'postulación propia':'postulaciones propias'):(d.applications.length===1?'expediente en esta responsabilidad':'expedientes en esta responsabilidad')}.</p>
    {workspace!=='authority'&&<label>Mostrar expedientes<select value={filter} onChange={e=>setFilter(e.target.value)}><option value="all">Todos, incluido el historial</option><option value="pending">{workspace==='own'?'Borradores por completar':workspace==='evaluator'?'Notas por confirmar en evaluación':'Habilitaciones pendientes en recepción'}</option></select></label>}
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
  const titles={student:'Tu camino a la auxiliatura comienza aquí.',admin:'Administración del sistema',authority:'Gestión de convocatorias'};
  const title=titles[session.user.role]||(responsibilities.length===1?workspaces[responsibilities[0]].title:responsibilities.length?'Mis responsabilidades académicas':'Cuenta sin responsabilidad académica');
  const shortcuts=admin?[['Usuarios y permisos','Organiza cuentas y responsabilidades.','/administracion'],['Actividad del portal','Consulta los accesos y las métricas.','/estadisticas']]:student?[['Explorar oportunidades','Consulta las convocatorias disponibles.','/convocatorias'],['Preparar mi expediente','Continúa tus borradores y su seguimiento.','/postulaciones'],['Consultar adjudicaciones','Revisa las decisiones sobre tus plazas.','/adjudicaciones']]:[...(authority?[['Configurar procesos','Revisa tus convocatorias y sus etapas.','/convocatorias']]:[]),...responsibilities.map(r=>[r==='reviewer'?'Revisar documentos':r==='evaluator'?'Registrar mis notas':'Supervisar expedientes',workspaces[r].description,workspaces[r].path])];
  return <>
    <section className="dashboard-hero" aria-label="Bienvenida al portal"><div>
      <p className="eyebrow">Mi espacio de trabajo · {roleName(session)}</p><h1>{title}</h1>
      <p>{admin?'Administra usuarios, accesos y catálogos. El perfil técnico no autoriza a calificar ni a aprobar resultados.':student?'Explora convocatorias, prepara tus documentos y sigue cada postulación.':authority?'Configura tus convocatorias y supervisa las etapas y decisiones de sus responsables.':'Las bandejas siguientes corresponden únicamente a tus responsabilidades vigentes por convocatoria.'}</p>
      <div className="hero-actions"><Link className="button" to={admin?'/administracion':student||authority?'/convocatorias':responsibilities.length?workspaces[responsibilities[0]].path:'/cuenta'}>{admin?'Gestionar accesos':student?'Buscar una convocatoria':authority?'Gestionar convocatorias':responsibilities.length?'Abrir mi bandeja':'Consultar mi cuenta'}</Link><Link className="button secondary" to="/cuenta">Mi cuenta y ayuda</Link></div>
    </div><div className="hero-companion" aria-hidden="true"><span className="hero-seal">F</span><span>Selección de auxiliares</span><strong>Una tarea.<br/>Un siguiente paso.</strong><small>Tu espacio según tus responsabilidades.</small></div></section>
    {shortcuts.length>0&&<nav className="workspace-shortcuts" aria-label="Accesos de mi responsabilidad">{shortcuts.map(([name,description,path],i)=><Link className="shortcut-card" key={path} to={path}><span className="shortcut-index" aria-hidden="true">{String(i+1).padStart(2,'0')}</span><strong>{name}</strong><p>{description}</p><span className="shortcut-arrow" aria-hidden="true">↗</span></Link>)}</nav>}
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
