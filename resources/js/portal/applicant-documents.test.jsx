import React from 'react';
import {describe,it,expect,afterEach} from 'vitest';
import {render,screen,cleanup} from '@testing-library/react';
import {DocumentLink} from './shared';
afterEach(cleanup);
describe('Documentos en cuarentena',()=>{
 for(const state of ['pending','blocked','error','expired'])it(`no ofrece abrir un archivo ${state}`,()=>{render(<DocumentLink doc={{id:8,name:'respaldo.pdf',version:2,security:{state,download_allowed:false}}}/>);expect(screen.queryByRole('link')).toBeNull();expect(screen.getByRole('status').textContent).not.toBe('Análisis válido');});
 it('permite abrir únicamente un archivo con autorización de descarga',()=>{render(<DocumentLink doc={{id:8,name:'respaldo.pdf',version:2,security:{state:'clean',download_allowed:true}}}/>);expect(screen.getByRole('link').getAttribute('href')).toContain('/documents/8');});
 it('trata una respuesta sin estado como pendiente',()=>{render(<DocumentLink doc={{id:8,name:'respaldo.pdf',version:2}}/>);expect(screen.queryByRole('link')).toBeNull();expect(screen.getByRole('status').textContent).toBe('Análisis pendiente');});
});
