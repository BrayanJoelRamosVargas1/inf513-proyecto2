import React, { useState } from 'react';
const normalized = value => String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es').trim();

export function UserDirectory({
  users,
  children
}) {
  const [query, setQuery] = useState(''),
    [role, setRole] = useState(''),
    [active, setActive] = useState('');
  const tokens = normalized(query).split(/\s+/).filter(Boolean);
  const filtered = users.filter(user => (!role || user.role === role) && (!active || Boolean(user.active) === (active === '1')) && tokens.every(token => normalized([user.name, user.email, user.faculty, user.career].join(' ')).includes(token)));
  return <section>
    <h2>Usuarios</h2>
    <div className="card">
      <div className="grid">
        <label>Buscar usuarios<input
            type="search"
            value={query}
            onChange={e => setQuery(e.target.value)}
            maxLength={150}
            placeholder="Nombre, correo, facultad o carrera"
          /></label>
        <label>Filtrar por perfil<select value={role} onChange={e => setRole(e.target.value)}>
            <option value="">Todos los perfiles</option>
            <option value="student">Postulante</option>
            <option value="staff">Personal/docente</option>
            <option value="authority">Autoridad</option>
            <option value="admin">Administrador técnico</option>
          </select></label>
        <label>Filtrar por estado<select value={active} onChange={e => setActive(e.target.value)}>
            <option value="">Todos los estados</option>
            <option value="1">Activo</option>
            <option value="0">Desactivado</option>
          </select></label>
      </div>
      <p role="status">{filtered.length} de {users.length} usuarios.</p>
      <button
        type="button"
        className="secondary"
        onClick={() => {
          setQuery('');
          setRole('');
          setActive('');
        }}
      >Quitar filtros de usuarios</button>
    </div>
    {!filtered.length && <p>No hay usuarios que coincidan con estos filtros.</p>}
    {children(filtered)}
  </section>;
}
