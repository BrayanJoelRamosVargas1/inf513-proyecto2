import React from 'react';
import {describe,it,expect,afterEach,vi} from 'vitest';
import {render,screen,cleanup,fireEvent} from '@testing-library/react';
import {DocumentLink,DocumentValidation} from './shared';
import {api} from './api';
afterEach(()=>{cleanup();vi.restoreAllMocks();});
describe('Documentos en cuarentena',()=>{
 for(const state of ['pending','blocked','error','expired'])it(`no ofrece abrir un archivo ${state}`,()=>{render(<DocumentLink doc={{id:8,name:'respaldo.pdf',version:2,security:{state,download_allowed:false}}}/>);expect(screen.queryByRole('link')).toBeNull();expect(screen.getByRole('status').textContent).not.toBe('Análisis válido');});
 it('permite abrir únicamente un archivo con autorización de descarga',()=>{render(<DocumentLink doc={{id:8,name:'respaldo.pdf',version:2,security:{state:'clean',download_allowed:true}}}/>);expect(screen.getByRole('link',{name:'Abrir respaldo.pdf'}).getAttribute('href')).toContain('/documents/8');expect(screen.getByRole('link',{name:'Descargar original: respaldo.pdf'}).getAttribute('href')).toContain('/documents/8/download');});
 it('trata una respuesta sin estado como pendiente',()=>{render(<DocumentLink doc={{id:8,name:'respaldo.pdf',version:2}}/>);expect(screen.queryByRole('link')).toBeNull();expect(screen.getByRole('status').textContent).toBe('Análisis pendiente');});
});

describe('Validación técnica separada de la habilitación',()=>{
 it('explica el bloqueo sin anunciar una inhabilitación',()=>{render(<DocumentValidation validation={{state:'pending',ready:false}}/>);expect(screen.getByRole('status').textContent).toContain('Validación documental pendiente');expect(screen.getByRole('status').textContent).toContain('La decisión académica y los resultados históricos se conservan.');});
 it('informa la vigencia solo con una respuesta afirmativa',()=>{render(<DocumentValidation validation={{state:'current',ready:true}}/>);expect(screen.getByRole('status').textContent).toContain('Validación documental vigente');});
 it('trata una respuesta incompleta como pendiente',()=>{render(<DocumentValidation/>);expect(screen.getByRole('status').textContent).toContain('Validación documental pendiente');});
});

it('muestra el tamaño original y advierte sobre imágenes pequeñas sin confundir seguridad con revisión académica',async()=>{
  vi.spyOn(api,'get').mockResolvedValue({data:{id:8,name:'imagen.png',version:2,mime:'image/png',size_bytes:2048,width:1,height:1,small_image:true,created_at:'2026-11-23'}});
  render(<DocumentLink doc={{id:8,name:'imagen.png',version:2,security:{state:'clean',download_allowed:true}}}/>);
  fireEvent.click(screen.getByRole('button',{name:'Ver detalles: imagen.png'}));
  await screen.findByText('Original cargado, sin conversión ni reducción');
  expect(screen.getByText(/Imagen de tamaño reducido/)).toBeTruthy();
  expect(screen.getByText(/El análisis de seguridad no confirma/)).toBeTruthy();
  expect(api.get).toHaveBeenCalledWith('/documents/8/metadata');
});
