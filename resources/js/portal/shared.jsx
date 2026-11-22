import React,{useEffect,useState,createContext} from 'react';
import {api,base,message} from './api';
export const Session=createContext(null);
export class ErrorBoundary extends React.Component {state={error:false};static getDerivedStateFromError(){return {error:true};}render(){return this.state.error?<main><h1>No se pudo mostrar esta pantalla</h1><a href={`${base}/p2`}>Volver al inicio</a></main>:this.props.children;}}
export function Alert({text}) {return text?<div role="alert" className="alert">{text}</div>:null;}
export function useData(path){const [data,set]=useState(null),[error,err]=useState(''),[version,bump]=useState(0);useEffect(()=>{let alive=true;set(null);err('');api.get(path).then(r=>{if(alive)set(r.data);}).catch(e=>{if(alive)err(message(e));});return()=>{alive=false;};},[path,version]);return [data,error,()=>bump(x=>x+1)];}
export function Loading({error}){return error?<Alert text={error}/>:<p role="status">Cargando información…</p>;}
export function Form({children,onSubmit,button='Guardar',className=''}){const [error,set]=useState(''),[busy,lock]=useState(false);return <form className={className} onSubmit={async e=>{e.preventDefault();if(busy)return;const el=e.currentTarget;set('');lock(true);try{await onSubmit(new FormData(el),el);}catch(err){set(message(err));}finally{lock(false);}}}><Alert text={error}/>{children}<button disabled={busy}>{busy?'Procesando…':button}</button></form>;}

export function DocumentLink({doc}){const state=doc.security?.state??'pending';const names={pending:'Análisis pendiente',clean:'Análisis válido',blocked:'Archivo bloqueado',error:'Análisis no disponible',expired:'Análisis vencido'};return <span>{doc.security?.download_allowed===true?<a target="_blank" rel="noopener" href={`${base}/api/v1/documents/${doc.id}`}>Abrir {doc.name}</a>:<span>{doc.name}</span>} · v{doc.version} · <span role="status">{names[state]??'Análisis pendiente'}</span></span>;}
