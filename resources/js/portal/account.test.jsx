import React from 'react';
import { it, expect, afterEach } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';
import { Account, TrainingSamples } from './account';
import { Session } from './shared';
afterEach(cleanup);

const show = (role, roles = []) => render(<Session.Provider value={{
  session: {
    training_enabled: true,
    user: {
      role,
      name: 'Usuario de prueba',
      email: 'fixture@example.test',
      faculty: 'FICCT',
      career: 'Sistemas'
    },
    roles
  }
}}>
  <Account />
</Session.Provider>);

it('separa las descargas ficticias de la ayuda institucional del postulante', () => {
  show('student');
  expect(screen.queryByRole('link', {
    name: /Descargar muestra:/
  })).toBeNull();
  expect(screen.getByRole('link', {
    name: 'Abrir entrenamiento documental'
  }).getAttribute('href')).toBe('/p2/entrenamiento');
  expect(screen.getAllByRole('link', {
    name: /Descargar original:/
  })).toHaveLength(3);
});

it('el evaluador recibe su guía sin formularios ni muestras del postulante', () => {
  show('staff', ['evaluator']);
  expect(screen.getByRole('heading', {
    name: 'Evaluador'
  })).toBeTruthy();
  expect(screen.queryByRole('heading', {
    name: 'Muestras PDF para practicar'
  })).toBeNull();
  expect(screen.queryByRole('heading', {
    name: 'Postulante'
  })).toBeNull();
});

it('conserva una combinación válida de postulante y revisor', () => {
  show('student', ['reviewer']);
  expect(screen.getByRole('heading', {
    name: 'Postulante'
  })).toBeTruthy();
  expect(screen.getByRole('heading', {
    name: 'Revisor'
  })).toBeTruthy();
});

it('el espacio de práctica identifica expresamente sus seis archivos ficticios', () => {
  render(<Session.Provider value={{
    session: {
      training_enabled: true,
      user: {
        role: 'student'
      }
    }
  }}>
    <TrainingSamples />
  </Session.Provider>);
  expect(screen.getAllByRole('link', {
    name: /Descargar muestra:/
  })).toHaveLength(6);
  expect(screen.getByText(/exclusivamente ficticios/)).toBeTruthy();
});
