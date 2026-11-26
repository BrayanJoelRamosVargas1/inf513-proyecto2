import React from 'react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, cleanup, fireEvent, waitFor } from '@testing-library/react';
import { ForcedPassword } from './password';
import { Session } from './shared';
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

describe('cambio obligatorio de la clave inicial', () => {
  it('envía la clave nueva y libera la sesión al guardarla', async () => {
    api.post.mockResolvedValue({
      status: 204
    });
    const setSession = vi.fn();
    const session = {
      user: {
        name: 'Vicedecano FICCT'
      },
      must_change_password: true
    };
    render(<Session.Provider value={{
      session,
      setSession
    }}><ForcedPassword /></Session.Provider>);
    expect(screen.getByRole('heading', {
      name: /cambie su clave/i
    })).toBeTruthy();
    fireEvent.change(screen.getByLabelText('Clave actual'), {
      target: {
        value: 'clave-inicial-muy-larga'
      }
    });
    fireEvent.change(screen.getByLabelText('Clave nueva'), {
      target: {
        value: 'clave-propia-muy-larga-1'
      }
    });
    fireEvent.change(screen.getByLabelText('Repita la clave nueva'), {
      target: {
        value: 'clave-propia-muy-larga-1'
      }
    });
    fireEvent.click(screen.getByRole('button', {
      name: 'Guardar clave nueva'
    }));
    await waitFor(() => expect(api.post).toHaveBeenCalledWith('/me/password', {
      current_password: 'clave-inicial-muy-larga',
      password: 'clave-propia-muy-larga-1',
      password_confirmation: 'clave-propia-muy-larga-1'
    }));
    await waitFor(() => expect(setSession).toHaveBeenCalledWith({
      ...session,
      must_change_password: false
    }));
  });
});
