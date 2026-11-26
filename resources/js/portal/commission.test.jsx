import React from 'react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, cleanup, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { CallCommission, CallVacancies } from './commission';
import { api } from './api';

vi.mock('./api', async original => ({
  ...(await original()),
  api: {
    get: vi.fn(),
    post: vi.fn()
  }
}));

afterEach(() => {
  cleanup();
  vi.resetAllMocks();
});

const at = (path, element) => render(<MemoryRouter initialEntries={[path]}>
  <Routes><Route path="/convocatorias/:id/*" element={element} /></Routes>
</MemoryRouter>);

describe('comisión evaluadora', () => {
  it('avisa qué falta por oferta y lista a los integrantes y veedores', async () => {
    api.get.mockResolvedValue({
      data: {
        cargos: {
          director_carrera: 'Director de Carrera',
          docente_invitado: 'Docente invitado',
          veedor_federacion: 'Veedor de la Federación de Auxiliares'
        },
        observers: ['veedor_federacion'],
        members: [{
          id: 1,
          offer_id: null,
          user_id: 4,
          name: 'Ana Docente',
          cargo: 'director_carrera',
          designation_reference: 'RES-1',
          accredited_on: null
        }, {
          id: 2,
          offer_id: null,
          user_id: null,
          name: 'Delegado FUAD',
          cargo: 'veedor_federacion',
          designation_reference: 'CARTA-9',
          accredited_on: '2026-02-01'
        }],
        missing: {
          Software: ['Docentes invitados (mínimo dos)']
        },
        offers: [{
          id: 7,
          title: 'Software'
        }],
        candidates: [{
          id: 4,
          name: 'Ana Docente'
        }],
        exam_at: '2026-02-03T08:00:00-04:00'
      }
    });
    at('/convocatorias/12/comision', <CallCommission />);
    expect(await screen.findByText('Composición incompleta.')).toBeTruthy();
    expect(screen.getByText(/Software: falta Docentes invitados/)).toBeTruthy();
    expect(screen.getByText(/Ana Docente · Director de Carrera/)).toBeTruthy();
    expect(screen.getByText(/Delegado FUAD · Veedor de la Federación/)).toBeTruthy();
    expect(api.get).toHaveBeenCalledWith('/calls/12/commission');
  });
});

describe('plazas sin cubrir', () => {
  it('muestra la situación por oferta y cuándo corresponde el acta de declaración desierta', async () => {
    api.get.mockResolvedValue({
      data: {
        call: {
          id: 12,
          title: 'Convocatoria 02/2026',
          status: 'results'
        },
        max_consecutive: 2,
        offers: [{
          offer_id: 7,
          title: 'Software',
          seats: 2,
          applicants: 0,
          selected: 0,
          vacancies: 2,
          outcome: 'deserted',
          consecutive_calls_unfilled: 2,
          may_declare: true
        }]
      }
    });
    at('/convocatorias/12/plazas', <CallVacancies />);
    await waitFor(() => expect(screen.getByText(/Corresponde acta de declaración desierta o vacante/)).toBeTruthy());
    expect(screen.getByRole('table', {
      name: /Estado de las plazas por oferta/
    })).toBeTruthy();
    // Con dos convocatorias consecutivas ya no se ofrece crear una tercera.
    expect(screen.queryByText('Convocar de nuevo las plazas sin cubrir')).toBeNull();
  });
});
