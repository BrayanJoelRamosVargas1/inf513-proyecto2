import React, { useState } from 'react';
import { base } from './api';
const date = value => value ? new Date(value.length === 10 ? value + 'T12:00:00-04:00' : value).toLocaleDateString('es-BO', {
  timeZone: 'America/La_Paz',
  day: '2-digit',
  month: 'long',
  year: 'numeric',
  ...(value.length > 10 ? {
    hour: '2-digit',
    minute: '2-digit'
  } : {})
}) : 'No especificada en la fuente';

export function DocumentaryCall({
  data
}) {
  const [mode, setMode] = useState('asignatura'),
    [search, setSearch] = useState('');
  const m = data.modalities[mode],
    calendar = data.calendars[mode];
  const rows = data.subjects.filter(s => (s.code + ' ' + s.original_name + ' ' + s.source_group).toLocaleLowerCase('es').includes(search.toLocaleLowerCase('es')));
  return <>
    <section className="card source-overview">
      <p className="eyebrow">Archivo institucional · {data.number}</p>
      <h2>Documentos originales de la convocatoria</h2>
      <p>Consulta documental de un proceso histórico cerrado. No admite nuevas postulaciones ni acredita resultados o designaciones.</p>
      <div className="source-downloads">
        {data.sources.map(s => <a
          className="button secondary"
          key={s.key}
          href={`${base}/api/v1/reference-sources/${s.key}`}
        >Descargar {s.name} <small>{s.pages} páginas · PDF original</small></a>)}
      </div>
      <details>
        <summary>Comprobar identidad de los originales</summary>
        {data.sources.map(s => <p key={s.key}>{s.name}<code className="source-hash">SHA-256: {s.sha256}</code></p>)}
      </details>
    </section>
    <div className="filter-tabs" role="group" aria-label="Modalidad de auxiliatura">
      {Object.entries(data.modalities).map(([key, value]) => <button key={key} aria-pressed={mode === key} onClick={() => setMode(key)}>
        {value.title}
      </button>)}
    </div>
    <section className="card">
      <p className="eyebrow">
        {m.regime === 'docencia' ? 'Régimen de docencia' : 'Régimen de laboratorio'}
      </p>
      <h2>{m.title}</h2>
      <p>{m.verification}</p>
      <h3>Calendario documental · {calendar.timezone}</h3>
      <dl className="documentary-calendar">
        {[['Apertura', calendar.opening], ['Cierre de recepción', calendar.closing], ['Inicio de exámenes', calendar.exam_start], ['Fin de exámenes', calendar.exam_end], ['Resultados previstos', calendar.results_planned], ['Publicación efectiva acreditada', calendar.results_actual], ['Asignación de horarios', calendar.timetable_assignment]].map(([key, value]) => <div key={key}><dt>{key}</dt><dd>{date(value)}</dd></div>)}
      </dl>
      <p>{calendar.period}</p>
      {calendar.conflict && <p className="notice">Advertencia documental: {calendar.conflict}</p>}
      <details>
        <summary>Requisitos que deben verificarse individualmente</summary>
        <ul>
          {Object.entries(m.requirements).map(([key, value]) => <li key={key}>{value}</li>)}
        </ul>
        <p>Estos son criterios de la fuente; su visualización no significa que un postulante los haya cumplido.</p>
      </details>
      <details>
        <summary>Documentación y formularios auténticos</summary>
        <ul>{Object.entries(m.documents).map(([key, value]) => <li key={key}>{value}</li>)}</ul>
        <p>{m.physical_delivery}</p>
        <h4>Formulario CPD</h4>
        <p>{data.cpd.request}</p>
        <p>{data.cpd.status}</p>
        <p>No se ofrece una muestra como sustituto del formulario oficial.</p>
      </details>
      <details>
        <summary>Evaluación de méritos y examen de competencia</summary>
        <p>Méritos: máximo 40 puntos. Examen: máximo 60. Total: 100.</p>
        <ul>
          {m.merit_components.map(c => <li key={c.key}>
            <strong>{c.label}: máximo {c.maximum} puntos.</strong>
            {c.bands && <p>Tramos impresos: {c.bands.map(b => `${b[0]}–${b[1]}: ${b[2]} puntos`).join('; ')}.</p>}
            {c.printed_bands && <p>Tabla original: {c.printed_bands.map(b => `${b[0]}–${b[1]}: ${b[2]} y ${b[3]} puntos`).join('; ')}.</p>}
            {c.semester_points && <p>{c.semester_points} puntos por semestre{c.annual_points ? ` o ${c.annual_points} por año` : ''}.</p>}
            {c.ambiguity && <p className="notice">Pendiente de aclaración: {c.ambiguity}</p>}
            {c.decimal_policy && <p>Decimales: {c.decimal_policy}</p>}
          </li>)}
        </ul>
        <p>Examen: {Object.entries(m.exam.components).map(([key, value]) => `${key}: ${value} puntos`).join(' · ')}.</p>
        <p>Práctico: {m.exam.practical_criteria.join(', ')}. Exposición: {m.exam.presentation_minutes} minutos; preguntas: {m.exam.questions_minutes} minutos.</p>
        <p>{m.exam.areas_composition}</p>
        <p>Nota mínima final aprobatoria: pendiente de confirmación. No se utiliza el PPA mínimo como umbral del concurso.</p>
      </details>
      <details>
        <summary>Comisión, desempates y actos administrativos</summary>
        <p>Designación: {m.commission.designation}</p>
        <ul>{m.commission.members.map(v => <li key={v}>{v}</li>)}</ul>
        {m.commission.additional && <p>{m.commission.additional}</p>}
        {m.commission.ambiguity && <p className="notice">{m.commission.ambiguity}</p>}
        <p>{m.commission.observers}</p>
        <p>{data.tie_exam}</p>
        <p>{data.publication[m.regime]}</p>
        <p>{data.administrative_scope}</p>
      </details>
    </section>
    {mode === 'asignatura' && <section className="card">
      <div className="dashboard-section-title">
        <h2>Asignaturas ofertadas ({data.subjects.length})</h2>
        <span>Plazas individuales no especificadas</span>
      </div>
      <label>Buscar asignatura, sigla o agrupación<input
          type="search"
          value={search}
          onChange={e => setSearch(e.target.value)}
          placeholder="Ej.: INF312 o Área común"
        /></label>
      <p role="status">{rows.length} de {data.subjects.length} asignaturas</p>
      <div
        className="table-scroll"
        role="region"
        aria-label="Catálogo de asignaturas"
        tabIndex="0"
      >
        <table>
          <thead>
            <tr>
              <th scope="col">N.º impreso</th>
              <th scope="col">Sigla</th>
              <th scope="col">Asignatura original</th>
              <th scope="col">Agrupación</th>
              <th scope="col">Fuente</th>
              <th scope="col">Plazas</th>
            </tr>
          </thead>
          <tbody>
            {rows.map(s => <tr key={s.offer_id}>
              <td>{s.printed_number}</td>
              <td>{s.code}</td>
              <td>{s.original_name}</td>
              <td>{s.source_group}</td>
              <td>Página {s.source_page}</td>
              <td>No especificadas</td>
            </tr>)}
          </tbody>
        </table>
      </div>
      {!rows.length && <p>No hay coincidencias. Prueba con otra sigla o denominación.</p>}
      <p>Se conserva la escritura del original, incluidos posibles errores tipográficos. La numeración con saltos no representa plazas ni asignaturas adicionales.</p>
    </section>}
    <section className="card">
      <h2>Validaciones institucionales pendientes</h2>
      <ul>{data.pending.map(v => <li key={v}>{v}</li>)}</ul>
    </section>
  </>;
}
