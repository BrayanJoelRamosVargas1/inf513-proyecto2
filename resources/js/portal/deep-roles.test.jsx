import React from 'react';
import { it, expect, vi, afterEach } from 'vitest';
import { render, screen, within, fireEvent, cleanup, waitFor } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { Session } from './shared';
import { api } from './api';
import { Application } from './application';
import { Management, CallConfiguration } from './call-forms';
import { CorrectionRequest } from './corrections';
import { Participation } from './participation';
afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

const user = {
  id: 1,
  name: 'Cuenta sintética',
  role: 'admin'
};

function show(component, data, path = '/', route = '*') {
  vi.spyOn(api, 'get').mockResolvedValue({
    data
  });
  render(<Session.Provider value={{
    session: {
      user,
      roles: []
    }
  }}>
    <MemoryRouter initialEntries={[path]}>
      <Routes><Route path={route} element={component} /></Routes>
    </MemoryRouter>
  </Session.Provider>);
}

it('conserva la vigencia completa de un permiso al preparar su renovación y no ofrece elevarse a sí mismo', async () => {
  const post = vi.spyOn(api, 'post').mockResolvedValue({
    data: {}
  });
  show(<Management />, {
    users: [{
      id: 1,
      name: 'Admin',
      role: 'admin',
      active: true
    }, {
      id: 2,
      name: 'Revisor',
      role: 'staff',
      active: true
    }],
    calls: [{
      id: 3,
      title: 'Convocatoria'
    }],
    categories: [],
    memberships: [{
      id: 4,
      user_id: 2,
      call_id: 3,
      role: 'reviewer',
      version: 1,
      starts_at: '2026-11-20T10:00:17Z',
      expires_at: '2026-11-25T10:00:23Z'
    }]
  });
  await screen.findByText('Renovar o cambiar vigencia');
  const section = screen.getByText('Renovar o cambiar vigencia').closest('section');
  expect(within(section).getByLabelText('Desde').value.replace(/\.000$/, '')).toBe('2026-11-20T06:00:17');
  expect(within(section).getByLabelText('Hasta').value.replace(/\.000$/, '')).toBe('2026-11-25T06:00:23');
  const assign = screen.getByText('Asignar responsabilidad').closest('section');
  expect([...within(assign).getByLabelText('Usuario').options].map(o => o.value)).toEqual(['2']);
  fireEvent.change(within(section).getByLabelText('Motivo y evidencia'), {
    target: {
      value: 'Renovación sintética conservando la vigencia'
    }
  });
  fireEvent.submit(section.querySelector('form'));
  await waitFor(() => expect(post).toHaveBeenCalledWith('/permissions', expect.objectContaining({
    starts_at: expect.stringMatching(/^2026-11-20T06:00:17(?:\.000)?$/),
    expires_at: expect.stringMatching(/^2026-11-25T06:00:23(?:\.000)?$/),
    version: 1
  })));
});

it('ofrece a la autoridad solo áreas sin responsable y una persona diferente para reasignar', async () => {
  show(<CallConfiguration />, {
    call: {
      mode: 'DEMO',
      status: 'evaluation'
    },
    categories: [],
    teachers: [{
      id: 2,
      name: 'Evaluador actual'
    }, {
      id: 3,
      name: 'Otro evaluador'
    }],
    offers: [{
      id: 5,
      title: 'Software y redes',
      seats: 1,
      weights: '{"Software":50,"Redes":50}'
    }],
    assignments: [{
      id: 6,
      offer_id: 5,
      area: 'Software',
      user_id: 2,
      version: 1
    }]
  }, '/convocatorias/1/configuracion', '/convocatorias/:id/configuracion');
  await screen.findByText('Asignar evaluador');
  const assign = screen.getByText('Asignar evaluador').closest('section');
  expect([...within(assign).getByLabelText('Área').options].map(o => o.value)).toEqual(['Redes']);
  const replace = screen.getByText('Reasignar con historial').closest('section');
  expect([...within(replace).getByLabelText('Nuevo evaluador').options].map(o => o.value)).toEqual(['3']);
});

it('no ofrece una asignación duplicada ni una reasignación imposible cuando todas las áreas tienen su único evaluador', async () => {
  show(<CallConfiguration />, {
    call: {
      mode: 'DEMO',
      status: 'evaluation'
    },
    categories: [],
    teachers: [{
      id: 2,
      name: 'Único evaluador'
    }],
    offers: [{
      id: 5,
      title: 'Software',
      seats: 1,
      weights: '{"Software":100}'
    }],
    assignments: [{
      id: 6,
      offer_id: 5,
      area: 'Software',
      user_id: 2,
      version: 1
    }]
  }, '/convocatorias/1/configuracion', '/convocatorias/:id/configuracion');
  await screen.findByText('Software · 1 plaza');
  expect(screen.queryByText('Asignar evaluador')).toBeNull();
  expect(screen.queryByText('Reasignar con historial')).toBeNull();
});

it('mantiene los criterios revisados y exige elegir la habilitación sin permitir aprobar con requisitos pendientes', async () => {
  show(<Application />, {
    application: {
      status: 'submitted',
      eligibility: 'pending',
      participation: 'active'
    },
    student: {
      name: 'Postulante'
    },
    call: {
      id: 1,
      title: 'Convocatoria'
    },
    offer: {
      documents: {
        cpd: 'Respaldo CPD'
      },
      title: 'Software'
    },
    documents: [],
    criteria: {
      'doc:cpd': 'Respaldo CPD',
      activo: 'Condición académica'
    },
    reviews: [{
      criterion: 'doc:cpd',
      decision: 'meets'
    }],
    grades: [],
    assignments: [],
    reviewer: true,
    can_review: true,
    can_enable: false
  }, '/postulaciones/1', '/postulaciones/:id');
  await screen.findByRole('button', {
    name: 'Guardar revisión por criterio'
  });
  expect(screen.getByLabelText('Respaldo CPD').value).toBe('meets');
  expect(screen.getByLabelText('Condición académica').value).toBe('observed');
  const decision = screen.getByLabelText('Decisión');
  expect(decision.value).toBe('');
  expect([...decision.options].find(o => o.value === '1').disabled).toBe(true);
});

it('limita una rectificación de mérito a 40 y una de examen a 100, enviando el tipo correcto', async () => {
  const post = vi.spyOn(api, 'post').mockResolvedValue({
      data: {}
    }),
    after = vi.fn();
  render(<CorrectionRequest
    id={7}
    options={[['', 'Méritos /40'], [2, 'Examen /100']]}
    after={after}
  />);
  expect(screen.getByLabelText('Motivo y evidencia').maxLength).toBe(3000);
  expect(screen.getByLabelText('Referencia de evidencia').maxLength).toBe(3000);
  const score = screen.getByLabelText('Nuevo puntaje');
  fireEvent.change(score, {
    target: {
      value: '41'
    }
  });
  expect(score.checkValidity()).toBe(false);
  fireEvent.change(screen.getByLabelText('Valor a corregir'), {
    target: {
      value: '2'
    }
  });
  expect(score.max).toBe('100');
  expect(score.checkValidity()).toBe(true);
  fireEvent.change(screen.getByLabelText('Motivo y evidencia'), {
    target: {
      value: 'Motivo sintético de rectificación'
    }
  });
  fireEvent.change(screen.getByLabelText('Referencia de evidencia'), {
    target: {
      value: 'Acta sintética de referencia'
    }
  });
  fireEvent.submit(score.closest('form'));
  await waitFor(() => expect(post).toHaveBeenCalledWith('/applications/7/corrections', expect.objectContaining({
    grade_id: '2',
    score: '41'
  })));
});

it('el interesado ve el fundamento de la decisión sobre su retiro y no solo el texto de su solicitud', async () => {
  show(<Participation />, {
    offer: {
      title: 'Software',
      documents: '{}'
    },
    app: {
      dossier_version: 1,
      participation: 'active'
    },
    call: {
      mode: 'DEMO',
      status: 'published'
    },
    owner: true,
    amendments: [],
    documents: [],
    snapshots: [],
    requests: [{
      id: 3,
      kind: 'withdrawal',
      status: 'rejected',
      reason: 'Motivo del interesado',
      evidence: 'Referencia'
    }],
    decisions: {
      3: {
        approved: false,
        reason: 'Fundamento de la autoridad',
        user_id: 4,
        created_at: '2026-11-23'
      }
    }
  }, '/postulaciones/1/participacion', '/postulaciones/:id/participacion');
  await screen.findByText(/Decisión registrada: Rechazada/);
  expect(screen.getByText(/Decisión registrada: Rechazada/).textContent).toContain('Fundamento de la autoridad');
});

it('muestra al evaluador reasignado la nota confirmada con autoría conservada y acceso a rectificación', async () => {
  show(<Application />, {
    application: {
      status: 'submitted',
      eligibility: 'enabled',
      participation: 'active'
    },
    student: {
      name: 'Postulante'
    },
    call: {
      id: 1,
      title: 'Convocatoria'
    },
    offer: {
      documents: {},
      title: 'Software'
    },
    documents: [],
    criteria: {},
    reviews: [],
    grades: [{
      id: 8,
      assignment_id: 2,
      area: 'Software',
      score: '80.00',
      status: 'confirmed',
      user_id: 4
    }],
    assignments: [{
      id: 2,
      area: 'Software',
      user_id: 5
    }],
    can_grade: false,
    can_view_corrections: true
  }, '/postulaciones/1', '/postulaciones/:id');
  await screen.findByText(/Software: 80.00/);
  expect(screen.getByText(/Registrada por cuenta #4/)).toBeTruthy();
  expect(screen.queryByRole('button', {
    name: 'Registrar nota'
  })).toBeNull();
  expect(screen.getByRole('link', {
    name: /Rectificaciones e historial/
  }).getAttribute('href')).toBe('/postulaciones/1/rectificaciones');
  expect(screen.queryByText('Expediente digital')).toBeNull();
});

it('el postulante puede leer el motivo de la observación de su documento y no recibe acciones de revisión', async () => {
  show(<Application />, {
    application: {
      status: 'submitted',
      eligibility: 'pending',
      participation: 'active'
    },
    owner: true,
    student: {
      name: 'Postulante'
    },
    call: {
      id: 1,
      title: 'Convocatoria'
    },
    offer: {
      documents: {
        cpd: 'Respaldo CPD'
      },
      title: 'Software'
    },
    documents: [],
    criteria: {
      'doc:cpd': 'Respaldo CPD'
    },
    reviews: [{
      criterion: 'doc:cpd',
      decision: 'observed',
      reason: 'La segunda página está ilegible; adjunta una copia legible.',
      user_id: 4,
      updated_at: '2026-11-23'
    }],
    grades: [],
    assignments: [],
    can_review: false
  }, '/postulaciones/1', '/postulaciones/:id');
  await screen.findByRole('heading', {
    name: 'Observaciones y fundamentos de revisión'
  });
  expect(screen.getByText('La segunda página está ilegible; adjunta una copia legible.')).toBeTruthy();
  expect(screen.queryByRole('button', {
    name: 'Guardar revisión por criterio'
  })).toBeNull();
});
