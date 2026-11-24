import axios from 'axios';
export const base = document.querySelector('meta[name="app-base"]')?.content || '';
export const api = axios.create({baseURL: `${base}/api/v1`, withCredentials: true, headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
export async function csrf() { const {data} = await api.get('/csrf'); api.defaults.headers.common['X-CSRF-TOKEN'] = data.token; }
export function message(error) {
  const status=error.response?.status;
  const data=error.response?.data;
  if(status>=500)return 'El servicio no pudo completar la operación. Intenta nuevamente.';
  const known={
    401:'Tu sesión terminó o cambiaron tus permisos. Inicia sesión nuevamente.',
    403:'No tienes permiso para realizar esta acción en este ámbito.',
    404:'El recurso o la página solicitada no están disponibles.',
    423:'El archivo está en cuarentena y no puede descargarse.',
    413:'El archivo o la solicitud supera el tamaño permitido.',
    419:'La sesión de seguridad venció. Recarga e inicia sesión.',
    429:'Demasiadas solicitudes. Espera un momento y vuelve a intentar.'
  };
  if(known[status])return known[status];
  if(status===422&&data?.errors)return Object.values(data.errors).flat().join(' ');
  return data?.message||'No se pudo conectar con el servidor.';
}
export const labels = {draft:'Borrador', submitted:'Enviada', pending:'Pendiente', enabled:'Habilitado', disabled:'Inhabilitado', published:'Recepción publicada', closed:'Recepción cerrada', evaluation:'Evaluación', results:'Resultados', archived:'Archivada', active:'Activa', withdrawn:'Retirada', resigned:'Renunciada', selected:'Seleccionado DEMO', not_selected:'Fuera de cupo', under_review:'Resultados en revisión', meets:'Cumple', observed:'Observado', fails:'No cumple', confirmed:'Confirmada', corrected:'Rectificada', paid:'Simulación exitosa', failed:'Simulación fallida', student:'Postulante', staff:'Personal/docente', reviewer:'Revisor', evaluator:'Evaluador', authority:'Autoridad', admin:'Administrador técnico'};
Object.assign(labels,{open:'Abierta',reviewed:'Revisada',expired:'Vencida',approved:'Aprobada',rejected:'Rechazada',scheduled:'Programado',resolved:'Resuelto',continued:'Ronda continuada',superseded:'Ronda conservada',withdrawal:'Retiro de postulación',renunciation:'Renuncia a plaza',accept:'Aceptación DEMO',defer:'Decisión diferida',pre_amendment_review:'Revisión anterior a la subsanación',faculty:'Facultad',career:'Carrera'});
export const label = key => /^expired_window_[1-9][0-9]*$/.test(key||'')?'Cierre de ventana vencida':labels[key]||key;
