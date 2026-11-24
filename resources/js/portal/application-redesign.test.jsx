import React from 'react';
import {describe,it,expect,vi,afterEach} from 'vitest';
import {render,screen,cleanup} from '@testing-library/react';
import {MemoryRouter,Routes,Route} from 'react-router-dom';
import {Application} from './main';

const fixture=vi.hoisted(()=>({data:null}));
vi.mock('./shared',async importOriginal=>({...await importOriginal(),useData:()=>[fixture.data,'',vi.fn()]}));
afterEach(cleanup);
function show({grades=[],reviewer=false,authority=false,owner=false}={}){
  fixture.data={application:{status:'submitted',eligibility:'enabled',participation:'active'},
    call:{id:1,title:'Convocatoria de prueba'},offer:{title:'Software',documents:{}},student:{name:'Postulante de ensayo'},
    documents:[],assignments:[{id:2}],reviews:[],criteria:{},grades,reviewer,authority,owner};
  render(<MemoryRouter initialEntries={['/postulaciones/1']}><Routes><Route path="/postulaciones/:id" element={<Application/>}/></Routes></MemoryRouter>);
}
describe('Navegación de rectificaciones por responsabilidad',()=>{
  it('no ofrece al evaluador un historial que el servidor rechazaría por falta de notas',()=>{
    show();
    expect(screen.queryByRole('link',{name:'Rectificaciones e historial de calificaciones →'})).toBeNull();
    expect(screen.queryByRole('heading',{name:'Expediente digital'})).toBeNull();
  });
  it('mantiene el acceso al historial de notas visibles para su evaluador',()=>{
    show({grades:[{id:1,area:'Software',score:75,status:'confirmed'}]});
    expect(screen.getByRole('link',{name:'Rectificaciones e historial de calificaciones →'}).getAttribute('href')).toContain('/rectificaciones');
  });
  it('conserva el acceso del revisor a rectificaciones aunque aún no existan notas de examen',()=>{
    show({reviewer:true});
    expect(screen.getByText(/Abre los documentos disponibles y contrástalos/)).toBeTruthy();
    expect(screen.queryByText(/Carga los documentos requeridos y confirma el envío/)).toBeNull();
    expect(screen.getByRole('link',{name:'Rectificaciones e historial de calificaciones →'})).toBeTruthy();
  });
  it('no envía al postulante con calificaciones al formulario reservado de rectificaciones',()=>{
    show({owner:true,grades:[{id:1,area:'Software',score:75,status:'confirmed'}]});
    expect(screen.queryByRole('link',{name:'Rectificaciones e historial de calificaciones →'})).toBeNull();
    expect(screen.getByRole('heading',{name:'Calificaciones visibles'})).toBeTruthy();
  });
});
