import React from 'react';
import '@testing-library/jest-dom/vitest';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, cleanup, fireEvent, waitFor, within } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { CreateCall } from './call-forms';
import { Corrections } from './corrections';
import { Awards } from './participation';
import { CpdRecord } from './institutional-register';
import { Session } from './shared';
import { api } from './api';

/* Auditoría F-15: pantallas que no tenían ninguna prueba. Usan el hook de datos real y simulan solo la red. */
afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

const as = (user, ui, path = '/', route = '/') => render(<Session.Provider value={{
  session: {
    user
  }
}}>
  <MemoryRouter initialEntries={[path]}>
    <Routes>
      <Route path={route} element={ui} />
      <Route path="*" element={<p>Otra pantalla</p>} />
    </Routes>
  </MemoryRouter>
</Session.Provider>);

const award = (extra = {}) => ({
  id: 11,
  user_id: 5,
  name: 'Ana Pérez',
  title: 'Software',
  selection: 'selected',
  participation: 'active',
  can_choose: true,
  publication: {
    id: 3,
    version: 2
  },
  choice: null,
  choices: [],
  ...extra
});

describe('Awards (adjudicaciones)', () => {
  it('el postulante ve su adjudicación y puede registrar una manifestación', async () => {
    vi.spyOn(api, 'get').mockResolvedValue({
      data: {
        authorityView: false,
        awards: [award()]
      }
    });
    const post = vi.spyOn(api, 'post').mockResolvedValue({
      data: {}
    });
    as({
      id: 5,
      role: 'student'
    }, <Awards />);
    expect(await screen.findByRole('heading', {
      name: 'Mis adjudicaciones'
    })).toBeInTheDocument();
    expect(screen.getByText(/Publicación v2/)).toBeInTheDocument();
    expect(screen.getByRole('link', {
      name: 'Expediente, retiro o renuncia'
    })).toHaveAttribute('href', '/postulaciones/11/participacion');
    const form = screen.getByRole('heading', {
      name: 'Registrar manifestación sobre esta publicación'
    }).closest('section').querySelector('form');
    fireEvent.change(within(form).getByLabelText('Motivo y evidencia'), {
      target: {
        value: 'Acepto para la demostración'
      }
    });
    fireEvent.change(within(form).getByLabelText('Referencia de evidencia'), {
      target: {
        value: 'Acta de la demostración'
      }
    });
    fireEvent.submit(form);
    await waitFor(() => expect(post).toHaveBeenCalled());
    expect(post.mock.calls[0][0]).toBe('/applications/11/choices');
    expect(post.mock.calls[0][1]).toMatchObject({
      choice: 'accept',
      publication_id: 3,
      version: 0
    });
  });
  it('la autoridad ve el ámbito sin poder decidir por otra persona', async () => {
    vi.spyOn(api, 'get').mockResolvedValue({
      data: {
        authorityView: true,
        awards: [award({
          user_id: 5
        })]
      }
    });
    as({
      id: 99,
      role: 'staff'
    }, <Awards />);
    expect(await screen.findByRole('heading', {
      name: 'Adjudicaciones de mi ámbito'
    })).toBeInTheDocument();
    expect(screen.queryByRole('heading', {
      name: 'Registrar manifestación sobre esta publicación'
    })).toBeNull();
  });
  it('no ofrece manifestación si la participación ya no está activa', async () => {
    vi.spyOn(api, 'get').mockResolvedValue({
      data: {
        authorityView: false,
        awards: [award({
          participation: 'resigned'
        })]
      }
    });
    as({
      id: 5,
      role: 'student'
    }, <Awards />);
    await screen.findByRole('heading', {
      name: 'Mis adjudicaciones'
    });
    expect(screen.queryByRole('heading', {
      name: 'Registrar manifestación sobre esta publicación'
    })).toBeNull();
  });
  it('avisa cuando no hay adjudicaciones y muestra el error de carga', async () => {
    const get = vi.spyOn(api, 'get').mockResolvedValueOnce({
      data: {
        authorityView: false,
        awards: []
      }
    });
    as({
      id: 5,
      role: 'student'
    }, <Awards />);
    expect(await screen.findByText('No hay adjudicaciones publicadas visibles para tu cuenta.')).toBeInTheDocument();
    cleanup();
    get.mockRejectedValueOnce({
      response: {
        status: 500,
        data: {
          message: 'Servicio no disponible'
        }
      }
    });
    as({
      id: 5,
      role: 'student'
    }, <Awards />);
    expect(await screen.findByRole('alert')).toBeInTheDocument();
  });
});

const correction = (extra = {}) => ({
  call: {
    mode: 'DEMO',
    status: 'evaluation'
  },
  app: {
    participation: 'active',
    merit: '30.00',
    user_id: 1
  },
  authority: true,
  reviewer: false,
  grades: [{
    id: 3,
    status: 'confirmed',
    score: '80.00'
  }, {
    id: 4,
    status: 'draft',
    score: '10.00'
  }],
  requests: [{
    id: 1,
    status: 'pending',
    old_value: '30.00',
    new_value: '35.00',
    reason: 'Error de transcripción',
    evidence_reference: 'Formulario CPD folio 3',
    requester_id: 5
  }],
  versions: [{
    id: 1,
    version: 1,
    score: '80.00',
    created_at: '2026-11-20 10:00',
    recorded_by: 2
  }, {
    id: 2,
    version: 2,
    score: '82.00',
    created_at: '2026-11-21 10:00',
    recorded_by: null
  }],
  ...extra
});

describe('Corrections (rectificaciones)', () => {
  const open = (user, data) => {
    vi.spyOn(api, 'get').mockResolvedValue({
      data
    });
    return as(user, <Corrections />, '/postulaciones/8/rectificaciones', '/postulaciones/:id/rectificaciones');
  };
  it('otra autoridad puede decidir una solicitud pendiente y se muestra el historial', async () => {
    open({
      id: 9,
      role: 'staff'
    }, correction());
    expect(await screen.findByRole('heading', {
      name: 'Rectificaciones y versiones'
    })).toBeInTheDocument();
    expect(screen.getByText('Solicitud #1 · Pendiente')).toBeInTheDocument();
    expect(screen.getByRole('heading', {
      name: 'Decidir solicitud'
    })).toBeInTheDocument();
    expect(screen.getByText(/v2 · 82.00/)).toBeInTheDocument();
    expect(screen.getByText(/Responsable #No inferido/)).toBeInTheDocument();
  });
  it('quien pidió la rectificación no puede decidirla', async () => {
    open({
      id: 5,
      role: 'staff'
    }, correction());
    await screen.findByText('Solicitud #1 · Pendiente');
    expect(screen.queryByRole('heading', {
      name: 'Decidir solicitud'
    })).toBeNull();
  });
  it('el dueño de la postulación no decide sobre su propia nota', async () => {
    open({
      id: 1,
      role: 'student'
    }, correction({
      authority: true
    }));
    await screen.findByText('Solicitud #1 · Pendiente');
    expect(screen.queryByRole('heading', {
      name: 'Decidir solicitud'
    })).toBeNull();
  });
  it('una convocatoria histórica o archivada solo se consulta', async () => {
    open({
      id: 9,
      role: 'staff'
    }, correction({
      call: {
        mode: 'HISTORICAL',
        status: 'archived'
      }
    }));
    await screen.findByText('Solicitud #1 · Pendiente');
    expect(screen.queryByRole('heading', {
      name: 'Decidir solicitud'
    })).toBeNull();
    expect(document.querySelectorAll('form')).toHaveLength(0);
  });
});

describe('CreateCall', () => {
  it('solo la autoridad puede crear convocatorias', () => {
    as({
      id: 5,
      role: 'student'
    }, <CreateCall />);
    expect(screen.getByText('No tienes permiso para crear convocatorias.')).toBeInTheDocument();
    expect(document.querySelector('form')).toBeNull();
  });
  it('crea el borrador y lleva a su configuración', async () => {
    const post = vi.spyOn(api, 'post').mockResolvedValue({
      data: {
        id: 42
      }
    });
    as({
      id: 9,
      role: 'authority'
    }, <CreateCall />, '/convocatorias/nueva', '/convocatorias/nueva');
    expect(screen.getByRole('heading', {
      name: 'Crear convocatoria'
    })).toBeInTheDocument();
    fireEvent.change(screen.getByLabelText('Nombre'), {
      target: {
        value: 'Convocatoria de prueba'
      }
    });
    fireEvent.submit(screen.getByRole('button', {
      name: 'Crear borrador'
    }).closest('form'));
    await waitFor(() => expect(post).toHaveBeenCalled());
    expect(post.mock.calls[0][0]).toBe('/calls');
    expect(post.mock.calls[0][1]).toMatchObject({
      title: 'Convocatoria de prueba',
      scope: 'UAGRM'
    });
    expect(await screen.findByText('Otra pantalla')).toBeInTheDocument();
  });
  it('muestra el error del servidor sin navegar', async () => {
    vi.spyOn(api, 'post').mockRejectedValue({
      response: {
        status: 422,
        data: {
          message: 'El cierre debe ser posterior a la apertura.'
        }
      }
    });
    as({
      id: 9,
      role: 'authority'
    }, <CreateCall />, '/convocatorias/nueva', '/convocatorias/nueva');
    fireEvent.submit(screen.getByRole('button', {
      name: 'Crear borrador'
    }).closest('form'));
    expect(await screen.findByRole('alert')).toHaveTextContent(/cierre/i);
    expect(screen.queryByText('Otra pantalla')).toBeNull();
  });
});

describe('CpdRecord', () => {
  const definition = {
    merit_components: [{
      key: 'ppa',
      label: 'Promedio',
      maximum: 24
    }, {
      key: 'experiencia',
      label: 'Experiencia',
      maximum: 12
    }]
  };
  const base = (extra = {}) => ({
    cpd_context: {
      modality: 'asignatura',
      definition,
      records: []
    },
    documents: [{
      id: 7,
      kind: 'cpd'
    }],
    application: {
      id: 8,
      merit: null,
      status: 'submitted'
    },
    call: {
      id: 3
    },
    reviewer: true,
    owner: false,
    document_validation: {
      ready: true
    },
    ...extra
  });
  it('no muestra nada sin contexto de CPD', () => {
    const {
      container
    } = render(<CpdRecord data={base({
      cpd_context: null
    })} reload={() => {}} />);
    expect(container).toBeEmptyDOMElement();
  });
  it('lista los registros conservados con su suma y el mérito del expediente', () => {
    const records = [{
      id: 1,
      reference: 'CPD-1',
      version: 2,
      recorded_by: 4,
      created_at: '2026-11-20',
      payload: {
        score: '30.00',
        component_sum: '30.00',
        components: {
          ppa: '20',
          experiencia: '10'
        },
        notes: 'Folio 3'
      }
    }];
    render(<CpdRecord
      data={base({
        cpd_context: {
          modality: 'asignatura',
          definition,
          records
        }
      })}
      reload={() => {}}
    />);
    expect(screen.getByRole('heading', {
      name: 'CPD-1 · versión 2'
    })).toBeInTheDocument();
    expect(screen.getByText(/Puntaje consignado: 30.00\/40/)).toBeInTheDocument();
    expect(screen.getByText('Promedio: 20')).toBeInTheDocument();
    expect(screen.getByText(/mérito registrado en el expediente: Pendiente/)).toBeInTheDocument();
  });
  it('solo el revisor que no es dueño registra componentes', () => {
    const {
      rerender
    } = render(<CpdRecord data={base()} reload={() => {}} />);
    expect(screen.getByText('Registrar componentes del formulario recibido')).toBeInTheDocument();
    rerender(<CpdRecord data={base({
      owner: true
    })} reload={() => {}} />);
    expect(screen.queryByText('Registrar componentes del formulario recibido')).toBeNull();
    rerender(<CpdRecord data={base({
      reviewer: false
    })} reload={() => {}} />);
    expect(screen.queryByText('Registrar componentes del formulario recibido')).toBeNull();
  });
  it('pide adjuntar el formulario auténtico cuando falta', () => {
    render(<CpdRecord data={base({
      documents: []
    })} reload={() => {}} />);
    expect(screen.getByText(/Falta adjuntar el formulario auténtico/)).toBeInTheDocument();
  });
});
