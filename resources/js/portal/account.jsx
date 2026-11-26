import React, { useContext } from 'react';
import { Session } from './shared';
import { base, label } from './api';
import { roleName } from './workspaces';
export function Account() {
  const {
    session
  } = useContext(Session);
  const student = session.user.role === 'student';
  const guides = [];
  if (student) guides.push(['Postulante', 'Abre Convocatorias, elige una oferta con recepción vigente e inicia tu postulación. Carga cada documento y confirma el envío. Sigue la revisión en Mis postulaciones. Una subsanación necesita una ventana autorizada; un retiro o una renuncia requieren decisión del responsable.']);
  if (session.roles.includes('reviewer')) guides.push(['Revisor', 'Abre Revisión de expedientes y selecciona un expediente enviado y coteja cada documento y requisito. Registra el motivo de la revisión y confirma la habilitación. Los documentos observados se corrigen mediante versiones y una nueva revisión. Registra el respaldo CPD sin inventar una rúbrica oficial.']);
  if (session.roles.includes('evaluator')) guides.push(['Evaluador', 'Abre Mis evaluaciones y consulta únicamente tus ofertas asignadas y registra las notas de tus áreas. Un borrador se puede completar; una nota confirmada requiere rectificación para cambiar. El detalle documental reservado no forma parte de tus permisos de evaluación.']);
  if (session.user.role === 'authority' || session.roles.includes('authority')) guides.push(['Autoridad', (session.user.role === 'authority' ? 'Puedes crear borradores de convocatoria. ' : '') + 'Configura tus convocatorias autorizadas y conduce sus etapas. Decide rectificaciones y solicitudes de participación con separación de responsables. Aprueba una publicación con acta. Un empate por las últimas plazas requiere examen y decisión de comisión; el sistema no inventa un ganador.']);
  if (session.user.role === 'admin') guides.push(['Administrador técnico', 'Usa Administración para crear cuentas, editar perfiles y asignar responsabilidades por convocatoria con fechas de vigencia. Puedes revocar permisos y administrar tipos de auxiliatura. Esta cuenta no concede por sí misma facultades para evaluar ni aprobar plazas.']);
  return <>
    <h1>Mi cuenta y ayuda</h1>
    <section className="card">
      <h2>{session.user.name}</h2>
      <p>{session.user.email}</p>
      <p>{session.user.faculty} · {session.user.career}</p>
      <p>Perfil de trabajo: {roleName(session)} · Responsabilidades: {session.roles.map(label).join(', ') || 'Sin responsabilidad académica adicional'}</p>
    </section>
    <section className="card">
      <h2>Fuentes originales y formularios</h2>
      <p>Las convocatorias históricas incluyen sus PDF originales, asignaturas, requisitos y cronogramas. Los formularios auténticos de méritos se solicitan al CPD Facultativo; las muestras del entorno de práctica no son formularios oficiales.</p>
      <ul>
        {[['resolucion', 'Resolución Vicerrectoral 021/2023'], ['convocatoria-01', 'Primera convocatoria 01/2026'], ['convocatoria-02', 'Segunda convocatoria 02/2026']].map(([key, title]) => <li key={key}>
          <a href={`${base}/api/v1/reference-sources/${key}`}>Descargar original: {title}</a>
        </li>)}
      </ul>
    </section>
    <section className="card">
      <h2>Mi guía de trabajo</h2>
      {guides.map(([title, text]) => <section key={title}><h3>{title}</h3><p>{text}</p></section>)}
      {!guides.length && <p>No tienes una responsabilidad académica vigente. El administrador puede asignarla cuando corresponda.</p>}
      <p>
        {session.training_enabled ? 'Las operaciones de entrenamiento no equivalen a una designación institucional.' : 'La preparación documental no acredita autorización para gestionar concursos oficiales.'}
      </p>
    </section>
    {student && session.training_enabled && <section className="card">
      <h2>Práctica académica de pagos</h2>
      <p>Este laboratorio cumple el ejercicio de INF513. Es independiente de la selección de auxiliares y no exige pagos para postular.</p>
      <a href={base + '/p2/simulacion'}>Abrir pagos simulados</a>
    </section>}
    {student && session.training_enabled && <section className="card">
      <h2>Entrenamiento separado</h2>
      <p>Los archivos ficticios se encuentran únicamente en el espacio de práctica; no sustituyen los documentos exigidos por la facultad.</p>
      <a href={base + '/p2/entrenamiento'}>Abrir entrenamiento documental</a>
    </section>}
  </>;
}

export function TrainingSamples() {
  const {
    session
  } = useContext(Session);
  if (!session.training_enabled || session.user.role !== 'student') return <p>Este espacio está disponible únicamente para postulantes del entorno de entrenamiento.</p>;
  const samples = [['carta', 'Carta de solicitud'], ['cpd', 'Formulario CPD'], ['ci', 'Identidad'], ['academico', 'Respaldo académico'], ['autonomia', 'Constancia requerida'], ['cv', 'Currículum y respaldos']];
  return <>
    <h1>Entrenamiento documental</h1>
    <p className="notice">Archivos exclusivamente ficticios. No acreditan identidad, matrícula, CPD ni autonomía y no deben presentarse en un concurso real.</p>
    <section className="card">
      <h2>Muestras PDF para practicar</h2>
      <p>Todos los archivos llevan la marca MUESTRA y corresponden solo a ejercicios técnicos.</p>
      <ul>
        {samples.map(([kind, title]) => <li key={kind}>
          <a href={base + '/ejemplos/documentos/' + kind}>Descargar muestra: {title}</a>
        </li>)}
      </ul>
    </section>
  </>;
}
