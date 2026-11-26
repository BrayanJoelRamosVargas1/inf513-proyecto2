import React, { useContext, useEffect, useRef, useState } from 'react';
import { Account, TrainingSamples } from './account';
import { Metrics, Search } from './analytics';
import { api, csrf, isTraining, message } from './api';
import { Application } from './application';
import { CallAudit } from './audit';
import { CallCommission, CallVacancies } from './commission';
import { Call } from './call';
import { InstitutionalDossier, InstitutionalIndex } from './institutional';
import { CallConfiguration, CreateCall, Management } from './call-forms';
import { Corrections } from './corrections';
import { Awards, Participation } from './participation';
import { Ranking } from './ranking';
import { Sandbox } from './sandbox';
import { Alert, Form, Loading, Session, useData, uuid } from './shared';
import { Applications, Calls, Home, roleName } from './workspaces';
import { Link, Route, Routes, useLocation, useNavigate } from 'react-router-dom';

/* F-14: orden de etapas de una convocatoria; el servidor lo valida (Selection::transition), la interfaz solo ofrece el siguiente paso. */
/* F-21: el título del documento cambia con la pantalla, para que lectores de pantalla y pestañas distingan dónde está la persona. */
const PAGE_TITLES = [[/^\/convocatorias\/nueva/, 'Nueva convocatoria'], [/^\/convocatorias\/\d+\/auditoria/, 'Auditoría de la convocatoria'], [/^\/convocatorias\/\d+\/configuracion/, 'Configuración de la convocatoria'], [/^\/convocatorias\/\d+\/comision/, 'Comisión evaluadora'], [/^\/convocatorias\/\d+\/plazas/, 'Plazas sin cubrir'], [/^\/convocatorias\/\d+/, 'Convocatoria'], [/^\/convocatorias/, 'Convocatorias'], [/^\/postulaciones\/\d+\/rectificaciones/, 'Rectificaciones'], [/^\/postulaciones\/\d+\/participacion/, 'Participación'], [/^\/postulaciones\/\d+/, 'Postulación'], [/^\/postulaciones/, 'Postulaciones'], [/^\/ofertas\/\d+\/resultados/, 'Resultados de la oferta'], [/^\/revision/, 'Revisión'], [/^\/evaluacion/, 'Evaluación'], [/^\/expedientes/, 'Expedientes'], [/^\/estadisticas/, 'Estadísticas'], [/^\/simulacion/, 'Simulación de pagos'], [/^\/cuenta/, 'Mi cuenta'], [/^\/buscar/, 'Búsqueda'], [/^\/administracion/, 'Administración'], [/^\/adjudicaciones/, 'Adjudicaciones'], [/^\/entrenamiento/, 'Materiales de entrenamiento'], [/^\/institucional/, 'Registro institucional']];

export function pageTitle(path) {
  const hit = PAGE_TITLES.find(([re]) => re.test(path));
  return (hit ? hit[1] + ' · ' : 'Inicio · ') + 'Auxiliares FICCT';
}

export function FooterVisitCounter({
  pathOverride,
  isPublic = false
}) {
  const {
    pathname,
    key: locationKey
  } = useLocation();
  const path = pathOverride || pathname;
  const receipt = useRef(null);
  const visitId = `${locationKey}:${isPublic}:${path}`;
  if (receipt.current?.id !== visitId) receipt.current = {
    id: visitId,
    key: uuid()
  };
  const [total, setTotal] = useState(null),
    [error, setError] = useState('');
  useEffect(() => {
    let active = true;
    setTotal(null);
    setError('');
    const endpoint = isPublic ? '/visits/public' : '/visits';
    const payload = {
      path,
      key: receipt.current.key
    };
    api.post(endpoint, payload).catch(async e => {
      if (!isPublic || e.response?.status !== 419) throw e;
      await csrf();
      if (!active) throw e;
      return api.post(endpoint, payload);
    }).then(r => {
      if (active) setTotal(r.data.total);
    }).catch(() => {
      if (active) setError('Contador temporalmente no disponible');
    });
    return () => {
      active = false;
    };
  }, [path, isPublic, visitId]);
  return <footer className={isPublic ? 'auth-footer' : ''}>
    <span className="footer-legal">
      <span className="footer-brand">{isTraining() ? 'FICCT · Grupo 09' : 'FICCT · UAGRM'}</span>
      <span>{isTraining() ? 'Proyecto académico · Sin validez institucional' : 'Facultad de Ingeniería en Ciencias de la Computación y Telecomunicaciones · UAGRM'}</span>
    </span>
    <span aria-live="polite">{error || `Visitas de esta vista: ${total ?? '…'}`}</span>
  </footer>;
}

export function Preferences() {
  const [d, error, reload] = useData('/me/preferences');
  const [status, set] = useState('');
  const [expanded, setExpanded] = useState(false);
  useEffect(() => {
    if (!d) return;
    const p = d.preferences;
    document.documentElement.dataset.theme = p.theme;
    document.documentElement.dataset.mode = p.mode === 'auto' ? matchMedia('(prefers-color-scheme: dark)').matches ? 'noche' : 'dia' : p.mode;
    document.documentElement.dataset.font = String(p.font);
    document.documentElement.dataset.contrast = p.contrast ? 'alto' : 'normal';
  }, [d]);
  if (!d) return <Loading error={error} />;
  return <details
    className="preferences"
    open={expanded}
    onToggle={e => setExpanded(e.currentTarget.open)}
  >
    <summary>Apariencia y accesibilidad</summary>
    <Form
      button="Aplicar preferencias"
      onSubmit={async f => {
        const values = Object.fromEntries(f);
        values.font = Number(values.font);
        values.contrast = values.contrast === '1';
        await api.put('/me/preferences', values);
        reload();
        set('Preferencias guardadas.');
      }}
    >
      <div className="grid">
        <label>Tema<select name="theme" defaultValue={d.preferences.theme}>
            <option value="ninos">Niños</option>
            <option value="jovenes">Jóvenes</option>
            <option value="adultos">Adultos</option>
          </select></label>
        <label>Modo<select name="mode" defaultValue={d.preferences.mode}>
            <option value="auto">Según el equipo</option>
            <option value="dia">Día</option>
            <option value="noche">Noche</option>
          </select></label>
        <label>Tamaño de letra<select name="font" defaultValue={d.preferences.font}>
            {[100, 125, 150, 175, 200].map(n => <option key={n} value={n}>{n}%</option>)}
          </select></label>
        <label>Contraste<select name="contrast" defaultValue={d.preferences.contrast ? '1' : '0'}>
            <option value="0">Normal</option>
            <option value="1">Alto contraste</option>
          </select></label>
      </div>
      <p role="status">{status}</p>
    </Form>
    <Form
      className="password-form"
      button="Cambiar clave"
      onSubmit={async (f, form) => {
        await api.post('/me/password', Object.fromEntries(f));
        form.reset();
        set('Clave cambiada. Las demás sesiones se cerraron.');
      }}
    >
      <h3>Cambiar clave</h3>
      <div className="grid">
        <label>Clave actual<input name="current_password" type="password" autoComplete="current-password" required /></label>
        <label>Clave nueva (mínimo 12 caracteres)<input name="password" type="password" autoComplete="new-password" minLength={12} required /></label>
        <label>Repita la clave nueva<input name="password_confirmation" type="password" autoComplete="new-password" minLength={12} required /></label>
      </div>
    </Form>
  </details>;
}

function NavIcon({
  path
}) {
  const type = path === '/' ? 'home' : path.includes('convocatorias') ? 'calendar' : path.includes('postulaciones') ? 'folder' : path.includes('estadisticas') ? 'chart' : path.includes('buscar') ? 'search' : path.includes('cuenta') ? 'user' : path.includes('simulacion') ? 'flask' : path.includes('administracion') ? 'settings' : path.includes('adjudicaciones') ? 'award' : 'grid';
  const segments = {
    home: <><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-6v-7h-4v7H4a1 1 0 0 1-1-1z" /></>,
    calendar: <><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M7 3v4M17 3v4M3 10h18" /></>,
    folder: <><path d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v10H3z" /></>,
    chart: <><path d="M4 20V10m5 10V4m5 16v-7m5 7V8" /></>,
    search: <><circle cx="10.5" cy="10.5" r="6" /><path d="m15 15 5 5" /></>,
    user: <><circle cx="12" cy="8" r="4" /><path d="M4 21c0-5 16-5 16 0" /></>,
    flask: <><path d="M9 3h6m-5 0v6L4 19a2 2 0 0 0 2 3h12a2 2 0 0 0 2-3L14 9V3M7 16h10" /></>,
    settings: <>
      <circle cx="12" cy="12" r="3" />
      <path d="M12 2v3m0 14v3M2 12h3m14 0h3M4.9 4.9 7 7m10 10 2.1 2.1M19.1 4.9 17 7M7 17l-2.1 2.1" />
    </>,
    award: <><circle cx="12" cy="8" r="5" /><path d="m8 12-2 9 6-3 6 3-2-9" /></>,
    grid: <>
      <rect x="3" y="3" width="7" height="7" rx="1" />
      <rect x="14" y="3" width="7" height="7" rx="1" />
      <rect x="3" y="14" width="7" height="7" rx="1" />
      <rect x="14" y="14" width="7" height="7" rx="1" />
    </>
  };
  return <svg
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    strokeWidth="1.8"
    strokeLinecap="round"
    strokeLinejoin="round"
    aria-hidden="true"
  >
    {segments[type]}
  </svg>;
}

export function AppShell() {
  const {
    session,
    setSession
  } = useContext(Session);
  const [nav, error] = useData('/me/navigation');
  const [menuOpen, setMenuOpen] = useState(false);
  const [logoutError, setLogoutError] = useState(''),
    [loggingOut, setLoggingOut] = useState(false);
  const menuRef = useRef(null),
    menuButtonRef = useRef(null);
  const pageLocation = useLocation(),
    firstPage = useRef(true);
  useEffect(() => {
    document.title = pageTitle(pageLocation.pathname); /* F-21: tras navegar, el foco va al contenido (no en la carga inicial) para que lectores de pantalla y teclado sepan que cambió la pantalla. */
    if (firstPage.current) {
      firstPage.current = false;
      return;
    }
    document.getElementById('contenido')?.focus({
      preventScroll: true
    });
  }, [pageLocation.pathname]);
  useEffect(() => {
    if (!menuOpen) return;
    const menu = menuRef.current;
    const focusable = () => [...menu.querySelectorAll('a[href],button:not([disabled])')];
    focusable()[0]?.focus();
    const handle = e => {
      if (e.key === 'Escape') {
        e.preventDefault();
        setMenuOpen(false);
      }
      if (e.key === 'Tab') {
        const items = focusable(),
          first = items[0],
          last = items.at(-1);
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last?.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first?.focus();
        }
      }
    };
    document.addEventListener('keydown', handle);
    return () => {
      document.removeEventListener('keydown', handle);
      menuButtonRef.current?.focus();
    };
  }, [menuOpen]);
  const navigate = useNavigate(),
    loc = useLocation();
  useEffect(() => setMenuOpen(false), [loc.pathname]);
  return <div className="portal-layout">
    <a className="skip" href="#contenido">Ir al contenido principal</a>
    {menuOpen && <button
      tabIndex="-1"
      className="sidebar-scrim"
      aria-label="Cerrar menú"
      onClick={() => setMenuOpen(false)}
    />}
    <aside
      ref={menuRef}
      id="portal-navigation"
      className={`sidebar ${menuOpen ? 'is-open' : ''}`}
      aria-label="Navegación lateral"
    >
      <button
        type="button"
        className="secondary sidebar-close"
        onClick={() => setMenuOpen(false)}
      >Cerrar menú</button>
      <Link to="/" className="sidebar-brand">
        <span className="brand-emblem">F</span>
        <span className="brand-copy">
          <strong>FICCT</strong>
          <small>Selección de auxiliares</small>
        </span>
      </Link>
      <p className="sidebar-section">Menú de navegación</p>
      <nav className="sidebar-nav" aria-label="Principal">
        {nav?.items.map(n => <Link
          key={n.id}
          to={n.path}
          aria-current={loc.pathname === n.path ? 'page' : undefined}
          onClick={() => setMenuOpen(false)}
        >
          <NavIcon path={n.path} />
          <span>{n.label}</span>
        </Link>)}
      </nav>
      <Alert text={error} />
      <div className="sidebar-footer">
        <strong>FICCT · UAGRM</strong>
        <p>Selección de auxiliares</p>
        {isTraining() && <p>Proyecto académico · Grupo 09</p>}
      </div>
    </aside>
    <div className="shell">
      <header className="topbar">
        <div className="topbar-leading">
          <button
            ref={menuButtonRef}
            aria-controls="portal-navigation"
            className="secondary mobile-nav-button"
            type="button"
            aria-label="Abrir menú"
            aria-expanded={menuOpen}
            onClick={() => setMenuOpen(true)}
          >
            <svg
              viewBox="0 0 24 24"
              width="20"
              height="20"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.8"
              aria-hidden="true"
            >
              <path d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
          <form
            className="topbar-search"
            role="search"
            onSubmit={e => {
              e.preventDefault();
              const q = new FormData(e.currentTarget).get('q');
              navigate('/buscar?q=' + encodeURIComponent(q));
            }}
          >
            <label className="sr-only" htmlFor="global-search">Buscar convocatorias y ofertas</label>
            <span className="search-symbol" aria-hidden="true"><NavIcon path="/buscar" /></span>
            <input
              id="global-search"
              name="q"
              placeholder="Buscar convocatorias y ofertas…"
              minLength="2"
              maxLength="80"
              required
            />
            <button type="submit">Buscar</button>
          </form>
        </div>
        <div className="topbar-account">
          <span className="user-avatar" aria-hidden="true">
            {session.user.name.trim().slice(0, 1).toUpperCase()}
          </span>
          <span className="user-meta">
            <strong>{session.user.name}</strong>
            <small>{roleName(session)}</small>
          </span>
          <button
            type="button"
            className="secondary"
            disabled={loggingOut}
            onClick={async () => {
              if (loggingOut) return;
              setLogoutError('');
              setLoggingOut(true);
              try {
                await api.post('/logout');
                setSession(null);
              } catch (e) {
                setLogoutError(message(e));
              } finally {
                setLoggingOut(false);
              }
            }}
          >
            {loggingOut ? 'Cerrando sesión…' : 'Salir'}
          </button>
        </div>
      </header>
      <main className="page-content" id="contenido" tabIndex="-1">
        {session.training_enabled && <div className="demo-banner">
          <span className="demo-dot" aria-hidden="true" />
          <span><strong>Entrenamiento y archivo documental.</strong> Los procesos ficticios están identificados como DEMO.</span>
        </div>}
        <Alert text={logoutError} />
        <div className="page-toolbar">
          <nav aria-label="Ruta actual">
            <Link to="/">Mi espacio</Link>
            {loc.pathname !== '/' && <>
              <span aria-hidden="true">/</span>
              <span aria-current="page">
                {nav?.items.find(n => n.path !== '/' && (loc.pathname === n.path || loc.pathname.startsWith(n.path + '/')))?.label || 'Detalle del proceso'}
              </span>
            </>}
          </nav>
          <Preferences />
        </div>
        <Routes>
          <Route path="/entrenamiento" element={<TrainingSamples />} />
          <Route path="/institucional" element={<InstitutionalIndex />} />
          <Route path="/institucional/:id" element={<InstitutionalDossier />} />
          <Route path="/" element={<Home />} />
          <Route path="/convocatorias" element={<Calls />} />
          <Route path="/convocatorias/:id" element={<Call />} />
          <Route path="/postulaciones" element={<Applications workspace="own" />} />
          <Route path="/revision" element={<Applications workspace="reviewer" />} />
          <Route path="/evaluaciones" element={<Applications workspace="evaluator" />} />
          <Route path="/expedientes" element={<Applications workspace="authority" />} />
          <Route path="/postulaciones/:id" element={<Application />} />
          <Route path="/estadisticas" element={<Metrics />} />
          <Route path="/buscar" element={<Search />} />
          <Route path="/simulacion" element={<Sandbox />} />
          <Route path="/cuenta" element={<Account />} />
          <Route path="/administracion" element={<Management />} />
          <Route path="/adjudicaciones" element={<Awards />} />
          <Route path="/convocatorias/:id/auditoria" element={<CallAudit />} />
          <Route path="/convocatorias/:id/configuracion" element={<CallConfiguration />} />
          <Route path="/convocatorias/:id/comision" element={<CallCommission />} />
          <Route path="/convocatorias/:id/plazas" element={<CallVacancies />} />
          <Route path="/convocatorias/nueva" element={<CreateCall />} />
          <Route path="/ofertas/:id/resultados" element={<Ranking />} />
          <Route path="/postulaciones/:id/rectificaciones" element={<Corrections />} />
          <Route path="/postulaciones/:id/participacion" element={<Participation />} />
          <Route path="*" element={<p>Página no encontrada. <Link to="/">Volver</Link></p>} />
        </Routes>
      </main>
      <FooterVisitCounter />
    </div>
  </div>;
}
