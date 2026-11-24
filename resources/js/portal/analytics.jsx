import React,{useContext} from 'react';
import {Link,useSearchParams} from 'react-router-dom';
import {useData,Loading,Form,Session} from './shared';
import {label} from './api';

export function Search(){
  const [params]=useSearchParams();
  const q=params.get('q')||'';
  const page=params.get('page')||'1';
  const [d,error]=useData('/search?q='+encodeURIComponent(q)+'&page='+encodeURIComponent(page));
  const target=n=>'/buscar?q='+encodeURIComponent(q)+'&page='+n;
  return <><h1>Resultados de búsqueda</h1><p>Consulta: {q}</p>{!d?<Loading error={error}/>:<>
    <section className="card">
      <p>{d.pagination.total} coincidencias autorizadas.</p>
      <h2>Convocatorias</h2>{d.calls.data.map(c=><p key={c.id}><Link to={'/convocatorias/'+c.id}>{c.title}</Link></p>)}
      <h2>Ofertas y asignaturas</h2>{d.offers.map(o=><p key={o.id}><Link to={'/convocatorias/'+o.call_id}>{o.title}</Link></p>)}
      <h2>Tipos de auxiliatura</h2>{d.types.map(t=><p key={t.id}>{t.name}</p>)}
      {!d.pagination.total&&<p>No se encontraron coincidencias en tu ámbito.</p>}
    </section>
    {d.pagination.last_page>1&&<nav aria-label="Páginas de resultados">
      <p>Página {d.pagination.page} de {d.pagination.last_page}</p>
      {d.pagination.page>1&&<Link to={target(d.pagination.page-1)}>Página anterior</Link>}
      {d.pagination.page<d.pagination.last_page&&<Link to={target(d.pagination.page+1)}>Página siguiente</Link>}
    </nav>}
  </>}</>;
}

export function Metrics(){
  const context=useContext(Session);
  const session=context?.session;
  const technical=session?.user.role==='admin'&&!session.roles.some(r=>['authority','reviewer','evaluator'].includes(r));
  const gradesVisible=!session||session.user.role==='student'||session.roles.some(r=>['authority','evaluator'].includes(r));
  const awardsVisible=!session||session.user.role==='student'||session.roles.includes('authority');
  const documentsVisible=!session||session.user.role==='student'||session.roles.includes('reviewer')||session.roles.includes('authority');
  const [params,setParams]=useSearchParams();
  const [d,error]=useData('/metrics?'+params.toString());
  if(!d)return <Loading error={error}/>;
  const total=Math.max(1,d.applications);
  const peak=Math.max(1,...d.series.map(day=>Number(day.total)));
  return <><h1>Estadísticas</h1>
    <section className="card"><h2>Filtrar estadísticas</h2>
      <Form button="Aplicar filtros" onSubmit={f=>{
        const next=Object.fromEntries([...f].filter(([,v])=>v!==''));
        setParams(next);
      }}>
        <div className="grid">
          <label>Desde<input type="date" name="from" defaultValue={params.get('from')||''}/></label>
          <label>Hasta<input type="date" name="to" min={params.get('from')||undefined} defaultValue={params.get('to')||''}/></label>
          <label>Convocatoria<select name="call_id" defaultValue={params.get('call_id')||''}>
            <option value="">Todas las visibles</option>{d.call_options.map(c=><option key={c.id} value={c.id}>{c.title}</option>)}
          </select></label>
        </div>
      </Form>
      <p><Link to="/estadisticas">Quitar filtros</Link></p>
      <p>Las fechas filtran postulaciones y accesos. El filtro de convocatoria se aplica a los datos académicos; los accesos corresponden al ámbito de tu cuenta.</p>
    </section>
    {!technical&&<section className="card"><h2>{session?.user.role==='student'&&!session.roles.length?'Seguimiento de mis postulaciones':'Postulaciones de mi responsabilidad'}: {d.applications}</h2>
      <div className="grid">
        <p>Convocatorias visibles: {d.calls}</p>{documentsVisible&&<p>Documentos de estas postulaciones: {d.documents}</p>}
        {gradesVisible&&<p>Notas de examen confirmadas visibles: {d.confirmed_exams}</p>}{awardsVisible&&<p>Adjudicaciones activas de estas postulaciones: {d.active_awards}</p>}
      </div>
      {d.eligibility.map(g=><div className="chart" key={g.eligibility}><label>{label(g.eligibility)}: {g.total}<meter min="0" max={total} value={g.total}>{g.total}</meter></label></div>)}
      {!d.applications&&<p>No hay postulaciones de tu ámbito para este filtro.</p>}
    </section>}
    <section className="card"><h2>Accesos · {d.traffic_scope}</h2>
      {d.registered_users!==null&&<p>Usuarios registrados en la instalación: {d.registered_users}</p>}
      <p>Inicios correctos: {d.login_success} · Fallidos: {d.login_failed}</p>
      <h3>Actividad por fecha</h3><p>Zona horaria: Bolivia. Se muestran hasta 90 fechas recientes.</p>
      {d.series.map(day=><div className="chart" key={day.day}><label>{day.day}: {day.total} eventos<meter min="0" max={peak} value={day.total}>{day.total}</meter></label></div>)}
      {!d.series.length&&<p>No hay actividad registrada para este período.</p>}
      <h3>Páginas más consultadas</h3>{d.popular.map(p=><p key={p.resource}>{p.resource}: {p.total}</p>)}
      {!d.popular.length&&<p>Todavía no hay visitas registradas.</p>}
    </section>
  </>;
}
