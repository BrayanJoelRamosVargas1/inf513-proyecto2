import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { api, base } from './api';
import { useData, Form, Loading } from './shared';
const fieldLabels = {
  modality: 'Modalidad',
  authority: 'Autoridad emisora',
  issued_on: 'Fecha de emisión',
  valid_until: 'Vigencia hasta',
  observers: 'Veedores',
  accreditation_on: 'Acreditación',
  act_type: 'Tipo de acto',
  deadline_on: 'Plazo de seguimiento',
  offer_id: 'Oferta',
  title: 'Nombre del formato',
  issuer: 'Unidad emisora',
  valid_from: 'Vigente desde',
  source_sha256: 'Huella del original',
  source_version: 'Versión del respaldo',
  commission_reference: 'Referencia de comisión',
  starts_at: 'Inicio',
  ends_at: 'Fin',
  conditions: 'Condiciones',
  record_state: 'Estado documental',
  approval_reference: 'Referencia de aprobación externa',
  approval_authority: 'Autoridad de aprobación externa',
  timezone: 'Zona horaria',
  opening: 'Apertura propuesta',
  closing: 'Cierre propuesto',
  exam_start: 'Inicio de exámenes',
  exam_end: 'Fin de exámenes',
  results_planned: 'Publicación prevista',
  results_actual: 'Publicación efectiva documentada',
  timetable_assignment: 'Asignación de horarios'
};

const states = {
  draft: 'Borrador',
  internal: 'Registro interno',
  documented: 'Documento respaldado',
  external_approval_recorded: 'Aprobación externa registrada, sujeta a verificación'
};

const names = {
  commission: 'Comisión evaluadora',
  act: 'Acto administrativo',
  template: 'Plantilla institucional',
  exam_plan: 'Plan de examen',
  calendar: 'Calendario por modalidad'
};

export function InstitutionalRegister({
  callId
}) {
  const [d, error, reload] = useData(`/calls/${callId}/institutional-register`);
  const [kind, setKind] = useState('commission'),
    [status, setStatus] = useState('');
  if (!d) return <Loading error={error} />;
  return <section className="card">
    <h2>Registro institucional y versiones</h2>
    <p>Conserva referencias de comisiones, actos, plantillas y planes de examen. Registrar evidencia no concede permisos, no homologa una plantilla ni aprueba un concurso.</p>
    <p><Link to="/institucional">Cargar un respaldo auténtico en Preparación institucional →</Link> Su descarga mantiene los controles de cuarentena e integridad.</p>
    <details>
      <summary>Registrar una referencia o nueva versión</summary>
      <label>Tipo de registro<select value={kind} onChange={e => setKind(e.target.value)}>
          {Object.entries(names).map(([k, n]) => <option value={k} key={k}>{n}</option>)}
        </select></label>
      <Form
        key={kind}
        button="Conservar registro documental"
        onSubmit={async f => {
          const v = Object.fromEntries(f),
            reference = v.reference.trim();
          const expected = Math.max(0, ...d.records.filter(r => r.kind === kind && r.reference === reference).map(r => r.version));
          const payload = {
            ...v
          };
          delete payload.reference;
          delete payload.source_id;
          if (kind === 'commission') payload.members = v.members.split('\n').filter(s => s.trim()).map(line => {
            const [name, ...role] = line.split('|');
            return {
              name: name.trim(),
              responsibility: role.join('|').trim()
            };
          });
          if (kind === 'template') payload.fields = v.fields.split('\n').map(s => s.trim()).filter(Boolean);
          if (kind === 'exam_plan') payload.criteria = v.criteria.split('\n').map(s => s.trim()).filter(Boolean);
          Object.keys(payload).forEach(k => {
            if (payload[k] === '') payload[k] = null;
          });
          await api.post(`/calls/${callId}/institutional-register`, {
            kind,
            reference,
            expected_version: expected,
            source_id: v.source_id ? Number(v.source_id) : null,
            payload
          });
          reload();
          setStatus('Registro conservado. Su validez institucional sigue pendiente.');
        }}
      >
        <label>Referencia del documento<input name="reference" required maxLength="100" /></label>
        <label>Modalidad<select name="modality">
            <option value="asignatura">Docencia por asignatura</option>
            <option value="sala">Docencia en sala de cómputo</option>
            <option value="laboratorio">Laboratorio de servicio</option>
          </select></label>
        <label>Estado documental<select name="record_state">
            <option value="internal">Registro interno, sin aprobación</option>
            <option value="draft">Borrador</option>
            <option value="documented">Documento respaldado</option>
            <option value="external_approval_recorded">Aprobación externa registrada, sujeta a verificación</option>
          </select></label>
        <label>Referencia de aprobación externa (si corresponde)<input name="approval_reference" maxLength="100" /></label>
        <label>Autoridad de aprobación externa (si corresponde)<input name="approval_authority" maxLength="180" /></label>
        <label>Respaldo original disponible<select name="source_id">
            <option value="">Pendiente de recibir</option>
            {d.sources.map(s => <option key={s.id} value={s.id}>{s.title} · versión {s.version}</option>)}
          </select></label>
        {['commission', 'act'].includes(kind) && <>
          <label>Oferta asociada<select name="offer_id">
              <option value="">Ámbito de la convocatoria</option>
              {(d.offers ?? []).map(o => <option key={o.id} value={o.id}>{o.title}</option>)}
            </select></label>
          <label>Autoridad que emite o designa<input name="authority" maxLength="180" required /></label>
          <label>Fecha del documento<input name="issued_on" type="date" required /></label>
        </>}
        {kind === 'commission' && <>
          <label>Integrantes: una línea por persona, formato Nombre | Responsabilidad<textarea name="members" required maxLength="6000" /></label>
          <label>Veedores y acreditaciones<textarea name="observers" maxLength="1500" /></label>
          <label>Fecha de acreditación<input name="accreditation_on" type="date" /></label>
          <label>Vigencia hasta<input name="valid_until" type="date" /></label>
        </>}
        {kind === 'act' && <>
          <label>Tipo de acto<select name="act_type">
              {['informe', 'acta', 'aprobacion', 'desempate', 'publicacion', 'resolucion', 'designacion', 'desierta', 'vacancia', 'renuncia', 'excepcion'].map(t => <option key={t}>{t}</option>)}
            </select></label>
          <label>Plazo de seguimiento<input name="deadline_on" type="date" /></label>
        </>}
        {kind === 'template' && <>
          <label>Nombre del formato<input name="title" maxLength="180" required /></label>
          <label>Unidad emisora<input name="issuer" maxLength="180" required /></label>
          <label>Vigente desde<input name="valid_from" type="date" /></label>
          <label>Vigente hasta<input name="valid_until" type="date" /></label>
          <label>Campos originales: uno por línea<textarea name="fields" required maxLength="6000" /></label>
        </>}
        {kind === 'exam_plan' && <>
          <label>Referencia de comisión<input name="commission_reference" required maxLength="100" /></label>
          <label>Inicio<input type="datetime-local" name="starts_at" required /></label>
          <label>Fin<input type="datetime-local" name="ends_at" required /></label>
          <label>Condiciones del examen<textarea name="conditions" minLength="10" maxLength="3000" required /></label>
          <label>Criterios aplicables: uno por línea<textarea name="criteria" required maxLength="3000" /></label>
        </>}
        {kind === 'calendar' && <>
          <p>Planificación documentada. No modifica fechas originales históricas ni reabre recepción. Las fechas operativas del borrador se gestionan en Configuración.</p>
          <input name="timezone" type="hidden" value="America/La_Paz" />
          {[['opening', 'Apertura propuesta'], ['closing', 'Cierre propuesto'], ['exam_start', 'Inicio de exámenes'], ['exam_end', 'Fin de exámenes'], ['results_planned', 'Publicación prevista'], ['results_actual', 'Publicación efectiva documentada'], ['timetable_assignment', 'Asignación de horarios']].map(([key, title]) => <label key={key}>{title}<input type="datetime-local" name={key} /></label>)}
        </>}
        <label>Fundamento, alcance y pendientes<textarea name="notes" minLength="10" maxLength="3000" required /></label>
        <p>Se conserva una nueva versión. No se reemplazan documentos ni publicaciones anteriores.</p>
      </Form>
      <p role="status">{status}</p>
    </details>
    <p>
      <a href={`${base}/api/v1/calls/${callId}/institutional-register/export`}>Descargar registro interno CSV</a>
    </p>
    <h3>Historial documental</h3>
    {!d.records.length && <p>No hay comisiones, actos, formatos o planes registrados. No se inventan documentos para completar este apartado.</p>}
    {d.records.map(r => <details key={r.id}>
      <summary>{names[r.kind]} · {r.reference} · versión {r.version}</summary>
      <p>{r.payload.notes}</p>
      <p>{r.payload.validation}</p>
      <p>Registrado por cuenta #{r.recorded_by} · {r.created_at}</p>
      <dl>
        {Object.entries(r.payload).filter(([k, v]) => !['notes', 'validation'].includes(k) && !Array.isArray(v) && v !== null).map(([k, v]) => <div key={k}>
          <dt>{fieldLabels[k] ?? k}</dt>
          <dd>{k === 'record_state' ? states[v] ?? v : String(v)}</dd>
        </div>)}
      </dl>
      {['fields', 'criteria'].map(key => r.payload[key] && <ul key={key}>{r.payload[key].map((value, i) => <li key={i}>{value}</li>)}</ul>)}
      {r.payload.members && <ul>{r.payload.members.map((m, i) => <li key={i}>{m.name} · {m.responsibility}</li>)}</ul>}
      {r.source_id && <p>
        <a href={`${base}/api/v1/institutional/sources/${r.source_id}/download`}>Descargar respaldo original sujeto a validación de seguridad</a>
      </p>}
    </details>)}
  </section>;
}

export function CpdRecord({
  data,
  reload
}) {
  const context = data.cpd_context,
    [reference, setReference] = useState(''),
    [status, setStatus] = useState('');
  if (!context) return null;
  const doc = data.documents.find(d => d.kind === 'cpd');
  return <section className="card">
    <h2>Formulario CPD y componentes de méritos</h2>
    <p>El formulario auténtico se solicita al CPD Facultativo. No emitimos un formulario oficial ni lo sustituimos con una muestra.</p>
    <p>La desagregación siguiente conserva lo consignado en el respaldo; no calcula una rúbrica ambigua ni modifica automáticamente el mérito del expediente.</p>
    {context.records.map(r => <article key={r.id}>
      <h3>{r.reference} · versión {r.version}</h3>
      <p>Puntaje consignado: {r.payload.score}/40 · suma de componentes: {r.payload.component_sum}/40 · mérito registrado en el expediente: {data.application.merit ?? 'Pendiente'}</p>
      <ul>
        {Object.entries(r.payload.components).map(([k, v]) => <li key={k}>{context.definition.merit_components.find(c => c.key === k)?.label}: {v}</li>)}
      </ul>
      {r.payload.difference_reason && <p>Diferencia fundamentada: {r.payload.difference_reason}</p>}
      <p>{r.payload.notes}</p>
      <small>Cuenta #{r.recorded_by} · {r.created_at}</small>
    </article>)}
    {data.reviewer && !data.owner && data.document_validation.ready && doc && data.application.status === 'submitted' && <details>
      <summary>Registrar componentes del formulario recibido</summary>
      <Form
        button="Conservar respaldo CPD estructurado"
        onSubmit={async f => {
          const v = Object.fromEntries(f),
            expected = Math.max(0, ...context.records.filter(r => r.reference === reference.trim()).map(r => r.version));
          await api.post(`/calls/${data.call.id}/institutional-register`, {
            kind: 'cpd',
            reference: reference.trim(),
            expected_version: expected,
            payload: {
              modality: context.modality,
              application_id: data.application.id,
              document_id: doc.id,
              issued_on: v.issued_on,
              score: v.score,
              components: Object.fromEntries(context.definition.merit_components.map(c => [c.key, v[c.key]])),
              notes: v.notes,
              ...(v.difference_reason ? {
                difference_reason: v.difference_reason
              } : {})
            }
          });
          reload();
          setStatus('Respaldo conservado. El puntaje del expediente se gestiona mediante su procedimiento existente.');
        }}
      >
        <label>Referencia CPD<input
            value={reference}
            onChange={e => setReference(e.target.value)}
            required
            maxLength="100"
          /></label>
        <label>Fecha de emisión<input name="issued_on" type="date" required /></label>
        <label>Puntaje consignado en el formulario /40<input name="score" type="number" min="0" max="40" step="0.01" required /></label>
        {context.definition.merit_components.map(c => <label key={c.key}>{c.label} /{c.maximum}<input name={c.key} type="number" min="0" max={c.maximum} step="0.01" required />{c.ambiguity && <small>{c.ambiguity}</small>}</label>)}
        <label>Fundamento si la suma difiere del CPD<textarea name="difference_reason" maxLength="3000" /></label>
        <label>Verificación documental y observaciones<textarea name="notes" minLength="10" maxLength="3000" required /></label>
      </Form>
      <p role="status">{status}</p>
    </details>}
    {!doc && <p>Falta adjuntar el formulario auténtico. Su disponibilidad depende de CPD.</p>}
  </section>;
}
