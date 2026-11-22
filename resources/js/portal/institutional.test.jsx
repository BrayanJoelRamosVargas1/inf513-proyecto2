import React from 'react';
import {it,expect,vi,afterEach} from 'vitest';
import {render,screen,cleanup} from '@testing-library/react';
import {MemoryRouter,Routes,Route} from 'react-router-dom';
import {InstitutionalDossier,InstitutionalIndex} from './institutional';
import {useData} from './shared';

vi.mock('./shared',async importOriginal=>({...await importOriginal(),useData:vi.fn()}));
afterEach(()=>{cleanup();vi.clearAllMocks();});
it('presenta diez condiciones pendientes sin ofrecer un botón de activación',()=>{
  useData.mockReturnValue([{dossier:{title:'Preparación de prueba',status:'technically_reviewed',version:4},readiness:{notice:'Sin autorización institucional.',gates:Array.from({length:10},(_,i)=>({id:'G'+i,name:'Condición '+i,state:'DOCUMENTED_UNVERIFIED'})),sources:[]},submissions:[],reviews:[],can_edit:false,can_review:false},'',vi.fn()]);
  render(<MemoryRouter initialEntries={['/institucional/5']}><Routes><Route path="/institucional/:id" element={<InstitutionalDossier/>}/></Routes></MemoryRouter>);
  expect(screen.getByText('No habilitado para concursos reales.')).toBeTruthy();
  expect(screen.getAllByText('Documentado, sin validación institucional')).toHaveLength(10);
  expect(screen.queryByRole('button')).toBeNull();
});
it('conserva el texto del snapshot y el resultado de revisión sin tratarlo como aprobación oficial',()=>{
  useData.mockReturnValue([{dossier:{title:'Preparación',status:'draft',version:4},readiness:{notice:'Pendiente.',gates:[],sources:[]},submissions:[{id:9,dossier_version:2,sha256:'1234',snapshot:{serialized:JSON.stringify({readiness:{official_activation_allowed:false}})}}],reviews:[{id:1,submission_id:9,decision:'observed',reason:'Falta vigencia.',created_at:'2026-11-21'}],can_edit:false,can_review:false},'',vi.fn()]);
  render(<MemoryRouter><InstitutionalDossier/></MemoryRouter>);
  expect(screen.getByText(/Falta vigencia/)).toBeTruthy();
  expect(screen.getByText(/"official_activation_allowed": false/)).toBeTruthy();
});
it('explica la falta de otra autoridad y no habilita la autorrevisión',()=>{
  useData.mockReturnValue([{calls:[],dossiers:[]},'',vi.fn()]);
  render(<MemoryRouter><InstitutionalIndex/></MemoryRouter>);
  expect(screen.getByRole('combobox',{name:'Otra autoridad para revisión técnica'}).disabled).toBe(true);
  expect(screen.getByText('No hay expedientes asignados.')).toBeTruthy();
});
it('presenta la cuarentena sin ofrecer la descarga del documento',()=>{
  useData.mockReturnValue([{dossier:{title:'Preparación',status:'draft',version:2},readiness:{notice:'Pendiente.',gates:[],sources:[{id:1,reference:'MUESTRA',version:1,title:'PDF de prueba',issued_on:'2026-11-21',pages:'1',classification:'historical_reference',notes:'Sin autenticidad acreditada',sha256:'a'.repeat(64),security:{state:'error',download_allowed:false}}]},submissions:[],reviews:[],can_edit:false,can_review:false},'',vi.fn()]);
  render(<MemoryRouter><InstitutionalDossier/></MemoryRouter>);
  expect(screen.getByText(/Análisis no disponible o fallido/)).toBeTruthy();
  expect(screen.queryByRole('link',{name:'Descargar fuente #1'})).toBeNull();
  expect(screen.getByText(/Descarga bloqueada/)).toBeTruthy();
});
