import React,{useState} from 'react';
import {Link,useNavigate,useParams} from 'react-router-dom';
import {api,base} from './api';
import {useData,Loading,Form} from './shared';

const status=s=>({draft:'Borrador de preparación',under_review:'En revisión técnica',technically_reviewed:'Revisión técnica registrada',observed:'Observado',reviewed:'Revisado técnicamente',MISSING:'Sin evidencia',DOCUMENTED_UNVERIFIED:'Documentado, sin validación institucional'})[s]||s;
export function InstitutionalIndex(){
  const [d,error]=useData('/institutional/dossiers');
  const [call,setCall]=useState('');
  const navigate=useNavigate();
  if(!d)return <Loading error={error}/>;
  const selected=d.calls.find(c=>String(c.id)===call);
  return <><h1>Preparación institucional</h1>
    <p className="notice">Este espacio conserva fuentes y revisiones técnicas. No abre concursos oficiales ni acredita autoridades o estudiantes. La convocatoria vinculada conserva su modo y sus datos.</p>
    <section className="card"><h2>Nuevo expediente de preparación</h2>
      <Form button="Crear borrador de preparación" onSubmit={async f=>{const r=await api.post('/institutional/dossiers',Object.fromEntries(f));navigate('/institucional/'+r.data.id);}}>
        <label>Título del expediente<input name="title" required maxLength="150"/></label>
        <label>Convocatoria de referencia<select name="call_id" required value={call} onChange={e=>setCall(e.target.value)}>
          <option value="">Seleccionar convocatoria de mi ámbito</option>{d.calls.map(c=><option key={c.id} value={c.id}>{c.title} · {c.mode}</option>)}
        </select></label>
        <label>Otra autoridad para revisión técnica<select key={call} name="reviewer_id" required disabled={!selected?.reviewers.length}>
          <option value="">Seleccionar responsable distinto del autor</option>{selected?.reviewers.map(u=><option key={u.id} value={u.id}>{u.name}</option>)}
        </select></label>
        {selected&&!selected.reviewers.length&&<p>Falta otra cuenta con responsabilidad de autoridad vigente en esta convocatoria. El administrador debe asignarla mediante el procedimiento existente. No se permite autorrevisión.</p>}
      </Form>
    </section>
    <section className="card"><h2>Expedientes de mi responsabilidad</h2>{d.dossiers.map(x=><p key={x.id}><Link to={'/institucional/'+x.id}>{x.title}</Link> · {status(x.status)} · versión {x.version}</p>)}{!d.dossiers.length&&<p>No hay expedientes asignados.</p>}</section>
  </>;
}
export function InstitutionalDossier(){
  const {id}=useParams();
  const [d,error,reload]=useData('/institutional/dossiers/'+id);
  if(!d)return <Loading error={error}/>;
  const expected_version=d.dossier.version;
  return <><Link to="/institucional">Volver a preparación institucional</Link><h1>{d.dossier.title}</h1>
    <p>{status(d.dossier.status)} · versión {expected_version}</p>
    <div className="notice"><strong>No habilitado para concursos reales.</strong> {d.readiness.notice}</div>
    <section className="card"><h2>Condiciones de habilitación pendientes</h2>
      <ul>{d.readiness.gates.map(g=><li key={g.id}>{g.id} · {g.name}: <strong>{status(g.state)}</strong></li>)}</ul>
      <p>Incluso con diez condiciones documentadas, la carga de archivos y una revisión técnica no sustituyen aprobación institucional ni las capacidades aún pendientes del motor oficial.</p>
    </section>
    {d.can_edit&&<section className="card"><h2>Conservar una fuente o nueva versión</h2>
      <Form button="Guardar versión de fuente" onSubmit={async f=>{f.append('expected_version',String(expected_version));await api.post('/institutional/dossiers/'+id+'/sources',f);reload();}}>
        <label>Referencia estable<input name="reference" required maxLength="100" pattern="[A-Za-z0-9._-]+" placeholder="RV-021-2023"/></label>
        <p>Reutiliza la misma referencia para añadir una versión; se conserva la anterior. Registrar una nueva versión no demuestra su vigencia.</p>
        <label>Título de la fuente<input name="title" required maxLength="180"/></label>
        <label>Condición relacionada<select name="gate" required>{d.readiness.gates.map(g=><option key={g.id} value={g.id}>{g.id} · {g.name}</option>)}</select></label>
        <label>Fecha que figura en el documento<input type="date" name="issued_on" required/></label>
        <label>Páginas o artículos a cotejar<input name="pages" required maxLength="80"/></label>
        <label>Clasificación<select name="classification"><option value="historical_reference">Referencia histórica</option><option value="proposed_rule">Regla propuesta, no validada</option></select></label>
        <label>Notas de contraste y asuntos pendientes<textarea name="notes" required maxLength="5000"/></label>
        <label>Fuente PDF, JPG o PNG (máximo 15 MB)<input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required/></label>
      </Form>
      <Form button="Enviar a revisión técnica" onSubmit={async()=>{await api.post('/institutional/dossiers/'+id+'/submission',{expected_version});reload();}}><p>Se conservará una copia inmutable de los metadatos y sus huellas. Las condiciones sin resolver seguirán pendientes.</p></Form>
    </section>}
    <section className="card"><h2>Fuentes y versiones conservadas</h2>
      {d.readiness.sources.map(s=><article key={s.id}><h3>{s.reference} · versión {s.version}</h3><p>{s.title} · {s.issued_on} · {s.pages} · {s.classification==='historical_reference'?'Referencia histórica':'Propuesta no validada'}</p><p>{s.notes}</p><p>SHA-256: <code style={{overflowWrap:'anywhere'}}>{s.sha256}</code></p><a href={base+'/api/v1/institutional/sources/'+s.id+'/download'}>Descargar fuente #{s.id}</a></article>)}
      {!d.readiness.sources.length&&<p>Todavía no hay fuentes conservadas.</p>}
    </section>
    {d.can_review&&<section className="card"><h2>Revisión por otra autoridad del ámbito</h2>
      <Form button="Registrar revisión técnica" onSubmit={async f=>{await api.post('/institutional/dossiers/'+id+'/review',{...Object.fromEntries(f),expected_version});reload();}}>
        <label>Decisión técnica<select name="decision"><option value="observed">Observar y devolver a borrador</option><option value="reviewed">Registrar revisión técnica completada</option></select></label>
        <label>Fundamento de la revisión<textarea name="reason" required minLength="10" maxLength="5000"/></label>
        <p>Esta decisión no acredita tu competencia institucional ni autoriza la operación oficial.</p>
      </Form>
    </section>}
    <section className="card"><h2>Envíos y revisiones anteriores</h2>
      {d.submissions.map(s=><article key={s.id}><h3>Envío #{s.id} · versión {s.dossier_version}</h3><p>{s.created_at}</p><p>SHA-256 del texto serializado: <code style={{overflowWrap:'anywhere'}}>{s.sha256}</code></p><details><summary>Consultar metadatos congelados</summary><pre style={{whiteSpace:'pre-wrap',overflowWrap:'anywhere'}}>{JSON.parse(s.snapshot.serialized)&&JSON.stringify(JSON.parse(s.snapshot.serialized),null,2)}</pre></details>
        {d.reviews.filter(r=>r.submission_id===s.id).map(r=><p key={r.id}>{status(r.decision)}: {r.reason} · {r.created_at}</p>)}
      </article>)}
      {!d.submissions.length&&<p>Todavía no se solicitó revisión.</p>}
    </section>
  </>;
}
