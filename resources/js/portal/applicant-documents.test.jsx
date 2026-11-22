import React from 'react';
import {describe,it,expect,afterEach} from 'vitest';
import {render,screen,cleanup} from '@testing-library/react';
import {DocumentLink,DocumentValidation} from './shared';
afterEach(cleanup);
describe('Documentos en cuarentena',()=>{
 for(const state of ['pending','blocked','error','expired'])it(`no ofrece abrir un archivo ${state}`,()=>{render(<DocumentLink doc={{id:8,name:'respaldo.pdf',version:2,security:{state,download_allowed:false}}}/>);expect(screen.queryByRole('link')).toBeNull();expect(screen.getByRole('status').textContent).not.toBe('Análisis válido');});
 it('permite abrir únicamente un archivo con autorización de descarga',()=>{render(<DocumentLink doc={{id:8,name:'respaldo.pdf',version:2,security:{state:'clean',download_allowed:true}}}/>);expect(screen.getByRole('link').getAttribute('href')).toContain('/documents/8');});
 it('trata una respuesta sin estado como pendiente',()=>{render(<DocumentLink doc={{id:8,name:'respaldo.pdf',version:2}}/>);expect(screen.queryByRole('link')).toBeNull();expect(screen.getByRole('status').textContent).toBe('Análisis pendiente');});
});

describe('Validación técnica separada de la habilitación',()=>{
 it('explica el bloqueo sin anunciar una inhabilitación',()=>{render(<DocumentValidation validation={{state:'pending',ready:false}}/>);expect(screen.getByRole('status').textContent).toContain('Validación documental pendiente');expect(screen.getByRole('status').textContent).toContain('La decisión académica y los resultados históricos se conservan.');});
 it('informa la vigencia solo con una respuesta afirmativa',()=>{render(<DocumentValidation validation={{state:'current',ready:true}}/>);expect(screen.getByRole('status').textContent).toContain('Validación documental vigente');});
 it('trata una respuesta incompleta como pendiente',()=>{render(<DocumentValidation/>);expect(screen.getByRole('status').textContent).toContain('Validación documental pendiente');});
});
