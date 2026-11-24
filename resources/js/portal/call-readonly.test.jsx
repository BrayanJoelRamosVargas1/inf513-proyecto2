import React from 'react';
import {it,expect,vi,afterEach} from 'vitest';
import {render,screen,cleanup} from '@testing-library/react';
import {MemoryRouter,Routes,Route} from 'react-router-dom';
import {Call} from './main';
import {Session} from './shared';
const fixture=vi.hoisted(()=>({data:null}));
vi.mock('./shared',async original=>({...await original(),useData:()=>[fixture.data,'',vi.fn()]}));
afterEach(cleanup);
it('permite consultar historia y reportes históricos sin ofrecer cambios de etapa',()=>{
 fixture.data={call:{id:7,title:'Convocatoria histórica',mode:'HISTORICAL',status:'published',source:'Antecedente conservado'},authority:true,offers:[],applications:[]};
 render(<Session.Provider value={{session:{user:{role:'authority'}}}}><MemoryRouter initialEntries={['/convocatorias/7']}><Routes><Route path="/convocatorias/:id" element={<Call/>}/></Routes></MemoryRouter></Session.Provider>);
 expect(screen.getByRole('link',{name:'Consultar historial del proceso →'}).getAttribute('href')).toBe('/convocatorias/7/auditoria');
 expect(screen.getByRole('link',{name:'Descargar consolidado PDF'}).getAttribute('href')).toContain('/calls/7/reports/pdf');
 expect(screen.queryByRole('button',{name:/Pasar a/})).toBeNull();
});
