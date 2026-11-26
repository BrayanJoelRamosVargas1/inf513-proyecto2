import React from 'react';
import {describe,it,expect,vi,afterEach} from 'vitest';
import {render,screen,cleanup,fireEvent,waitFor,act} from '@testing-library/react';
import {useData,Loading,uuid} from './shared';
import {api} from './api';
vi.mock('./api',async original=>({...await original(),api:{get:vi.fn(),post:vi.fn(),delete:vi.fn()}}));
afterEach(()=>{cleanup();vi.resetAllMocks();vi.unstubAllGlobals();});

function Probe({path='/datos'}){
 const [d,error,reload]=useData(path);
 if(!d)return <Loading error={error}/>;
 return <section><p data-testid="valor">Valor {d.n}</p><button onClick={reload}>Recargar</button></section>;
}

describe('useData conserva los datos al recargar (F-11)',()=>{
 it('mantiene la pantalla y el foco mientras se revalida la misma ruta',async()=>{
  let release;
  api.get.mockResolvedValueOnce({data:{n:1}}).mockImplementationOnce(()=>new Promise(r=>{release=()=>r({data:{n:2}});}));
  render(<Probe/>);
  expect(await screen.findByText('Valor 1')).toBeTruthy();
  const button=screen.getByRole('button',{name:'Recargar'});
  button.focus();
  fireEvent.click(button);
  // Mientras llega la respuesta, los datos previos siguen visibles: no se desmonta el DOM ni se pierde el foco.
  expect(screen.getByText('Valor 1')).toBeTruthy();
  expect(screen.queryByRole('status')).toBeNull();
  expect(document.activeElement).toBe(button);
  await act(async()=>{release();});
  expect(await screen.findByText('Valor 2')).toBeTruthy();
  expect(document.activeElement).toBe(screen.getByRole('button',{name:'Recargar'}));
 });
 it('muestra el error si la recarga falla en lugar de dejar datos viejos sin avisar',async()=>{
  api.get.mockResolvedValueOnce({data:{n:1}}).mockRejectedValueOnce({response:{status:503,data:{message:'Servicio no disponible'}}});
  render(<Probe/>);
  fireEvent.click(await screen.findByRole('button',{name:'Recargar'}));
  expect(await screen.findByRole('alert')).toBeTruthy();
  expect(screen.queryByText('Valor 1')).toBeNull();
 });
 it('vacía los datos al cambiar de ruta de datos',async()=>{
  api.get.mockImplementation(path=>Promise.resolve({data:{n:path==='/a'?1:2}}));
  const {rerender}=render(<Probe path="/a"/>);
  expect(await screen.findByText('Valor 1')).toBeTruthy();
  rerender(<Probe path="/b"/>);
  await waitFor(()=>expect(screen.getByText('Valor 2')).toBeTruthy());
 });
});

describe('uuid funciona también sin contexto seguro (F-20)',()=>{
 it('usa randomUUID cuando existe',()=>{
  vi.stubGlobal('crypto',{randomUUID:()=>'11111111-1111-4111-8111-111111111111',getRandomValues:a=>a});
  expect(uuid()).toBe('11111111-1111-4111-8111-111111111111');
 });
 it('genera un UUID v4 válido con getRandomValues cuando randomUUID no está disponible',()=>{
  vi.stubGlobal('crypto',{getRandomValues:a=>{for(let i=0;i<a.length;i++)a[i]=(i*37+11)%256;return a;}});
  const value=uuid();
  expect(value).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
  expect(uuid()).toBe(value);
 });
});
