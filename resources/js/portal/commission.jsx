import React from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { Action, field, localDate, opts, reason, select } from './form-kit';
import { label } from './api';
import { Loading, useData } from './shared';

/** Comisión evaluadora y veedores (Resolución 021/2023, Arts. 13 y 14). La registra la autoridad de la convocatoria. */
export function CallCommission() {
  const {
    id
  } = useParams();
  const [d, error, reload] = useData(`/calls/${id}/commission`);
  if (!d) return <Loading error={error} />;
  const missing = Object.entries(d.missing);
  const cargos = Object.entries(d.cargos).filter(([key]) => !d.observers.includes(key));
  const observers = Object.entries(d.cargos).filter(([key]) => d.observers.includes(key));
  const offers = [['', 'Toda la convocatoria'], ...opts(d.offers)];
  return <>
    <Link to={`/convocatorias/${id}`}>← Convocatoria</Link>
    <h1>Comisión evaluadora y veedores</h1>
    <p>La designa el Director de Carrera, el Vicedecano o el Decano; aquí se registra con la referencia del acto de designación. Solo quien integra la comisión puede ser asignado como evaluador, y la evaluación no se abre sin la composición mínima de cada oferta. Los veedores se acreditan al menos 48 horas antes del examen, no votan ni operan el sistema.</p>
    {missing.length > 0 ? <div className="notice" role="alert">
      <strong>Composición incompleta.</strong>
      <ul>{missing.map(([title, lack]) => <li key={title}>{title}: falta {lack.join(', ')}</li>)}</ul>
    </div> : <div className="notice" role="status">La composición mínima de todas las ofertas está completa.</div>}
    <Action
      title="Designar integrante"
      endpoint={`/calls/${id}/commission`}
      after={reload}
      fields={[select('cargo', 'Cargo', cargos), select('user_id', 'Cuenta de la persona', opts(d.candidates)), {
        ...select('offer_id', 'Alcance', offers, ''),
        required: false
      }, field('designation_reference', 'Referencia del acto de designación', {
        maxLength: 100
      })]}
      transform={values => ({
        ...values,
        offer_id: values.offer_id || null
      })}
    />
    <Action
      title="Acreditar veedor"
      endpoint={`/calls/${id}/commission`}
      after={reload}
      fields={[select('cargo', 'Representación', observers), field('name', 'Nombre completo', {
        maxLength: 180
      }), field('accredited_on', 'Fecha de acreditación', {
        type: 'date'
      }), field('designation_reference', 'Referencia de la acreditación', {
        maxLength: 100
      })]}
    />
    <section>
      <h2>Integrantes vigentes</h2>
      {d.members.length === 0 && <p>Aún no hay integrantes registrados.</p>}
      {d.members.map(m => <details className="card" key={m.id}>
        <summary>{m.name} · {d.cargos[m.cargo] ?? label(m.cargo)} · {m.offer_id ? d.offers.find(o => o.id === m.offer_id)?.title : 'Toda la convocatoria'}</summary>
        <p>Acto: {m.designation_reference}{m.accredited_on ? ` · Acreditado el ${m.accredited_on}` : ''}</p>
        <Action title="Retirar de la comisión" endpoint={`/commission-members/${m.id}/revocation`} fields={[reason]} button="Retirar" after={reload} />
      </details>)}
    </section>
  </>;
}

/** Plazas sin cubrir, convocatorias consecutivas y paso a una nueva convocatoria (Art. 20 de docencia, Art. 19 de laboratorio). */
export function CallVacancies() {
  const {
      id
    } = useParams(),
    navigate = useNavigate();
  const [d, error] = useData(`/calls/${id}/vacancies`);
  if (!d) return <Loading error={error} />;
  const outcomes = {
    pending: 'Resultados sin aprobar',
    covered: 'Plazas cubiertas',
    partial: 'Quedan plazas sin cubrir',
    deserted: 'Desierta: nadie fue seleccionado'
  };
  const canCarry = ['results', 'archived'].includes(d.call.status) && d.offers.some(o => (o.vacancies ?? 0) > 0 && !o.may_declare);
  return <>
    <Link to={`/convocatorias/${id}`}>← Convocatoria</Link>
    <h1>Plazas sin cubrir</h1>
    <p>Si nadie se presenta, nadie alcanza el puntaje de aprobación o la persona designada no acepta, la plaza se convoca de nuevo. Solo se admiten {d.max_consecutive} convocatorias consecutivas: después corresponde el acta de declaración desierta o vacante y elegir entre los aprobados, entre los presentados o invitar con formulario por excepción de la DICAA. El acta se registra en el registro institucional.</p>
    <table>
      <caption>Estado de las plazas por oferta</caption>
      <thead><tr><th scope="col">Oferta</th><th scope="col">Plazas</th><th scope="col">Postulantes</th><th scope="col">Seleccionados</th><th scope="col">Sin cubrir</th><th scope="col">Situación</th><th scope="col">Convocatorias consecutivas</th></tr></thead>
      <tbody>
        {d.offers.map(o => <tr key={o.offer_id}>
          <td>{o.title}</td><td>{o.seats}</td><td>{o.applicants}</td><td>{o.selected}</td><td>{o.vacancies ?? '—'}</td>
          <td>{outcomes[o.outcome]}{o.may_declare ? ' · Corresponde acta de declaración desierta o vacante' : ''}</td>
          <td>{o.consecutive_calls_unfilled || '—'}</td>
        </tr>)}
      </tbody>
    </table>
    {canCarry && <Action
      title="Convocar de nuevo las plazas sin cubrir"
      endpoint={`/calls/${id}/carry-over`}
      button="Crear convocatoria vinculada"
      fields={[field('title', 'Título de la nueva convocatoria', {
        maxLength: 150
      }), field('opens_at', 'Apertura de recepción', {
        type: 'datetime-local',
        value: localDate(new Date())
      }), field('closes_at', 'Cierre de recepción', {
        type: 'datetime-local'
      }), field('exam_at', 'Fecha del examen', {
        type: 'datetime-local'
      }), field('source', 'Fuente o decisión', {
        maxLength: 2000
      })]}
      after={r => navigate(`/convocatorias/${r.id}`)}
    />}
  </>;
}
