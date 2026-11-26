import React, { useContext, useState } from 'react';
import { api, label } from './api';
import { Action, Field, evidence, reason, select } from './form-kit';
import { Form, Loading, Session, useData } from './shared';
import { Link, useParams } from 'react-router-dom';

export function CorrectionRequest({
  id,
  options,
  after
}) {
  const [grade, setGrade] = useState(String(options[0]?.[0] ?? ''));
  return <section className="card">
    <h2>Solicitar rectificación</h2>
    <Form onSubmit={async f => {
      const values = Object.fromEntries(f);
      if (values.grade_id === '') delete values.grade_id;
      await api.post(`/applications/${id}/corrections`, values);
      after();
    }}>
      <label>Valor a corregir<select name="grade_id" value={grade} onChange={e => setGrade(e.target.value)}>
          {options.map(([key, title]) => <option key={key} value={key}>{title}</option>)}
        </select></label>
      <label>Nuevo puntaje<input
          name="score"
          type="number"
          min="0"
          max={grade === '' ? 40 : 100}
          step="0.01"
          required
        /></label>
      <p>Máximo para este valor: {grade === '' ? 40 : 100} puntos.</p>
      <Field {...reason} />
      <Field {...evidence} />
    </Form>
  </section>;
}

export function Corrections() {
  const {
    id
  } = useParams();
  const {
    session
  } = useContext(Session);
  const [d, error, reload] = useData('/applications/' + id + '/corrections');
  if (!d) return <Loading error={error} />;
  const operate = d.call.mode === 'DEMO' && ['evaluation', 'results'].includes(d.call.status) && d.app.participation === 'active';
  const options = [...((d.authority || d.reviewer) && d.app.merit !== null ? [['', 'Méritos /40']] : []), ...d.grades.filter(g => g.status !== 'draft').map(g => [g.id, `Examen #${g.id} · ${g.score}/100`])];
  return <>
    <Link to={'/postulaciones/' + id}>← Expediente</Link>
    <h1>Rectificaciones y versiones</h1>
    <p>Solicitar no cambia la nota. Otra autoridad debe decidir y revisar el efecto sobre publicaciones.</p>
    {operate && options.length > 0 && <CorrectionRequest id={id} options={options} after={reload} />}
    <section>
      <h2>Solicitudes conservadas</h2>
      {d.requests.map(r => <section className="card" key={r.id}>
        <h3>Solicitud #{r.id} · {label(r.status)}</h3>
        <p>{r.old_value} → {r.new_value} · {r.reason}</p>
        <p>{r.evidence_reference}</p>
        {operate && d.authority && r.status === 'pending' && r.requester_id !== session.user.id && d.app.user_id !== session.user.id && <Action
          title="Decidir solicitud"
          endpoint={`/corrections/${r.id}/decision`}
          fields={[select('approved', 'Decisión', [['', 'Seleccionar decisión'], ['1', 'Aprobar'], ['0', 'Rechazar']]), reason]}
          after={reload}
        />}
      </section>)}
    </section>
    <section className="card">
      <h2>Historial de notas</h2>
      {d.versions.map(v => <p key={v.id}>v{v.version} · {v.score} · {v.created_at} · Responsable #{v.recorded_by ?? 'No inferido'}</p>)}
    </section>
  </>;
}
