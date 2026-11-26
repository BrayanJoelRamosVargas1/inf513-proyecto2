import React from 'react';
import '@testing-library/jest-dom/vitest';
import { it, expect, vi, afterEach } from 'vitest';
import { render, screen, within, fireEvent, cleanup } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { Ranking } from './ranking';
import { api } from './api';
afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

it('permite consultar la publicación anterior, el impacto y su antiguo desempate sin reabrir acciones históricas', async () => {
  const old = {
    id: 44,
    name: 'Aspirante de la publicación anterior',
    merit: '33.25',
    exam: '54.00',
    final: '87.25'
  };
  vi.spyOn(api, 'get').mockResolvedValue({
    data: {
      call: {
        id: 15,
        mode: 'HISTORICAL',
        status: 'archived'
      },
      offer: {
        id: 5,
        title: 'Software',
        seats: 2,
        held_vacancies: 0,
        result_revision: 2
      },
      ranking: {
        rank: [],
        tied: [],
        vacancies: 2,
        held_vacancies: 0
      },
      ties: [],
      publications: [{
        id: 1,
        version: 1,
        result_revision: 1,
        created_at: '2026-11-24T12:00:00-04:00',
        minutes: 'Acta de ensayo anterior',
        snapshot: JSON.stringify({
          selected: [44],
          ranking: [old]
        })
      }],
      revisions: [{
        id: 1,
        revision: 2,
        correction_request_id: 7,
        created_at: '2026-11-24T13:00:00-04:00',
        changes: JSON.stringify([{
          application_id: 46,
          old_position: 4,
          new_position: 2,
          old_final: '75.80',
          new_final: '88.00'
        }])
      }],
      oldTies: [{
        id: 3,
        result_revision: 1,
        round: 1,
        status: 'resolved',
        participants: '[44,45]',
        commission: '["Comisión sintética"]',
        conditions: 'Condiciones históricas de prueba',
        results: '{"44":"Resultado conservado","45":"Otro resultado"}',
        winners: '[44]',
        minutes: '<script>contenido histórico literal</script>'
      }]
    }
  });
  render(<MemoryRouter initialEntries={['/ofertas/5/resultados']}>
    <Routes><Route path="/ofertas/:id/resultados" element={<Ranking />} /></Routes>
  </MemoryRouter>);
  const revision = await screen.findByRole('heading', {
    name: 'Impacto de revisión 2',
    exact: true
  });
  expect(within(revision.closest('section')).getByText('75.80')).toBeInTheDocument();
  expect(within(revision.closest('section')).getByText('88.00')).toBeInTheDocument();
  fireEvent.click(screen.getByText('Publicación v1 · Revisión 1', {
    exact: true
  }));
  expect(screen.getByText(old.name)).toBeVisible();
  fireEvent.click(screen.getByText('Examen #3 · Revisión 1 · Ronda 1', {
    exact: true
  }));
  expect(screen.getByText(/Postulación #44: Resultado conservado/, {
    selector: 'li'
  })).toBeVisible();
  expect(screen.getByText('<script>contenido histórico literal</script>')).toBeVisible();
  expect(document.querySelector('main script,section script')).toBeNull();
  expect(screen.queryByRole('button', {
    name: 'Aprobar publicación DEMO'
  })).not.toBeInTheDocument();
  expect(document.querySelector('form')).toBeNull();
});

it('describe una ronda continuada sin presentarla como pendiente cuando la siguiente ya está resuelta', async () => {
  vi.spyOn(api, 'get').mockResolvedValue({
    data: {
      call: {
        id: 19,
        mode: 'DEMO',
        status: 'evaluation'
      },
      authority: true,
      offer: {
        id: 45,
        title: 'Ensayo de continuidad',
        seats: 1,
        held_vacancies: 0,
        result_revision: 0
      },
      ranking: {
        rank: [],
        tied: [66, 67],
        vacancies: 0,
        held_vacancies: 0
      },
      ties: [{
        id: 4,
        round: 1,
        status: 'continued',
        conditions: 'Primera ronda conservada',
        minutes: 'Persistió el empate'
      }, {
        id: 5,
        round: 2,
        status: 'resolved',
        conditions: 'Segunda ronda terminada',
        minutes: 'Decisión expresa de comisión'
      }],
      publications: [{
        id: 18,
        version: 1,
        result_revision: 0,
        created_at: '2026-11-24T16:00:00-04:00',
        minutes: 'Aprobación posterior',
        snapshot: JSON.stringify({
          selected: [66],
          ranking: []
        })
      }],
      revisions: [],
      oldTies: []
    }
  });
  render(<MemoryRouter initialEntries={['/ofertas/45/resultados']}>
    <Routes><Route path="/ofertas/:id/resultados" element={<Ranking />} /></Routes>
  </MemoryRouter>);
  const first = await screen.findByRole('heading', {
    name: 'Desempate #4 · Ronda 1',
    exact: true
  });
  expect(within(first.closest('section')).getByText('Ronda continuada · Primera ronda conservada')).toBeVisible();
  expect(screen.getByText('Resuelto · Segunda ronda terminada')).toBeVisible();
  expect(screen.queryByText(/Nueva ronda pendiente/)).not.toBeInTheDocument();
  expect(screen.queryByRole('button', {
    name: 'Aprobar publicación DEMO'
  })).not.toBeInTheDocument();
  expect(screen.queryByRole('heading', {
    name: 'Persiste el empate: nueva ronda'
  })).not.toBeInTheDocument();
});

it('no presenta las plazas ofertadas históricas como vacantes sin resultados acreditados', async () => {
  vi.spyOn(api, 'get').mockResolvedValue({
    data: {
      call: {
        id: 1,
        mode: 'HISTORICAL',
        status: 'archived'
      },
      offer: {
        id: 1,
        title: 'Sala de cómputo',
        seats: 20,
        held_vacancies: 0
      },
      documentary_reference: true,
      ranking: null,
      warning: 'No se infieren ganadores, cobertura ni vacantes.',
      ties: [],
      publications: [],
      revisions: [],
      oldTies: []
    }
  });
  render(<MemoryRouter initialEntries={['/ofertas/1/resultados']}>
    <Routes><Route path="/ofertas/:id/resultados" element={<Ranking />} /></Routes>
  </MemoryRouter>);
  expect(await screen.findByText(/Plazas ofertadas en la fuente: 20/)).toBeVisible();
  expect(screen.getByText(/No se infieren ganadores/)).toBeVisible();
  expect(screen.queryByText(/Vacantes totales:/)).toBeNull();
  expect(screen.queryByText(/retenidas por renuncia/)).toBeNull();
});
