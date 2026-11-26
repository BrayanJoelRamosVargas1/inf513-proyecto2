import React from 'react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, cleanup, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { AppShell } from './shell';
import { Sandbox } from './sandbox';
import { Session } from './shared';
import { api } from './api';
vi.mock('./api', async original => ({
  ...(await original()),
  api: {
    get: vi.fn(),
    post: vi.fn(),
    delete: vi.fn()
  }
}));
afterEach(() => {
  cleanup();
  vi.resetAllMocks();
});

const failure = {
  response: {
    status: 503,
    data: {
      message: 'Internal private error'
    }
  }
};

describe('Operaciones recuperables ante fallos de red', () => {
  it('conserva la sesión, informa el fallo y permite volver a salir', async () => {
    const setSession = vi.fn();
    api.get.mockImplementation(path => Promise.resolve({
      data: path === '/me/navigation' ? {
        items: []
      } : {
        preferences: {
          theme: 'adultos',
          mode: 'dia',
          font: 100,
          contrast: false
        }
      }
    }));
    api.post.mockImplementation(path => path === '/logout' ? Promise.reject(failure) : Promise.resolve({
      data: {
        total: 1
      }
    }));
    render(<MemoryRouter initialEntries={['/cuenta']}>
      <Session.Provider value={{
        session: {
          user: {
            name: 'Prueba',
            role: 'student',
            email: 'test@example.test'
          },
          roles: []
        },
        setSession
      }}>
        <AppShell />
      </Session.Provider>
    </MemoryRouter>);
    fireEvent.click(screen.getByRole('button', {
      name: 'Salir'
    }));
    await screen.findByRole('alert');
    expect(screen.getByRole('alert').textContent).toContain('Intenta nuevamente');
    expect(setSession).not.toHaveBeenCalled();
    api.post.mockResolvedValue({
      data: {}
    });
    fireEvent.click(screen.getByRole('button', {
      name: 'Salir'
    }));
    await waitFor(() => expect(setSession).toHaveBeenCalledWith(null));
  });
  it('no oculta un método si falla la desactivación y permite reintentar', async () => {
    let active = true;
    api.get.mockImplementation(() => Promise.resolve({
      data: {
        methods: active ? [{
          id: 7,
          label: 'Método de práctica A',
          active: true
        }] : [],
        plans: [],
        installments: [],
        payments: [],
        summary: {
          paid: 0,
          failed: 0,
          pending_installments: 0
        }
      }
    }));
    api.delete.mockRejectedValueOnce(failure).mockImplementationOnce(() => {
      active = false;
      return Promise.resolve({});
    });
    render(<Sandbox />);
    fireEvent.click(await screen.findByRole('button', {
      name: 'Desactivar #7'
    }));
    await screen.findByRole('alert');
    expect(screen.getByRole('button', {
      name: 'Desactivar #7'
    }).disabled).toBe(false);
    fireEvent.click(screen.getByRole('button', {
      name: 'Desactivar #7'
    }));
    await waitFor(() => expect(screen.queryByRole('button', {
      name: 'Desactivar #7'
    })).toBeNull());
    expect(api.delete).toHaveBeenCalledTimes(2);
  });
});
