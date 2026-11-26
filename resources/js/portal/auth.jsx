import React, { useContext, useState } from 'react';
import { api, csrf } from './api';
import { Form, Session } from './shared';
import { FooterVisitCounter } from './shell';

export function Auth() {
  const {
    setSession
  } = useContext(Session);
  const [register, setRegister] = useState(false);
  return <div className="auth-page">
    <a className="skip" href="#contenido">Ir al formulario</a>
    <div className="auth-shell">
      <section className="auth-story" aria-label="Presentación del portal">
        <div>
          <div className="sidebar-brand">
            <span className="brand-emblem">F</span>
            <span className="brand-copy">
              <strong>FICCT</strong>
              <small>Universidad Autónoma Gabriel René Moreno</small>
            </span>
          </div>
          <p className="eyebrow">Portal de selección de auxiliares</p>
          <h1>Tu postulación.<br />Cada paso, más claro.</h1>
          <p>Un espacio para consultar convocatorias, organizar tus documentos y acompañar tu proceso de selección.</p>
          <ol className="auth-journey">
            <li>
              <span>01</span>
              <div>
                <strong>Encuentra tu convocatoria</strong>
                <small>Revisa las plazas, fechas y requisitos.</small>
              </div>
            </li>
            <li>
              <span>02</span>
              <div>
                <strong>Prepara tu expediente</strong>
                <small>Adjunta tus documentos y confirma el envío.</small>
              </div>
            </li>
            <li>
              <span>03</span>
              <div>
                <strong>Sigue tu proceso</strong>
                <small>Consulta observaciones y resultados autorizados.</small>
              </div>
            </li>
          </ol>
        </div>
        <p className="auth-note">Fuentes institucionales reales y procesos de demostración. Las operaciones DEMO no tienen validez oficial.</p>
      </section>
      <main className="auth-stage" id="contenido" tabIndex="-1">
        <section
          className="auth-card"
          aria-label={register ? 'Registro de postulante' : 'Ingreso al portal'}
        >
          <p className="eyebrow">Bienvenido al portal</p>
          <h2>{register ? 'Crea tu cuenta' : 'Iniciar sesión'}</h2>
          <p className="auth-intro">
            {register ? 'Regístrate como postulante para utilizar el entorno de demostración.' : 'Ingresa con tu cuenta para continuar en tu espacio de trabajo.'}
          </p>
          <Form
            button={register ? 'Crear cuenta de postulante' : 'Entrar al sistema'}
            onSubmit={async f => {
              await csrf();
              const {
                data
              } = await api.post(register ? '/register' : '/login', Object.fromEntries(f));
              api.defaults.headers.common['X-CSRF-TOKEN'] = data.token;
              setSession(data);
            }}
          >
            {register && <>
              <label>Nombre completo<input name="name" required maxLength="100" autoComplete="name" /></label>
              <label>Facultad<input name="faculty" required maxLength="80" /></label>
              <label>Carrera<input name="career" required maxLength="80" /></label>
            </>}
            <label>Correo electrónico<input
                type="email"
                name="email"
                required
                maxLength="150"
                autoComplete="username"
                placeholder="nombre@correo.com"
              /></label>
            <label>Contraseña<input
                name="password"
                type="password"
                required
                minLength={register ? 12 : 1}
                autoComplete={register ? 'new-password' : 'current-password'}
                placeholder="Tu contraseña"
              /></label>
            {register && <label>Confirmar contraseña<input
                name="password_confirmation"
                type="password"
                required
                minLength="12"
                autoComplete="new-password"
              /></label>}
          </Form>
          <p className="auth-switch">
            {register ? '¿Ya tienes una cuenta?' : '¿Todavía no tienes cuenta?'}
          </p>
          <button type="button" className="secondary" onClick={() => setRegister(!register)}>
            {register ? 'Volver a ingresar' : 'Crear cuenta de postulante'}
          </button>
        </section>
      </main>
    </div>
    <FooterVisitCounter pathOverride={register ? '/registro' : '/ingresar'} isPublic />
  </div>;
}
