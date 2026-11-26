import React, { useEffect, useState } from 'react';
import { api, base, csrf, setOperationalMode } from './api';
import { Auth } from './auth';
import { Alert, ErrorBoundary, Session } from './shared';
import { ForcedPassword } from './password';
import { AppShell } from './shell';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import './portal.css';
import './workspace.css';

function Root() {
  const [session, setSession] = useState(null),
    [loading, setLoading] = useState(true),
    [bootError, setBootError] = useState('');
  useEffect(() => {
    csrf().then(() => api.get('/me')).then(r => {
      api.defaults.headers.common['X-CSRF-TOKEN'] = r.data.token;
      setOperationalMode(r.data.operational_mode);
      setSession(r.data);
    }).catch(e => {
      if (!e.response || e.response.status >= 500) setBootError('No se pudo conectar con el servidor. Compruebe su conexión e intente de nuevo en unos minutos.');
    }).finally(() => setLoading(false));
    const interceptor = api.interceptors.response.use(r => r, e => {
      if (e.response?.status === 401) setSession(null);
      return Promise.reject(e);
    });
    return () => api.interceptors.response.eject(interceptor);
  }, []);
  return <Session.Provider value={{
    session,
    setSession
  }}>
    {loading ? <main><p role="status">Comprobando sesión…</p></main> : session ? session.must_change_password ? <ForcedPassword /> : <AppShell /> : <><Alert text={bootError} /><Auth /></>}
  </Session.Provider>;
}

if (document.getElementById('root')) createRoot(document.getElementById('root')).render(<ErrorBoundary>
  <BrowserRouter basename={`${base}/p2`}><Root /></BrowserRouter>
</ErrorBoundary>);
