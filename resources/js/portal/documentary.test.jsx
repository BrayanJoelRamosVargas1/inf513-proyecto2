import React from 'react';
import { it, expect, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup } from '@testing-library/react';
import { DocumentaryCall } from './documentary';
import definitions from '../../institutional/catalogue.json';
import subjects from '../../institutional/subjects.json';
afterEach(cleanup);

const data = {
  ...definitions.profiles['convocatoria-01'],
  sources: Object.entries(definitions.sources).filter(([k]) => k !== 'convocatoria-02').map(([key, v]) => ({
    key,
    ...v
  })),
  subjects: subjects['convocatoria-01'].map((s, i) => ({
    offer_id: i,
    printed_number: s.numero_impreso,
    source_page: s.pagina_pdf,
    source_group: 'Grupo documental',
    code: s.sigla,
    original_name: s.nombre
  }))
};

it('consulta 49 asignaturas y filtra sin inventar plazas o botones de postulación', () => {
  render(<DocumentaryCall data={data} />);
  expect(screen.getByRole('heading', {
    name: 'Asignaturas ofertadas (49)'
  })).toBeTruthy();
  fireEvent.change(screen.getByLabelText('Buscar asignatura, sigla o agrupación'), {
    target: {
      value: 'INF312'
    }
  });
  expect(screen.getByRole('status').textContent).toBe('1 de 49 asignaturas');
  expect(screen.getByText('BASE DE DATOS I')).toBeTruthy();
  expect(screen.queryByRole('button', {
    name: 'Iniciar postulación'
  })).toBeNull();
});

it('distingue el calendario de asignatura del conflicto impreso de salas', () => {
  render(<DocumentaryCall data={data} />);
  expect(screen.queryByText(/Advertencia documental/)).toBeNull();
  fireEvent.click(screen.getByRole('button', {
    name: 'Docencia en sala de cómputo'
  }));
  expect(screen.getByText(/Advertencia documental/).textContent).toContain('enero');
  expect(screen.queryByRole('heading', {
    name: 'Asignaturas ofertadas (49)'
  })).toBeNull();
});

it('ofrece originales verificables y explica dónde pedir CPD sin fabricar una descarga', () => {
  render(<DocumentaryCall data={data} />);
  expect(screen.getAllByRole('link', {
    name: /Descargar/
  })).toHaveLength(2);
  expect(screen.getByText(/Formato auténtico no proporcionado/)).toBeTruthy();
  expect(screen.queryByRole('link', {
    name: /CPD/
  })).toBeNull();
  expect(screen.getByText(/Nota mínima final aprobatoria/)).toBeTruthy();
});
