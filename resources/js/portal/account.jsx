import React,{useContext} from 'react';
import {Session} from './shared';
import {base,label} from './api';
import {roleName} from './workspaces';

export function Account(){
  const {session}=useContext(Session);
  const student=session.user.role==='student';
  const guides=[];
  if(student)guides.push(['Postulante','Abre Convocatorias, elige una oferta con recepción vigente e inicia tu postulación. Carga cada documento y confirma el envío. Sigue la revisión en Mis postulaciones. Una subsanación necesita una ventana autorizada; un retiro o una renuncia requieren decisión del responsable.']);
  if(session.roles.includes('reviewer'))guides.push(['Revisor','Abre Revisión de expedientes y selecciona un expediente enviado y coteja cada documento y requisito. Registra el motivo de la revisión y confirma la habilitación. Los documentos observados se corrigen mediante versiones y una nueva revisión. Registra el respaldo CPD sin inventar una rúbrica oficial.']);
  if(session.roles.includes('evaluator'))guides.push(['Evaluador','Abre Mis evaluaciones y consulta únicamente tus ofertas asignadas y registra las notas de tus áreas. Un borrador se puede completar; una nota confirmada requiere rectificación para cambiar. El detalle documental reservado no forma parte de tus permisos de evaluación.']);
  if(session.user.role==='authority'||session.roles.includes('authority'))guides.push(['Autoridad',(session.user.role==='authority'?'Puedes crear borradores de convocatoria. ':'')+'Configura tus convocatorias autorizadas y conduce sus etapas. Decide rectificaciones y solicitudes de participación con separación de responsables. Aprueba una publicación con acta. Un empate por las últimas plazas requiere examen y decisión de comisión; el sistema no inventa un ganador.']);
  if(session.user.role==='admin')guides.push(['Administrador técnico','Usa Administración para crear cuentas, editar perfiles y asignar responsabilidades por convocatoria con fechas de vigencia. Puedes revocar permisos y administrar tipos de auxiliatura. Esta cuenta no concede por sí misma facultades para evaluar ni aprobar plazas.']);
  const samples=[['carta','Carta de solicitud'],['cpd','Formulario CPD'],['ci','Identidad'],['academico','Respaldo académico'],['autonomia','Constancia requerida'],['cv','Currículum y respaldos']];
  return <><h1>Mi cuenta y ayuda</h1>
    <section className="card"><h2>{session.user.name}</h2><p>{session.user.email}</p><p>{session.user.faculty} · {session.user.career}</p><p>Perfil de trabajo: {roleName(session)} · Responsabilidades: {session.roles.map(label).join(', ')||'Sin responsabilidad académica adicional'}</p></section>
    <section className="card"><h2>Mi guía de trabajo</h2>
      {guides.map(([title,text])=><section key={title}><h3>{title}</h3><p>{text}</p></section>)}
      {!guides.length&&<p>No tienes una responsabilidad académica vigente. El administrador puede asignarla cuando corresponda.</p>}
      <p>Las decisiones de esta plataforma son DEMO. No equivalen a una designación institucional.</p>
    </section>
    <section className="card"><h2>Práctica académica de pagos</h2><p>Este laboratorio cumple el ejercicio de INF513. Es independiente de la selección de auxiliares y no exige pagos para postular.</p><a href={base+'/p2/simulacion'}>Abrir pagos simulados</a></section>
    {student&&<section className="card"><h2>Muestras PDF para practicar</h2>
      <p>Son PDF A4 marcados MUESTRA. No acreditan identidad, matrícula ni CPD y no deben presentarse como documentación real.</p>
      <ul>{samples.map(([kind,title])=><li key={kind}><a href={base+'/ejemplos/documentos/'+kind} target="_blank" rel="noopener">Descargar muestra: {title}</a></li>)}</ul>
    </section>}
  </>;
}
