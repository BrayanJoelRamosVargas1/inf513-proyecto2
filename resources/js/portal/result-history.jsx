import React from 'react';
import {Link} from 'react-router-dom';
import {label} from './api';

const decoded=value=>{if(typeof value!=='string')return value;try{return JSON.parse(value);}catch{return null;}};
const list=value=>{const parsed=decoded(value);return Array.isArray(parsed)?parsed:[];};
const date=value=>{const parsed=new Date(value);return value&&!Number.isNaN(parsed.getTime())?parsed.toLocaleString('es-BO',{timeZone:'America/La_Paz'}):'Fecha no registrada';};

/** Read-only historical evidence already authorized by the ranking endpoint. */
export function ResultHistory({publications=[],revisions=[],oldTies=[],callId}){
  return <>
    <section className="card"><h2>Publicaciones conservadas</h2>
      {publications.map(publication=>{
        const snapshot=decoded(publication.snapshot),selected=Array.isArray(snapshot?.selected)?snapshot.selected:null,ranking=list(snapshot?.ranking);
        return <details key={publication.id}><summary>Publicación v{publication.version} · Revisión {publication.result_revision??'No registrada'}</summary>
          <p>{date(publication.created_at)} · {publication.user_id?'Responsable #'+publication.user_id:'Responsable no registrado'}</p>
          <p>{publication.minutes}</p>
          <p>Seleccionados al aprobar esta publicación. La consulta histórica no sustituye la selección actual.</p>
          {selected?.length>0&&<div className="table-wrap" role="region" aria-label={'Seleccionados históricos de publicación v'+publication.version} tabIndex="0"><table><thead><tr><th>Postulación</th><th>Mérito /40</th><th>Examen /60</th><th>Final /100</th></tr></thead><tbody>{selected.map(id=>{const entry=ranking.find(r=>String(r.id)===String(id));return <tr key={id}><td>#{id}<br/><span>{entry?.name??'Nombre no registrado'}</span></td><td>{entry?.merit??'No registrado'}</td><td>{entry?.exam??'No registrado'}</td><td>{entry?.final??'No registrado'}</td></tr>;})}</tbody></table></div>}
          {selected&&selected.length===0&&<p>Sin postulaciones seleccionadas en esta publicación.</p>}
          {!selected&&<p>Detalle no disponible para esta publicación.</p>}
        </details>;
      })}
      {!publications.length&&<p>Sin publicación aprobada.</p>}
    </section>
    {revisions.map(revision=><section className="card" key={revision.id}><h2>Impacto de revisión {revision.revision}</h2>
      <p>{date(revision.created_at)} · {revision.participation_request_id?'Solicitud de participación #'+revision.participation_request_id:'Rectificación #'+revision.correction_request_id}</p>
      <p>El orden entre notas iguales es visual. Una revisión no reemplaza automáticamente una publicación.</p>
      <div className="table-wrap" role="region" aria-label={'Comparación de revisión '+revision.revision} tabIndex="0"><table><thead><tr><th>Postulación</th><th>Orden anterior</th><th>Orden nuevo</th><th>Final anterior</th><th>Final nuevo</th></tr></thead><tbody>{list(revision.changes).map(change=><tr key={change.application_id}><td>#{change.application_id}</td><td>{change.old_position??'Sin posición'}</td><td>{change.new_position??'Sin posición'}</td><td>{change.old_final??'Sin resultado'}</td><td>{change.new_final??'Sin resultado'}</td></tr>)}</tbody></table></div>
      <Link to={'/convocatorias/'+callId+'/auditoria'}>Consultar motivos y responsables en auditoría →</Link>
    </section>)}
    {oldTies.length>0&&<section className="card"><h2>Desempates de revisiones anteriores</h2><p>Estas decisiones se conservan y no resuelven un empate de la revisión actual.</p>
      {oldTies.map(tie=><details key={tie.id}><summary>Examen #{tie.id} · Revisión {tie.result_revision} · Ronda {tie.round}</summary><p>Estado conservado: {label(tie.status)} · {date(tie.scheduled_at)}</p><p>Comisión registrada: {list(tie.commission).join(', ')||'No registrada'}</p><p>{tie.conditions}</p><ul>{list(tie.participants).map(id=><li key={id}>Postulación #{id}: {decoded(tie.results)?.[id]??'Resultado no registrado'}</li>)}</ul><p>Seleccionados por la comisión en esta ronda: {list(tie.winners).map(id=>'#'+id).join(', ')||'Sin decisión de selección'}</p><p>{tie.minutes??'Acta no registrada'}</p></details>)}
    </section>}
  </>;
}
