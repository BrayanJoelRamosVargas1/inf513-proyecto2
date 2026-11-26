import React, { useContext, useState } from 'react';
import { api, base, label, message } from './api';
import { DocumentaryCall } from './documentary';
import { InstitutionalRegister } from './institutional-register';
import { Alert, Form, Loading, Session, useData } from './shared';
import { AppTable } from './workspaces';
import { Link, useNavigate, useParams } from 'react-router-dom';

const NEXT_STAGE = {
  draft: 'published',
  published: 'closed',
  closed: 'evaluation',
  evaluation: 'results',
  results: 'archived'
};

export function Call() {
  const {
      id
    } = useParams(),
    navigate = useNavigate();
  const {
    session
  } = useContext(Session);
  const [d, error, reload] = useData('/calls/' + id);
  const [err, set] = useState('');
  if (!d) return <Loading error={error} />;
  return <>
    <Link to="/convocatorias">← Convocatorias</Link>
    <h1>{d.call.title}</h1>
    <p>{label(d.call.status)} · {d.call.mode}</p>
    <p>{d.call.source}</p>
    <Alert text={err} />
    {d.documentary && <DocumentaryCall data={d.documentary} />}
    {d.authority && <section className="card">
      <h2>Control del proceso</h2>
      {d.call.mode === 'DEMO' && NEXT_STAGE[d.call.status] && <Form
        button={'Pasar a ' + label(NEXT_STAGE[d.call.status])}
        onSubmit={async () => {
          await api.post('/calls/' + id + '/transition', {
            status: NEXT_STAGE[d.call.status]
          });
          reload();
        }}
      />}
      <p>
        <Link to={`/convocatorias/${id}/configuracion`}>Configurar convocatoria y responsables →</Link>
      </p>
      <p>
        <Link to={`/convocatorias/${id}/auditoria`}>Consultar historial del proceso →</Link>
      </p>
      <p><a href={`${base}/api/v1/calls/${id}/reports/pdf`}>Descargar consolidado PDF</a> · <a href={`${base}/api/v1/calls/${id}/reports/xlsx`}>Descargar consolidado Excel</a></p>
    </section>}
    <div className="grid">
      {d.offers.filter(o => !d.documentary || !o.is_subject).map(o => <article className="card" key={o.id}>
        <span className="badge">
          {o.seats === null ? 'Plazas no especificadas' : `${o.seats} ${o.seats === 1 ? 'plaza' : 'plazas'}`}
        </span>
        <h2>{o.title}</h2>
        <p>{Object.entries(o.weights).map(([k, v]) => `${k}: ${v}%`).join(' · ')}</p>
        {d.documentary ? <p>Consulta los documentos, requisitos y calendario de esta modalidad en los apartados de la fuente original.</p> : <details>
          <summary>Documentos y requisitos</summary>
          <ul>
            {Object.values(o.documents).concat(Object.values(o.requirements)).map((t, i) => <li key={i}>{t}</li>)}
          </ul>
        </details>}
        {d.authority && <p><Link to={`/ofertas/${o.id}/resultados`}>Clasificación y publicación →</Link></p>}
        {session.user.role === 'student' && (o.own_application ? <Link className="button" to={'/postulaciones/' + o.own_application}>Abrir mi postulación →</Link> : o.application_block ? <p>{o.application_block}</p> : <button onClick={async () => {
          try {
            const r = await api.post('/offers/' + o.id + '/applications');
            navigate('/postulaciones/' + r.data.id);
          } catch (e) {
            set(message(e));
          }
        }}>Iniciar postulación</button>)}
      </article>)}
    </div>
    {d.authority && <InstitutionalRegister callId={id} />}
    {!d.documentary && <section className="card">
      <h2>Expedientes visibles</h2>
      <AppTable items={d.applications} />
    </section>}
  </>;
}
