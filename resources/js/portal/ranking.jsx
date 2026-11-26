import React from 'react';
import { base, label, isOperable } from './api';
import { Action, field } from './form-kit';
import { ResultHistory } from './result-history';
import { Loading, useData } from './shared';
import { Link, useParams } from 'react-router-dom';

export function Ranking() {
  const {
    id
  } = useParams();
  const [d, error, reload] = useData('/offers/' + id + '/ranking');
  if (!d) return <Loading error={error} />;
  const operate = isOperable(d.call) && ['evaluation', 'results'].includes(d.call.status);
  const tied = d.ranking?.tied?.length > 0;
  const resolved = d.ties.some(t => t.status === 'resolved');
  const parse = v => typeof v === 'string' ? JSON.parse(v) : v;
  return <><Link to={'/convocatorias/' + d.call.id}>← Convocatoria</Link><h1>Clasificación y selección · {d.offer.title}</h1>{d.documentary_reference ? <p>Plazas ofertadas en la fuente: {d.offer.seats ?? 'No especificadas'}. Cobertura y vacantes históricas no acreditadas.</p> : <p>{d.offer.seats} {d.offer.seats === 1 ? 'plaza' : 'plazas'} · {d.offer.held_vacancies} {d.offer.held_vacancies === 1 ? 'retenida' : 'retenidas'} por renuncia</p>}{d.warning && <div role="status" className="notice">{d.warning}</div>}{d.ranking && <section className="card">
      <h2>Resultados de la oferta</h2>
      <div
        className="table-wrap"
        role="region"
        aria-label="Clasificación de postulantes"
        tabIndex="0"
      >
        <table>
          <thead>
            <tr>
              <th scope="col">Orden visual</th>
              <th scope="col">Postulante</th>
              <th scope="col">Mérito</th>
              <th scope="col">Examen</th>
              <th scope="col">Final</th>
              <th scope="col">Selección</th>
            </tr>
          </thead>
          <tbody>
            {d.ranking.rank.map((a, i) => <tr key={a.id}>
              <td>{i + 1}</td>
              <td><Link to={'/postulaciones/' + a.id}>{a.name}</Link></td>
              <td>{a.merit}</td>
              <td>{a.exam}</td>
              <td>{a.final}</td>
              <td>{label(a.selection)}</td>
            </tr>)}
          </tbody>
        </table>
      </div>
      <p>El orden visual de notas iguales no resuelve un empate.</p>
      {d.ranking.approval_threshold != null && <p>Nota mínima final de aprobación: {d.ranking.approval_threshold}. {d.ranking.below_threshold?.length ? `${d.ranking.below_threshold.length} postulación(es) habilitada(s) no la alcanzan y quedan fuera de la selección.` : 'Todas las postulaciones habilitadas la alcanzan.'}</p>}
      <p>Vacantes totales: {d.ranking.vacancies} · Retenidas: {d.ranking.held_vacancies} · Por insuficiencia: {d.ranking.vacancies - d.ranking.held_vacancies}</p>
    </section>}{operate && tied && !resolved && d.ties.length === 0 && <Action
      title="Convocar examen exclusivo de desempate"
      endpoint={`/offers/${id}/ties`}
      fields={[field('commission', 'Comisión: nombres separados por coma'), field('scheduled_at', 'Fecha del examen', {
        type: 'datetime-local'
      }), field('conditions', 'Condiciones acordadas por comisión', {
        type: 'textarea',
        minLength: 20
      })]}
      after={reload}
    />} {d.ties.map(t => <section className="card" key={t.id}>
      <h2>Desempate #{t.id} · Ronda {t.round}</h2>
      <p>{label(t.status)} · {t.conditions}</p>
      {t.status === 'scheduled' && operate ? <>
        <Action
          title="Registrar decisión de comisión"
          endpoint={`/ties/${t.id}/resolution`}
          fields={[...parse(t.participants).map(pid => field('result-' + pid, 'Resultado postulación #' + pid)), field('winners', 'IDs seleccionados por comisión, separados por coma'), field('minutes', 'Acta y fundamento', {
            type: 'textarea',
            minLength: 20
          })]}
          transform={v => ({
            results: Object.fromEntries(parse(t.participants).map(pid => [pid, v['result-' + pid]])),
            winners: v.winners.split(',').map(x => Number(x.trim())),
            minutes: v.minutes
          })}
          after={reload}
        />
        <Action
          title="Persiste el empate: nueva ronda"
          endpoint={`/ties/${t.id}/continuation`}
          fields={[...parse(t.participants).map(pid => field('result-' + pid, 'Resultado postulación #' + pid)), field('minutes', 'Acta de persistencia', {
            type: 'textarea',
            minLength: 20
          }), field('commission', 'Comisión de nueva ronda'), field('scheduled_at', 'Nueva fecha', {
            type: 'datetime-local'
          }), field('conditions', 'Nuevas condiciones', {
            type: 'textarea',
            minLength: 20
          })]}
          transform={v => ({
            ...v,
            results: Object.fromEntries(parse(t.participants).map(pid => [pid, v['result-' + pid]]))
          })}
          after={reload}
        />
      </> : <p>Acta: {t.minutes ?? 'Pendiente'}</p>}
    </section>)}{operate && d.ranking && (!tied || resolved) && (d.publications.length === 0 || d.offer.needs_publication_review) && <Action
      title="Aprobar selección"
      endpoint={`/offers/${id}/publications`}
      fields={[field('minutes', 'Acta y motivo de aprobación', {
        type: 'textarea',
        minLength: 20
      })]}
      button="Aprobar publicación"
      after={reload}
    />}<ResultHistory
      publications={d.publications}
      revisions={d.revisions}
      oldTies={d.oldTies}
      callId={d.call.id}
    /><section className="card"><h2>Reportes de toda la convocatoria</h2><a href={`${base}/api/v1/calls/${d.call.id}/reports/pdf`}>Descargar PDF</a> · <a href={`${base}/api/v1/calls/${d.call.id}/reports/xlsx`}>Descargar Excel</a></section></>;
}
