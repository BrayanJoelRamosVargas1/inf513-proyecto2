import React,{useEffect,useState,createContext} from 'react';
import {api,base,message} from './api';
export const Session=createContext(null);
export class ErrorBoundary extends React.Component {state={error:false};static getDerivedStateFromError(){return {error:true};}render(){return this.state.error?<main><h1>No se pudo mostrar esta pantalla</h1><a href={`${base}/p2`}>Volver al inicio</a></main>:this.props.children;}}
export function Alert({text}) {return text?<div role="alert" className="alert">{text}</div>:null;}
export function useData(path){const [data,set]=useState(null),[error,err]=useState(''),[version,bump]=useState(0);useEffect(()=>{let alive=true;set(null);err('');api.get(path).then(r=>{if(alive)set(r.data);}).catch(e=>{if(alive)err(message(e));});return()=>{alive=false;};},[path,version]);return [data,error,()=>bump(x=>x+1)];}
export function Loading({error}){return error?<Alert text={error}/>:<p role="status">Cargando información…</p>;}
export function Form({children,onSubmit,button='Guardar',className=''}){const [error,set]=useState(''),[busy,lock]=useState(false);return <form className={className} onSubmit={async e=>{e.preventDefault();if(busy)return;const el=e.currentTarget;set('');lock(true);try{await onSubmit(new FormData(el),el);}catch(err){set(message(err));}finally{lock(false);}}}><Alert text={error}/>{children}<button disabled={busy}>{busy?'Procesando…':button}</button></form>;}

export function DocumentLink({doc}){
  const [details,showDetails]=useState(false);
  const state=doc.security?.state??'pending';
  const names={pending:'Análisis pendiente',clean:'Análisis válido',blocked:'Archivo bloqueado',error:'Análisis no disponible',expired:'Análisis vencido'};
  const allowed=doc.security?.download_allowed===true;
  return <span>{allowed?<><a target="_blank" rel="noopener" href={`${base}/api/v1/documents/${doc.id}`}>Abrir {doc.name}</a>{' · '}<a href={`${base}/api/v1/documents/${doc.id}/download`}>Descargar original: {doc.name}</a></>:<span>{doc.name}</span>}{' · '}v{doc.version}{' · '}<span role="status">{names[state]??'Análisis pendiente'}</span>{allowed&&<> {' · '}<button type="button" aria-expanded={details} onClick={()=>showDetails(v=>!v)}>{details?'Ocultar detalles':'Ver detalles'}: {doc.name}</button>{details&&<DocumentDetails id={doc.id}/>}</>}</span>;
}
function DocumentDetails({id}){
  const [d,error]=useData(`/documents/${id}/metadata`);
  if(!d)return <span role={error?'alert':'status'}>{error||'Cargando detalles…'}</span>;
  return <span className="document-details"><br/><strong>Original cargado, sin conversión ni reducción</strong><br/>Formato: {d.mime==='application/pdf'?'PDF':d.mime==='image/png'?'Imagen PNG':'Imagen JPG'} · Tamaño: {(d.size_bytes/1024).toLocaleString('es-BO',{maximumFractionDigits:1})} KB{d.width&&<> · {d.width} × {d.height} píxeles</>}<br/>Cargado: {d.created_at} · Versión {d.version}{d.small_image&&<><br/><strong>Imagen de tamaño reducido. Comprueba su legibilidad; si no se puede leer, registra una observación y solicita subsanación.</strong></>}<br/>El análisis de seguridad no confirma que el contenido cumpla los requisitos. El revisor debe abrir el documento y cotejarlo.</span>;
}

export function DocumentValidation({validation}){return <section className="card" role="status"><h2>Validación documental técnica</h2><p>{validation?.ready===true?'Validación documental vigente':'Validación documental pendiente'}</p><p>Las nuevas evaluaciones requieren archivos disponibles, íntegros y con análisis vigente. La decisión académica y los resultados históricos se conservan.</p></section>;}
