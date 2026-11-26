import React from 'react';
import { it, expect, vi, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { Calls } from './workspaces';
import { Session } from './shared';
import { api } from './api';
afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

const date = days => new Date(Date.now() + days * 86400000).toISOString();

function show(calls) {
  vi.spyOn(api, 'get').mockResolvedValue({
    data: {
      calls
    }
  });
  render(<MemoryRouter>
    <Session.Provider value={{
      session: {
        user: {
          role: 'student'
        },
        roles: []
      }
    }}>
      <Calls />
    </Session.Provider>
  </MemoryRouter>);
}

it('separa referencias históricas de recepción DEMO vigente y permite volver al catálogo', async () => {
  show([{
    id: 1,
    title: 'Referencia institucional 01/2026',
    mode: 'HISTORICAL',
    status: 'published',
    opens_at: date(-1),
    closes_at: date(1)
  }, {
    id: 2,
    title: 'Práctica abierta',
    mode: 'DEMO',
    status: 'published',
    opens_at: date(-1),
    closes_at: date(1)
  }, {
    id: 3,
    title: 'Práctica cerrada',
    mode: 'DEMO',
    status: 'closed',
    opens_at: date(-5),
    closes_at: date(-2)
  }]);
  await screen.findByRole('link', {
    name: 'Referencia institucional 01/2026'
  });
  fireEvent.click(screen.getByRole('button', {
    name: /^Recepción vigente/
  }));
  expect(screen.getByRole('link', {
    name: 'Práctica abierta'
  })).toBeTruthy();
  expect(screen.queryByRole('link', {
    name: 'Referencia institucional 01/2026'
  })).toBeNull();
  expect(screen.queryByRole('link', {
    name: 'Práctica cerrada'
  })).toBeNull();
  fireEvent.click(screen.getByRole('button', {
    name: /^Históricas/
  }));
  expect(screen.getByRole('link', {
    name: 'Referencia institucional 01/2026'
  }).getAttribute('href')).toBe('/convocatorias/1');
  expect(screen.queryByRole('link', {
    name: 'Práctica abierta'
  })).toBeNull();
  fireEvent.click(screen.getByRole('button', {
    name: /^Todas/
  }));
  expect(screen.getByRole('link', {
    name: 'Práctica cerrada'
  })).toBeTruthy();
  expect(api.get).toHaveBeenCalledTimes(1);
});

it('un filtro sin recepción vigente muestra un estado vacío y conserva el acceso a las referencias', async () => {
  show([{
    id: 1,
    title: 'Referencia conservada',
    mode: 'HISTORICAL',
    status: 'archived',
    opens_at: date(-10),
    closes_at: date(-1)
  }]);
  await screen.findByRole('link', {
    name: 'Referencia conservada'
  });
  fireEvent.click(screen.getByRole('button', {
    name: /^Recepción vigente/
  }));
  expect(screen.getByText('No hay convocatorias que coincidan con este filtro.')).toBeTruthy();
  expect(screen.queryByRole('link', {
    name: 'Crear convocatoria'
  })).toBeNull();
  fireEvent.click(screen.getByRole('button', {
    name: /^Históricas/
  }));
  expect(screen.getByRole('link', {
    name: 'Referencia conservada'
  })).toBeTruthy();
});
