import React, { useContext } from 'react';
import { label, isOperable } from './api';
import { Action, field, localDate, opts, reason, select } from './form-kit';
import { Loading, Session, useData } from './shared';
import { UserDirectory } from './user-directory';
import { Link, useNavigate, useParams } from 'react-router-dom';

const callFields = c => [field('title', 'Nombre', {
  value: c?.title,
  maxLength: 150
}), field('management_year', 'Gestión', {
  value: c?.management_year,
  type: 'number',
  min: 1900,
  max: 9999,
  required: false
}), field('call_number', 'Número de convocatoria', {
  value: c?.call_number,
  required: false,
  maxLength: 40
}), field('opens_at', 'Inicio de recepción', {
  value: localDate(c?.opens_at),
  type: 'datetime-local'
}), field('closes_at', 'Cierre de recepción', {
  value: localDate(c?.closes_at),
  type: 'datetime-local'
}), field('exam_at', 'Fecha del examen', {
  value: localDate(c?.exam_at),
  type: 'datetime-local'
}), select('scope', 'Ámbito', [['UAGRM', 'Toda UAGRM'], ['faculty', 'Facultad'], ['career', 'Carrera']], c?.scope), field('scope_value', 'Facultad o carrera del ámbito', {
  value: c?.scope_value,
  required: false
}), field('subject_limit', 'Límite de asignaturas', {
  value: c?.subject_limit,
  type: 'number',
  min: 1,
  max: 100,
  required: false
}), field('academic_period', 'Período académico', {
  value: c?.academic_period,
  required: false,
  maxLength: 60
}), field('reception_place', 'Lugar de recepción', {
  value: c?.reception_place,
  required: false,
  maxLength: 200
}), field('exam_place', 'Lugar del examen', {
  value: c?.exam_place,
  required: false,
  maxLength: 200
}), field('source', 'Fuente o decisión', {
  value: c?.source,
  type: 'textarea'
})];

export function CreateCall() {
  const navigate = useNavigate();
  const {
    session
  } = useContext(Session);
  if (session.user.role !== 'authority') return <p>No tienes permiso para crear convocatorias.</p>;
  return <>
    <h1>Crear convocatoria</h1>
    <p>Los requisitos y fechas se congelan al publicar.</p>
    <Action
      title="Borrador de convocatoria"
      endpoint="/calls"
      fields={callFields()}
      button="Crear borrador"
      after={r => navigate('/convocatorias/' + r.id + '/configuracion')}
    />
  </>;
}

const pairs = v => typeof v === 'string' ? Object.entries(JSON.parse(v)).map(([k, t]) => k + '=' + t).join('\n') : '';

export function CallConfiguration() {
  const {
    id
  } = useParams();
  const [d, error, reload] = useData('/calls/' + id + '/configuration');
  if (!d) return <Loading error={error} />;
  const draft = isOperable(d.call) && d.call.status === 'draft';
  const assign = isOperable(d.call) && ['draft', 'published', 'closed', 'evaluation'].includes(d.call.status);
  const offerFields = o => [field('title', 'Nombre de la oferta', {
    value: o?.title
  }), select('category_id', 'Tipo de auxiliatura', opts(d.categories), o?.category_id), field('seats', 'Número de plazas', {
    type: 'number',
    min: 0,
    max: 999,
    value: o?.seats
  }), select('modality', 'Modalidad', [['Presencial', 'Presencial'], ['Virtual', 'Virtual'], ['Servicio académico', 'Servicio académico'], ['Servicio social', 'Servicio social']], o?.modality), select('is_subject', '¿Es asignatura?', [['0', 'No'], ['1', 'Sí']], o?.is_subject ? '1' : '0'), {
    name: 'requirement_profile',
    label: 'Criterios documentales del régimen (no concede homologación)',
    options: [['', 'Conservar configuración actual'], ['asignatura', 'Docencia por asignatura · fuentes 2026'], ['sala', 'Docencia en sala de cómputo · fuentes 2026'], ['laboratorio', 'Laboratorio de servicio · fuentes 2026']],
    value: o?.requirement_profile ?? '',
    required: false
  }, field('documents', 'Documentos: una fila clave=nombre', {
    type: 'textarea',
    value: o ? pairs(o.documents) : 'carta=Carta de solicitud\ncpd=Respaldo CPD\nci=Identidad'
  }), field('requirements', 'Requisitos: una fila clave=criterio', {
    type: 'textarea',
    value: o ? pairs(o.requirements) : 'activo=Condición académica por revisar'
  }), field('weights', 'Pesos: una fila área=porcentaje', {
    type: 'textarea',
    value: o ? pairs(o.weights) : 'Software=100'
  }), field('theory_hours', 'Horas teóricas (opcional)', {
    type: 'number',
    min: 0,
    max: 200,
    required: false,
    value: o?.theory_hours
  }), field('practice_hours', 'Horas prácticas (opcional)', {
    type: 'number',
    min: 0,
    max: 200,
    required: false,
    value: o?.practice_hours
  }), field('approval_threshold', 'Nota mínima final de aprobación (opcional; vacío = sin mínimo)', {
    type: 'number',
    min: 0,
    max: 100,
    step: '0.01',
    required: false,
    value: o?.approval_threshold
  }), select('scheme', 'Composición del examen', [['AREAS_100', 'Áreas sobre 100'], ['COMPONENTS_100', 'Componentes sobre 100'], ['PENDING_COMPOSITION', 'Composición pendiente'], ...(o?.scheme?.startsWith('DEMO_') ? [[o.scheme, 'Composición de entrenamiento (' + o.scheme + ')']] : [])], o?.scheme), field('source', 'Fuente o decisión de configuración', {
    type: 'textarea',
    value: o?.source ?? 'Configuración sintética sin validez institucional.'
  })];
  return <>
    <Link to={'/convocatorias/' + id}>← Convocatoria</Link>
    <h1>Configuración y evaluadores</h1>
    <p>Fechas en hora de Bolivia (UTC−4). Cada área tiene un responsable; usa la reasignación para cambiarlo conservando el historial.</p>
    {draft ? <>
      <Action
        title="Editar borrador"
        endpoint={`/calls/${id}/configuration`}
        fields={callFields(d.call)}
        after={reload}
      />
      <Action
        title="Agregar oferta"
        endpoint={`/calls/${id}/offers`}
        fields={offerFields()}
        after={reload}
      />
    </> : <p>La configuración publicada se conserva como historial.</p>}
    {d.offers.map(o => <details className="card" key={o.id}>
      <summary>{o.title} · {o.seats} {o.seats === 1 ? 'plaza' : 'plazas'}</summary>
      {draft && <Action
        title="Editar oferta"
        endpoint={`/calls/${id}/offers`}
        fields={offerFields(o)}
        data={{
          offer_id: o.id
        }}
        after={reload}
      />}
      <p><Link to={'/ofertas/' + o.id + '/resultados'}>Clasificación y publicación</Link></p>
      {assign && d.teachers.length > 0 && Object.keys(JSON.parse(o.weights)).some(k => !d.assignments.some(a => a.offer_id === o.id && a.area === k)) && <Action
        title="Asignar evaluador"
        endpoint={`/offers/${o.id}/assignments`}
        fields={[select('area', 'Área', Object.keys(JSON.parse(o.weights)).filter(k => !d.assignments.some(a => a.offer_id === o.id && a.area === k)).map(k => [k, k])), select('user_id', 'Evaluador con permiso vigente', opts(d.teachers))]}
        after={reload}
      />}
      <ul>
        {d.assignments.filter(a => a.offer_id === o.id).map(a => <li key={a.id}>{a.area} · Responsable: {d.teachers.find(t => t.id === a.user_id)?.name ?? 'Cuenta #' + a.user_id} · v{a.version}{assign && d.teachers.some(t => t.id !== a.user_id) && <Action
            title="Reasignar con historial"
            endpoint={`/assignments/${a.id}/replacement`}
            fields={[select('user_id', 'Nuevo evaluador', opts(d.teachers.filter(t => t.id !== a.user_id))), reason]}
            data={{
              version: a.version
            }}
            after={reload}
          />}</li>)}
      </ul>
    </details>)}
  </>;
}

export function Management() {
  const {
    session
  } = useContext(Session);
  const [d, error, reload] = useData('/management');
  if (!d) return <Loading error={error} />;
  const roles = [['student', 'Postulante'], ['staff', 'Personal/docente'], ['authority', 'Autoridad']];
  return <>
    <h1>Administración técnica</h1>
    <p>Los permisos son específicos por convocatoria. Cambiarlos invalida las sesiones correspondientes. Las fechas se muestran y guardan en hora de Bolivia (UTC−4); conserva o modifica explícitamente su vigencia.</p>
    <Action
      title="Crear usuario"
      endpoint="/users"
      after={reload}
      fields={[field('name', 'Nombre'), field('email', 'Correo', {
        type: 'email'
      }), field('password', 'Contraseña temporal', {
        type: 'password',
        minLength: 12
      }), select('role', 'Perfil', roles), field('faculty', 'Facultad'), field('career', 'Carrera')]}
    />
    <Action
      title="Asignar responsabilidad"
      endpoint="/permissions"
      after={reload}
      fields={[select('user_id', 'Usuario', opts(d.users.filter(u => u.id !== session.user.id))), select('call_id', 'Convocatoria', opts(d.calls)), select('role', 'Responsabilidad', [['reviewer', 'Revisor'], ['evaluator', 'Evaluador'], ['authority', 'Autoridad']]), field('starts_at', 'Desde', {
        type: 'datetime-local',
        required: false
      }), field('expires_at', 'Hasta', {
        type: 'datetime-local',
        required: false
      }), reason]}
    />
    <UserDirectory users={d.users}>
      {users => users.map(u => <details className="card" key={u.id}>
        <summary>{u.name} · {label(u.role)} · {u.active ? 'Activo' : 'Desactivado'}</summary>
        <Action
          title="Editar perfil"
          endpoint={`/users/${u.id}/profile`}
          after={reload}
          data={{
            version: u.profile_version
          }}
          fields={[field('name', 'Nombre', {
            value: u.name
          }), field('email', 'Correo', {
            value: u.email,
            type: 'email'
          }), field('faculty', 'Facultad', {
            value: u.faculty
          }), field('career', 'Carrera', {
            value: u.career
          }), select('role', 'Perfil', [...roles, ['admin', 'Administrador técnico']], u.role), select('active', 'Estado', [['1', 'Activo'], ['0', 'Desactivado']], u.active ? '1' : '0'), reason]}
        />
      </details>)}
    </UserDirectory>
    <section>
      <h2>Permisos registrados</h2>
      {d.memberships.map(m => <details className="card" key={m.id}>
        <summary>{d.users.find(u => u.id === m.user_id)?.name} · Convocatoria #{m.call_id} · {label(m.role)} · {m.revoked_at ? 'Revocado' : 'Consultar vigencia'}</summary>
        <p>Inicio: {m.starts_at ?? 'Sin restricción'} · Vencimiento: {m.expires_at ?? 'Sin vencimiento'}</p>
        {!m.revoked_at && <Action
          title="Revocar permiso"
          endpoint={`/permissions/${m.id}/revoke`}
          fields={[reason]}
          data={{
            version: m.version
          }}
          after={reload}
        />}
        {m.user_id === session.user.id ? <p>Otra cuenta administradora debe renovar tus responsabilidades.</p> : <Action
          title="Renovar o cambiar vigencia"
          endpoint="/permissions"
          data={{
            user_id: m.user_id,
            call_id: m.call_id,
            role: m.role,
            version: m.version
          }}
          fields={[field('starts_at', 'Desde', {
            type: 'datetime-local',
            required: false,
            value: localDate(m.starts_at)
          }), field('expires_at', 'Hasta', {
            type: 'datetime-local',
            required: false,
            value: localDate(m.expires_at)
          }), reason]}
          after={reload}
        />}
      </details>)}
    </section>
    <Action
      title="Crear tipo de auxiliatura"
      endpoint="/categories"
      fields={[field('name', 'Nombre'), select('regime', 'Régimen', [['docencia', 'Docencia'], ['laboratorio', 'Laboratorio']]), select('active', 'Estado', [['1', 'Activo'], ['0', 'Inactivo']])]}
      after={reload}
    />
    {d.categories.map(c => <details className="card" key={c.id}>
      <summary>{c.name} · {c.regime}</summary>
      <Action
        title="Editar tipo"
        endpoint={`/categories/${c.id}/configuration`}
        fields={[field('name', 'Nombre', {
          value: c.name
        }), select('regime', 'Régimen', [['docencia', 'Docencia'], ['laboratorio', 'Laboratorio']], c.regime), select('active', 'Estado', [['1', 'Activo'], ['0', 'Inactivo']], c.active ? '1' : '0'), reason]}
        after={reload}
      />
    </details>)}
  </>;
}
