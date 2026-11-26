import React, { useContext } from 'react';
import { api } from './api';
import { Form, Session } from './shared';

/** Pantalla obligatoria del primer ingreso: la clave inicial la conoce quien creó la cuenta y debe cambiarse. */
export function ForcedPassword() {
  const {
    session,
    setSession
  } = useContext(Session);
  return <main className="auth-page" id="contenido" tabIndex="-1">
    <div className="auth-shell">
      <section className="auth-form-panel">
        <h1>Cambie su clave para continuar</h1>
        <p>{session.user.name}, su cuenta se creó con una clave inicial que conoce quien la registró. Defina una clave propia de al menos 12 caracteres para empezar a usar el sistema.</p>
        <Form
          button="Guardar clave nueva"
          onSubmit={async f => {
            await api.post('/me/password', Object.fromEntries(f));
            setSession({
              ...session,
              must_change_password: false
            });
          }}
        >
          <label>Clave actual<input name="current_password" type="password" autoComplete="current-password" required /></label>
          <label>Clave nueva<input name="password" type="password" autoComplete="new-password" minLength={12} required /></label>
          <label>Repita la clave nueva<input name="password_confirmation" type="password" autoComplete="new-password" minLength={12} required /></label>
        </Form>
        <p>Si no conoce su clave inicial, pida a la administración que la restablezca.</p>
        <button
          type="button"
          className="secondary"
          onClick={async () => {
            await api.post('/logout').catch(() => {});
            setSession(null);
          }}
        >Salir</button>
      </section>
    </div>
  </main>;
}
