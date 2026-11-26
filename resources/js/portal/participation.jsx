import React, { useContext } from 'react';
import { api, label, isOperable } from './api';
import { Action, evidence, field, reason, select } from './form-kit';
import { DocumentLink, Form, Loading, Session, useData } from './shared';
import { Link, useParams } from 'react-router-dom';

export function Participation() {
  const {
    id
  } = useParams();
  const [d, error, reload] = useData('/applications/' + id + '/participation');
  if (!d) return <Loading error={error} />;
  const operate = isOperable(d.call) && ['published', 'closed', 'evaluation', 'results'].includes(d.call.status);
  const parse = v => typeof v === 'string' ? JSON.parse(v) : v;
  const docs = parse(d.offer.documents);
  return <><Link to={'/postulaciones/' + id}>← Expediente</Link><h1>Subsanaciones y participación</h1><button type="button" onClick={reload}>Actualizar estado de archivos</button><p>{d.offer.title} · Versión {d.app.dossier_version} · {label(d.app.participation)}</p><div className="notice">Procedimiento con plazos explícitos fijados por la autoridad de la convocatoria. No se asignan reemplazos automáticamente.</div>{d.canOpen && d.app.participation === 'active' && <Action
      title="Autorizar subsanación"
      endpoint={`/applications/${id}/amendments`}
      fields={[select('kinds', 'Documento autorizado', Object.entries(docs)), field('deadline', 'Vencimiento inclusivo', {
        type: 'datetime-local'
      }), reason, evidence]}
      transform={v => ({
        ...v,
        kinds: [v.kinds]
      })}
      after={reload}
    />} {d.amendments.map(m => <section className="card" key={m.id}><h2>Subsanación #{m.id} · {label(m.status)}</h2><p>Vence: {m.deadline} · {m.reason}</p><p>Documentos autorizados: {parse(m.allowed_kinds).map(k => docs[k] ?? k).join(', ')}</p>{d.owner && m.status === 'open' && new Date(m.deadline) >= new Date() && operate && <>
        <Form
          button="Agregar nueva versión"
          onSubmit={async f => {
            await api.post(`/amendments/${m.id}/documents`, f);
            reload();
          }}
        >
          <label>Documento<select name="kind">
              {parse(m.allowed_kinds).map(k => <option key={k} value={k}>{docs[k] ?? k}</option>)}
            </select></label>
          <label>Archivo PDF/JPG/PNG hasta 5 MB<input name="file" type="file" accept=".pdf,.png,.jpg,.jpeg" required /></label>
        </Form>
        <Action
          title="Enviar corrección"
          endpoint={`/amendments/${m.id}/submission`}
          button="Enviar subsanación a revisión"
          after={reload}
        />
      </>}{d.reviewer && !d.owner && m.status === 'submitted' && operate && <Action
        title="Nueva revisión completa"
        endpoint={`/amendments/${m.id}/review`}
        fields={[...Object.entries(docs).map(([k, t]) => select('doc:' + k, t, [['observed', 'Observado'], ['meets', 'Cumple'], ['fails', 'No cumple']])), ...Object.entries(parse(d.offer.requirements)).map(([k, t]) => select(k, t, [['observed', 'Observado'], ['meets', 'Cumple'], ['fails', 'No cumple'], ...(d.offer.requirement_profile === 'asignatura' && k === 'graduacion_mora' ? [['not_applicable', 'No aplica con fundamento']] : [])])), reason]}
        transform={v => ({
          reason: v.reason,
          decisions: Object.fromEntries(Object.entries(v).filter(([k]) => k !== 'reason'))
        })}
        after={reload}
      />} {(d.reviewer || d.authority) && !d.owner && m.status === 'open' && new Date(m.deadline) < new Date() && operate && <Action
        title="Cerrar ventana vencida"
        endpoint={`/amendments/${m.id}/expiration`}
        fields={[reason]}
        after={reload}
      />}<ul>
        {d.documents.filter(doc => doc.amendment_id === m.id).map(doc => <li key={doc.id}><DocumentLink doc={doc} /></li>)}
      </ul></section>)}{d.owner && d.app.participation === 'active' && operate && !d.requests.some(r => r.status === 'pending') && <Action
      title={d.award ? 'Solicitar renuncia a plaza' : 'Solicitar retiro de postulación'}
      endpoint={`/applications/${id}/participation-requests`}
      data={{
        kind: d.award ? 'renunciation' : 'withdrawal'
      }}
      fields={[reason, evidence]}
      after={reload}
    />}<section>
      <h2>Historial de solicitudes</h2>
      {d.requests.map(r => <section className="card" key={r.id}>
        <h3>{label(r.kind)} · {label(r.status)}</h3>
        <p>{r.reason} · {r.evidence}</p>
        {d.decisions?.[r.id] && <p>Decisión registrada: {d.decisions[r.id].approved ? 'Aprobada' : 'Rechazada'} · {d.decisions[r.id].reason} · Responsable #{d.decisions[r.id].user_id} · {d.decisions[r.id].created_at}</p>}
        {d.authority && r.status === 'pending' && !d.owner && operate && <Action
          title="Decidir participación"
          endpoint={`/participation-requests/${r.id}/decision`}
          fields={[select('approved', 'Decisión', [['', 'Seleccionar decisión'], ['1', 'Aprobar'], ['0', 'Rechazar']]), reason]}
          after={reload}
        />}
      </section>)}
    </section><section className="card">
      <h2>Versiones conservadas del expediente</h2>
      {d.snapshots.map(s => <details key={s.id}>
        <summary>v{s.version} · {label(s.phase)} · {s.created_at}</summary>
        <ul>
          {parse(s.snapshot).documents.map(doc => <li key={doc.id}>{docs[doc.kind] ?? doc.kind} · Registro #{doc.id} · v{doc.version}</li>)}
        </ul>
      </details>)}
    </section></>;
}

export function Awards() {
  const {
    session
  } = useContext(Session);
  const [d, error, reload] = useData('/awards');
  if (!d) return <Loading error={error} />;
  return <>
    <h1>{d.authorityView ? 'Adjudicaciones de mi ámbito' : 'Mis adjudicaciones'}</h1>
    <p>Aceptar o diferir registra su decisión. El nombramiento lo emite la autoridad competente.</p>
    {d.awards.map(a => <section className="card" key={a.id}>
      <h2>{a.name} · {a.title}</h2>
      <p>Publicación v{a.publication.version} · {label(a.selection)}</p>
      <p>
        <Link to={'/postulaciones/' + a.id + '/participacion'}>Expediente, retiro o renuncia</Link>
      </p>
      {a.can_choose && a.user_id === session.user.id && a.participation === 'active' && <Action
        title="Registrar manifestación sobre esta publicación"
        endpoint={`/applications/${a.id}/choices`}
        fields={[select('choice', 'Decisión', [['accept', 'Aceptar la plaza'], ['defer', 'Diferir decisión']]), reason, evidence]}
        data={{
          publication_id: a.publication.id,
          version: a.choice?.version ?? 0
        }}
        after={reload}
      />}
      <details>
        <summary>Historia de manifestaciones</summary>
        {a.choices.map(c => <p key={c.id}>v{c.version} · {label(c.choice)} · {c.reason}</p>)}
      </details>
    </section>)}
    {!d.awards.length && <p>No hay adjudicaciones publicadas visibles para tu cuenta.</p>}
  </>;
}
