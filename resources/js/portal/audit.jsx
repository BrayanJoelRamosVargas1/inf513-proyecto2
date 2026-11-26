import React, { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useData, Loading } from './shared';
const actions = {
  'call.created': 'Convocatoria creada',
  'call.edited': 'Convocatoria modificada',
  'call.transition': 'Cambio de etapa',
  'offer.created': 'Oferta creada',
  'offer.edited': 'Oferta modificada',
  'application.created': 'Postulación registrada',
  'application.submitted': 'Expediente enviado',
  'document.upload': 'Documento cargado',
  'review.recorded': 'Revisión de requisito',
  'eligibility.decided': 'Decisión de habilitación',
  'merit.recorded': 'Méritos registrados',
  'evaluator.assigned': 'Evaluador asignado',
  'assignment.reassigned': 'Evaluador reasignado',
  'grade.confirmed': 'Calificación confirmada',
  'grade.draft_saved': 'Borrador de calificación',
  'grade.corrected': 'Calificación rectificada',
  'result.calculated': 'Resultado calculado',
  'result.approved': 'Publicación aprobada',
  'tie.exam_scheduled': 'Examen de desempate convocado',
  'tie.exam_resolved': 'Decisión de desempate registrada',
  'tie.continued': 'Nueva ronda de desempate',
  'correction.requested': 'Rectificación solicitada',
  'correction.approved': 'Rectificación aprobada',
  'correction.rejected': 'Rectificación rechazada',
  'amendment.opened': 'Subsanación autorizada',
  'amendment.document_uploaded': 'Documento de subsanación cargado',
  'amendment.submitted': 'Subsanación enviada',
  'amendment.reviewed': 'Subsanación revisada',
  'amendment.expired': 'Ventana de subsanación vencida',
  'amendment.cancelled': 'Subsanación cancelada',
  'participation.requested': 'Retiro o renuncia solicitado',
  'participation.approved': 'Solicitud de participación aprobada',
  'participation.rejected': 'Solicitud de participación rechazada',
  'permission.granted': 'Permiso asignado',
  'permission.revoked': 'Permiso revocado',
  'user.profile_changed': 'Perfil modificado',
  'institutional.draft.created': 'Expediente institucional creado',
  'institutional.source.recorded': 'Fuente institucional registrada',
  'institutional.review.requested': 'Revisión institucional solicitada',
  'institutional.review.recorded': 'Revisión institucional registrada'
};

function Snapshot({
  value,
  empty
}) {
  if (value === null || value === undefined) return <p>{empty}</p>;
  let rendered = value;
  if (typeof value === 'string') {
    try {
      rendered = JSON.parse(value);
    } catch {}
  }
  return <pre style={{
    whiteSpace: 'pre-wrap',
    overflowWrap: 'anywhere'
  }}>
    {typeof rendered === 'string' ? rendered : JSON.stringify(rendered, null, 2)}
  </pre>;
}

export function CallAudit() {
  const {
      id
    } = useParams(),
    [page, setPage] = useState(1);
  const [data, error] = useData(`/calls/${id}/audit?page=${page}`);
  if (!data) return <Loading error={error} />;
  const events = data.events;
  return <>
    <Link to={'/convocatorias/' + id}>← Convocatoria</Link>
    <h1>Historial del proceso</h1>
    <p>Operaciones de esta convocatoria con responsable, motivo y valores conservados. Este historial es de consulta y no permite modificar sus registros.</p>
    <section className="card">
      <div className="table-wrap" role="region" aria-label="Eventos de auditoría" tabIndex="0">
        <table>
          <thead>
            <tr>
              <th scope="col">Evento y fecha</th>
              <th scope="col">Responsable</th>
              <th scope="col">Operación</th>
              <th scope="col">Evidencia</th>
            </tr>
          </thead>
          <tbody>
            {events.data.map(event => <tr key={event.id}>
              <td>#{event.id}<br />{new Date(event.created_at).toLocaleString('es-BO', {
                  timeZone: 'America/La_Paz'
                })}</td>
              <td>Cuenta #{event.user_id ?? 'No registrada'}</td>
              <td>{actions[event.action] || 'Operación registrada'}<br />{event.entity}</td>
              <td>
                <details>
                  <summary>Ver detalle del evento #{event.id}</summary>
                  <p>Código de operación: {event.action}</p>
                  <p>{event.reason || 'Sin motivo adicional registrado'}</p>
                  <h2>Antes</h2>
                  <Snapshot value={event.before} empty="Sin valor previo" />
                  <h2>Después</h2>
                  <Snapshot value={event.after} empty="Sin datos adicionales" />
                </details>
              </td>
            </tr>)}
          </tbody>
        </table>
      </div>
      {!events.data.length && <p>No hay eventos registrados para esta convocatoria.</p>}
      <p role="status">Página {events.current_page} de {events.last_page} · {events.total} eventos</p>
      <div className="actions">
        <button type="button" disabled={page <= 1} onClick={() => setPage(n => n - 1)}>Página anterior</button>
        <button
          type="button"
          disabled={page >= events.last_page}
          onClick={() => setPage(n => n + 1)}
        >Página siguiente</button>
      </div>
    </section>
  </>;
}
