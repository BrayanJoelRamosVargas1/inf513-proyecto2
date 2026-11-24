import React from 'react';
import {describe,it,expect,vi,afterEach} from 'vitest';
import {render,screen,cleanup,fireEvent,waitFor} from '@testing-library/react';
import {MemoryRouter} from 'react-router-dom';
import {Auth,FooterVisitCounter} from './main';
import {Session} from './shared';
import {api} from './api';

vi.mock('./api',async importOriginal=>{
  const original=await importOriginal();
  return {...original,api:{post:vi.fn(),defaults:{headers:{common:{}}}},csrf:vi.fn()};
});
afterEach(()=>{cleanup();vi.clearAllMocks();});
describe('Acceso renovado y contador público',()=>{
  it('cuenta acceso y registro por separado sin repetir al cambiar campos',async()=>{
    api.post.mockResolvedValue({data:{total:8}});
    render(<MemoryRouter><Session.Provider value={{setSession:vi.fn()}}><Auth/></Session.Provider></MemoryRouter>);
    await screen.findByText('Visitas de esta vista: 8');
    fireEvent.change(screen.getByLabelText('Correo electrónico'),{target:{value:'postulante@example.test'}});
    expect(api.post).toHaveBeenCalledTimes(1);
    expect(api.post.mock.calls[0][0]).toBe('/visits/public');
    expect(api.post.mock.calls[0][1].path).toBe('/ingresar');
    fireEvent.click(screen.getByRole('button',{name:'Crear cuenta de postulante'}));
    await screen.findByRole('heading',{name:'Crea tu cuenta'});
    await waitFor(()=>expect(api.post).toHaveBeenCalledTimes(2));
    expect(api.post.mock.calls[1][1].path).toBe('/registro');
    expect(api.post.mock.calls[0][1].key).not.toBe(api.post.mock.calls[1][1].key);
    expect(screen.getByLabelText('Confirmar contraseña').required).toBe(true);
    expect(screen.getByRole('contentinfo')).toBeTruthy();
    fireEvent.click(screen.getByRole('button',{name:'Volver a ingresar'}));
    await waitFor(()=>expect(api.post).toHaveBeenCalledTimes(3));
    expect(api.post.mock.calls[2][1].path).toBe('/ingresar');
    expect(api.post.mock.calls[2][1].key).not.toBe(api.post.mock.calls[0][1].key);
  });
  it('descarta una respuesta antigua al cambiar de pantalla',async()=>{
    let finish;
    api.post.mockImplementationOnce(()=>new Promise(resolve=>{finish=resolve;}))
      .mockResolvedValueOnce({data:{total:3}});
    const component=path=><MemoryRouter><FooterVisitCounter pathOverride={path} isPublic/></MemoryRouter>;
    const view=render(component('/ingresar'));
    view.rerender(component('/registro'));
    await screen.findByText('Visitas de esta vista: 3');
    finish({data:{total:999}});
    await waitFor(()=>expect(screen.queryByText(/999/)).toBeNull());
  });
  it('un fallo del contador no elimina el formulario ni muestra errores internos',async()=>{
    api.post.mockRejectedValue(new Error('SQL private database'));
    render(<MemoryRouter><Session.Provider value={{setSession:vi.fn()}}><Auth/></Session.Provider></MemoryRouter>);
    await screen.findByText('Contador temporalmente no disponible');
    expect(screen.getByRole('button',{name:'Entrar al sistema'})).toBeTruthy();
    expect(screen.queryByText(/SQL/)).toBeNull();
  });
});
