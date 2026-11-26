import React from 'react';
import { api, base, label } from './api';
import { CpdRecord } from './institutional-register';
import { DocumentLink, DocumentValidation, Form, Loading, useData } from './shared';
import { Link, useParams } from 'react-router-dom';

export function Application() {
  const {
    id
  } = useParams();
  const [d, error, reload] = useData('/applications/' + id);
  if (!d) return <Loading error={error} />;
  const a = d.application;
  const missing = Object.keys(d.offer.documents).filter(k => !d.documents.some(doc => doc.kind === k));
  const submit = async (action, data) => {
    await api.post(`/applications/${id}/${action}`, data);
    reload();
  };
  return <>
    <Link to={'/convocatorias/' + d.call.id}>← {d.call.title}</Link>
    <p className="eyebrow">
      {d.owner ? 'Mi postulación' : d.reviewer ? 'Revisión de expediente' : d.authority ? 'Seguimiento de resultados' : 'Evaluación asignada'}
    </p>
    <h1>{d.offer.title}</h1>
    <p>Expediente #{id} · {d.student.name} · {label(a.status)} · {label(a.eligibility)}</p>
    {d.student.document_number && <p>Cédula {d.student.document_number}{d.student.student_code ? ` · Registro ${d.student.student_code}` : ''}{d.reviewer ? ' · Contraste estos datos con la cédula y el histórico del expediente.' : ''}</p>}
    <p>{label(a.participation)}</p>
    <DocumentValidation validation={d.document_validation} />
    {(d.owner || d.authority) && <div className="grid">
      <section className="card">Méritos /40: {a.merit ?? 'Pendiente'}</section>
      <section className="card">Examen /60: {a.exam ?? 'Pendiente'}</section>
      <section className="card">Final /100: {a.final ?? 'Pendiente'}</section>
    </div>}
    {(d.owner || d.reviewer) && <section className="card">
      <h2>Expediente digital</h2>
      {a.status === 'submitted' && <p>
        <a href={`${base}/api/v1/applications/${id}/receipt`}>Descargar constancia interna de recepción</a>
      </p>}
      <p>
        {d.owner ? 'Carga los documentos requeridos y confirma el envío cuando estén completos. El análisis de seguridad puede continuar después del envío; espera su validación para abrir los archivos.' : 'Abre los documentos disponibles y contrástalos con cada requisito. Registra las observaciones y su fundamento; el análisis de seguridad no sustituye la revisión académica.'}
      </p>
      <button type="button" onClick={reload}>Actualizar estado de archivos</button>
      <ul>
        {Object.entries(d.offer.documents).map(([k, title]) => {
          const doc = d.documents.find(v => v.kind === k);
          return <li key={k}>{title}: {doc ? <><DocumentLink doc={doc} /></> : 'Falta adjuntar'}</li>;
        })}
      </ul>
      {d.can_upload && <>
        <Form button="Guardar archivo" onSubmit={f => submit('archivo', f)}>
          <label>Documento<select name="kind">
              {Object.entries(d.offer.documents).map(([k, t]) => <option key={k} value={k}>{t}</option>)}
            </select></label>
          <label>Archivo PDF, JPG o PNG · hasta 5 MB<input name="file" type="file" accept=".pdf,.png,.jpg,.jpeg" required /></label>
        </Form>
        {missing.length ? <p>Faltan {missing.length} documentos. Completa la carga para enviar.</p> : <Form button="Confirmar envío definitivo" onSubmit={() => submit('enviar', {})}>
          <p>Al enviar, el expediente queda conservado para revisión.</p>
        </Form>}
      </>}
    </section>}
    {d.criteria && Object.keys(d.criteria).length > 0 && <section className="card">
      <h2>Revisión y habilitación</h2>
      {a.eligibility_reason && <p>{a.eligibility_reason}</p>}
      {d.can_review ? <>
        <Form
          button="Guardar revisión por criterio"
          onSubmit={f => {
            const values = Object.fromEntries(f);
            const decisions = Object.fromEntries(Object.keys(d.criteria).map(k => [k, values[k]]));
            return submit('revision', {
              decisions,
              reason: values.reason
            });
          }}
        >
          {Object.entries(d.criteria).map(([k, t]) => <label key={k}>
            {t}
            <select
              name={k}
              defaultValue={d.reviews.find(x => x.criterion === k)?.decision ?? 'observed'}
            >
              <option value="observed">Observado</option>
              <option value="meets">Cumple</option>
              <option value="fails">No cumple</option>
              {d.conditional_criteria?.includes(k) && <option value="not_applicable">No aplica: requiere fundamento</option>}
            </select>
          </label>)}
          <label>Motivo y evidencia<textarea name="reason" minLength="10" maxLength="3000" required /></label>
        </Form>
        {d.can_enable === false && <p>Para habilitar, guarda los criterios obligatorios como Cumple, fundamenta los condicionales que no apliquen y espera una validación documental vigente. Puedes registrar una inhabilitación motivada cuando corresponda.</p>}
        <Form
          button="Confirmar habilitación"
          onSubmit={f => submit('habilitacion', Object.fromEntries(f))}
        >
          <label>Decisión<select name="enabled" required defaultValue="">
              <option value="" disabled>Seleccionar decisión</option>
              <option value="1" disabled={d.can_enable === false}>Habilitar</option>
              <option value="0">Inhabilitar</option>
            </select></label>
          <label>Motivo<textarea name="reason" minLength="10" maxLength="3000" required /></label>
        </Form>
      </> : <div>
        {d.reviews.length > 0 && <h3>Observaciones y fundamentos de revisión</h3>}
        <ul>
          {Object.entries(d.criteria).map(([k, t]) => {
            const review = d.reviews.find(x => x.criterion === k);
            return <li key={k}>
              <strong>{t}: {label(review?.decision ?? 'pending')}</strong>
              {review && <>
                <p>{review.reason}</p>
                <small>Revisado por cuenta #{review.user_id} · {review.updated_at}</small>
              </>}
            </li>;
          })}
        </ul>
      </div>}
    </section>}
    {d.can_review && d.reviews.length > 0 && <section className="card">
      <h2>Observaciones y fundamentos de revisión</h2>
      <ul>
        {d.reviews.map(r => <li key={r.criterion}>
          <strong>{d.criteria[r.criterion] ?? r.criterion}: {label(r.decision)}</strong>
          <p>{r.reason}</p>
          <small>Revisado por cuenta #{r.user_id} · {r.updated_at}</small>
        </li>)}
      </ul>
    </section>}
    <CpdRecord data={d} reload={reload} />
    {d.reviewer && !d.owner && a.merit != null && <section className="card">
      <h2>Méritos CPD registrados</h2>
      <p>{a.merit} / 40 puntos. Un cambio requiere rectificación autorizada.</p>
    </section>}
    {d.can_merit && <section className="card">
      <h2>Registro de méritos CPD</h2>
      <Form button="Confirmar méritos" onSubmit={f => submit('meritos', Object.fromEntries(f))}>
        <label>Referencia de respaldo<input name="reference" required maxLength="150" /></label>
        <label>Mérito certificado sobre 40<input name="score" type="number" min="0" max="40" step="0.01" required /></label>
      </Form>
    </section>}
    {d.can_grade && d.assignments.map(as => {
      const grade = d.grades.find(g => g.assignment_id === as.id);
      if (grade && grade.status !== 'draft') return null;
      return <section className="card" key={as.id}>
        <h2>Mi evaluación: {as.area}</h2>
        <Form
          button="Registrar nota"
          onSubmit={f => submit('nota', {
            ...Object.fromEntries(f),
            assignment_id: as.id
          })}
        >
          <label>Nota sobre 100<input
              type="number"
              name="score"
              min="0"
              max="100"
              step="0.01"
              required
              defaultValue={grade?.score ?? ''}
            /></label>
          <label>Acción<select name="intent">
              <option value="draft">Guardar borrador</option>
              <option value="confirm">Confirmar nota</option>
            </select></label>
        </Form>
      </section>;
    })}
    {d.grades.length > 0 && <section className="card">
      <h2>Calificaciones visibles</h2>
      <ul>
        {d.grades.map(g => <li key={g.id}>{g.area}: {g.score}/100 · {label(g.status)}{g.user_id && <small> · Registrada por cuenta #{g.user_id}. La reasignación conserva la autoría original.</small>}</li>)}
      </ul>
    </section>}
    {d.can_calculate && <section className="card">
      <Form button="Calcular resultado" onSubmit={() => submit('calcular', {})}>
        <p>Se requieren las notas confirmadas de todas las áreas. Las faltantes no equivalen a cero.</p>
      </Form>
    </section>}
    {!d.owner && !d.reviewer && !d.authority && d.assignments.length > 0 && d.call.status !== 'evaluation' && <section className="card">
      <h2>Disponibilidad de mi evaluación</h2>
      <p>El registro de notas se habilita durante la etapa de evaluación, para postulantes habilitados y áreas asignadas. Etapa actual: {label(d.call.status)}.</p>
    </section>}
    <section className="card">
      <h2>Seguimiento e historial</h2>
      {(d.owner || d.reviewer || d.authority) && <p>
        <Link to={`/postulaciones/${id}/participacion`}>Subsanaciones, retiro o renuncia →</Link>
      </p>}
      {(d.can_view_corrections ?? (d.reviewer || d.authority || !d.owner && d.assignments.length > 0 && d.grades.length > 0)) && <p>
        <Link to={`/postulaciones/${id}/rectificaciones`}>Rectificaciones e historial de calificaciones →</Link>
      </p>}
    </section>
  </>;
}
