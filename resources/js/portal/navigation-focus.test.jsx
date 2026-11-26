import React from 'react';
import '@testing-library/jest-dom/vitest';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, cleanup, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter, Link } from 'react-router-dom';
import { AppShell, Preferences } from './shell';
import { Session } from './shared';
import { api } from './api';
vi.mock('./api', async original => ({
  ...(await original()),
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn()
  }
}));
afterEach(() => {
  cleanup();
  vi.resetAllMocks();
  delete document.documentElement.dataset.theme;
  delete document.documentElement.dataset.contrast;
});

const prefs = {
  preferences: {
    theme: 'jovenes',
    mode: 'noche',
    font: 125,
    contrast: true
  }
};

describe('Foco y título al navegar (F-21)', () => {
  it('no roba el foco al cargar', async () => {
    api.get.mockImplementation(path => Promise.resolve({
      data: path === '/me/navigation' ? {
        items: [{
          id: 'account',
          path: '/cuenta',
          label: 'Mi cuenta'
        }]
      } : prefs
    }));
    api.post.mockResolvedValue({
      data: {
        total: 1
      }
    });
    render(<MemoryRouter initialEntries={['/cuenta']}>
      <Session.Provider value={{
        session: {
          user: {
            name: 'Prueba',
            role: 'student',
            email: 't@example.test'
          },
          roles: []
        },
        setSession: vi.fn()
      }}>
        <AppShell />
      </Session.Provider>
    </MemoryRouter>);
    await waitFor(() => expect(document.title).toBe('Mi cuenta · FICCT · Proyecto 2'));
    const main = document.getElementById('contenido');
    expect(main).toHaveAttribute('tabindex', '-1');
    expect(document.activeElement).not.toBe(main);
  });
  it('lleva el foco al contenido al navegar y actualiza el título', async () => {
    api.get.mockImplementation(path => Promise.resolve({
      data: path === '/me/navigation' ? {
        items: [{
          id: 'account',
          path: '/cuenta',
          label: 'Mi cuenta'
        }, {
          id: 'training',
          path: '/entrenamiento',
          label: 'Materiales'
        }]
      } : prefs
    }));
    api.post.mockResolvedValue({
      data: {
        total: 1
      }
    });
    render(<MemoryRouter initialEntries={['/cuenta']}>
      <Session.Provider value={{
        session: {
          training_enabled: true,
          user: {
            name: 'Prueba',
            role: 'student',
            email: 't@example.test'
          },
          roles: []
        },
        setSession: vi.fn()
      }}>
        <AppShell />
      </Session.Provider>
    </MemoryRouter>);
    fireEvent.click(await screen.findByRole('link', {
      name: /Materiales/
    }));
    await waitFor(() => expect(document.activeElement).toBe(document.getElementById('contenido')));
    expect(document.title).toBe('Materiales de entrenamiento · FICCT · Proyecto 2');
  });
});

describe('Preferences', () => {
  it('aplica la apariencia guardada al documento', async () => {
    api.get.mockResolvedValue({
      data: prefs
    });
    render(<Preferences />);
    await waitFor(() => expect(document.documentElement.dataset.theme).toBe('jovenes'));
    expect(document.documentElement.dataset.font).toBe('125');
    expect(document.documentElement.dataset.contrast).toBe('alto');
    expect(document.documentElement.dataset.mode).toBe('noche');
  });
  it('guarda los cambios y avisa', async () => {
    api.get.mockResolvedValue({
      data: prefs
    });
    api.put.mockResolvedValue({
      data: {}
    });
    render(<Preferences />);
    fireEvent.click(await screen.findByText('Apariencia y accesibilidad'));
    fireEvent.change(screen.getByLabelText('Tamaño de letra'), {
      target: {
        value: '150'
      }
    });
    fireEvent.click(screen.getByRole('button', {
      name: 'Aplicar preferencias'
    }));
    await waitFor(() => expect(api.put).toHaveBeenCalled());
    expect(api.put.mock.calls[0][0]).toBe('/me/preferences');
    expect(api.put.mock.calls[0][1]).toMatchObject({
      theme: 'jovenes',
      mode: 'noche',
      font: 150,
      contrast: true
    });
    expect(await screen.findByText('Preferencias guardadas.')).toBeInTheDocument();
  });
  it('muestra el error si no se pueden cargar', async () => {
    api.get.mockRejectedValue({
      response: {
        status: 500,
        data: {
          message: 'Servicio no disponible'
        }
      }
    });
    render(<Preferences />);
    expect(await screen.findByRole('alert')).toBeInTheDocument();
  });
});
