import React from 'react';
import {it,expect,vi,afterEach} from 'vitest';
import {render,screen,cleanup,fireEvent} from '@testing-library/react';
import {MemoryRouter} from 'react-router-dom';
import {Session} from './shared';
import {Home,Applications,Calls,roleName} from './workspaces';
import {api} from './api';

afterEach(()=>{cleanup();vi.restoreAllMocks();});
const session=(role,roles=[])=>({user:{id:1,name:'Cuenta de ensayo',role},roles});
function show(s,child=<Home/>){
  vi.spyOn(api,'get').mockImplementation(async path=>({data:path==='/calls'?{calls:[]}:{applications:[{id:1,name:'Postulante sintético',title:'Software',call_status:'evaluation',status:'submitted',eligibility:'enabled',participation:'active',assigned_areas:['Software'],pending_areas:['Software']}]}}));
  render(<Session.Provider value={{session:s}}><MemoryRouter>{child}</MemoryRouter></Session.Provider>);
}
it('el revisor recibe su bandeja documental y no un panel de postulante o evaluador',async()=>{
  show(session('staff',['reviewer']));
  await screen.findByRole('link',{name:'Revisar expediente'});
  expect(screen.queryByRole('heading',{name:'Mis postulaciones'})).toBeNull();
  expect(screen.queryByRole('heading',{name:'Mis evaluaciones'})).toBeNull();
  expect(screen.queryByRole('link',{name:'Gestionar accesos'})).toBeNull();
  expect(api.get).toHaveBeenCalledWith('/applications?workspace=reviewer');
});
it('el evaluador ve sus áreas y un acceso a evaluación, sin bandeja documental',async()=>{
  show(session('staff',['evaluator']));
  await screen.findByRole('link',{name:'Abrir evaluación'});
  expect(screen.getByRole('columnheader',{name:'Mis áreas'})).toBeTruthy();
  expect(screen.queryByRole('link',{name:'Revisar expediente'})).toBeNull();
  fireEvent.change(screen.getByLabelText('Mostrar expedientes'),{target:{value:'pending'}});
  expect(screen.getByText('Por confirmar: Software')).toBeTruthy();
});
it('el postulante consulta solo su bandeja aunque tenga también responsabilidad de revisión',async()=>{
  show(session('student',['reviewer']));
  await screen.findByRole('link',{name:'Consultar seguimiento'});
  expect(api.get).toHaveBeenCalledWith('/applications?workspace=own');
  expect(api.get).toHaveBeenCalledWith('/applications?workspace=reviewer');
  expect(screen.getByRole('heading',{name:'Mis postulaciones'})).toBeTruthy();
  expect(screen.getByRole('heading',{name:'Revisión de expedientes'})).toBeTruthy();
});
it('la autoridad tiene seguimiento y gestión, sin controles del administrador técnico',async()=>{
  show(session('authority',['authority']));
  await screen.findByRole('link',{name:'Consultar expediente'});
  expect(screen.getByRole('heading',{name:'Gestión de convocatorias'})).toBeTruthy();
  expect(screen.queryByRole('link',{name:'Gestionar accesos'})).toBeNull();
  expect(screen.queryByRole('heading',{name:'Mis postulaciones'})).toBeNull();
});
it('el administrador sin responsabilidad académica recibe control técnico, no expedientes vacíos',()=>{
  show(session('admin'));
  expect(screen.getByRole('link',{name:'Gestionar accesos'})).toBeTruthy();
  expect(screen.queryByRole('heading',{name:'Mis postulaciones'})).toBeNull();
  expect(api.get).not.toHaveBeenCalled();
});
it('la cuenta de personal sin asignación recibe una explicación y ninguna tarea académica',()=>{
  show(session('staff'));
  expect(screen.getByRole('heading',{name:'Sin tareas asignadas'})).toBeTruthy();
  expect(roleName(session('staff',['reviewer','evaluator']))).toBe('Revisor · Evaluador');
  expect(api.get).not.toHaveBeenCalled();
});
it('una bandeja vacía conserva filtros y no inventa evaluaciones',async()=>{
  vi.spyOn(api,'get').mockResolvedValue({data:{applications:[]}});
  render(<MemoryRouter><Applications workspace="evaluator"/></MemoryRouter>);
  await screen.findByText('No hay expedientes para este filtro.');
  expect(screen.queryByRole('link',{name:'Abrir evaluación'})).toBeNull();
  expect(screen.getByLabelText('Mostrar expedientes')).toBeTruthy();
});
it('prioriza la recepción vigente sin presentar como abierta una convocatoria publicada fuera de plazo',async()=>{
  const now=Date.now(),date=days=>new Date(now+days*86400000).toISOString();
  vi.spyOn(api,'get').mockResolvedValue({data:{calls:[
    {id:1,title:'Historial',status:'archived',mode:'DEMO',opens_at:date(-20),closes_at:date(-10)},
    {id:2,title:'Publicada vencida',status:'published',mode:'DEMO',opens_at:date(-4),closes_at:date(-1)},
    {id:3,title:'Recepción actual',status:'published',mode:'DEMO',opens_at:date(-1),closes_at:date(2)},
  ]}});
  render(<Session.Provider value={{session:session('student')}}><MemoryRouter><Calls/></MemoryRouter></Session.Provider>);
  await screen.findByRole('link',{name:'Recepción actual'});
  expect(screen.getAllByRole('link')[0].textContent).toBe('Recepción actual');
  expect(screen.getByText('Recepción vigente · DEMO')).toBeTruthy();
  expect(screen.getByText('Recepción fuera de plazo · DEMO')).toBeTruthy();
});

it('el postulante encuentra su subsanación en pendientes con acceso directo al proceso',async()=>{
  vi.spyOn(api,'get').mockResolvedValue({data:{applications:[{id:9,title:'Corrección de identidad',status:'submitted',participation:'active',eligibility:'pending',pending_action:'correct_documents'}]}});
  render(<MemoryRouter><Applications workspace="own"/></MemoryRouter>);
  await screen.findByText('Corrección de identidad');
  fireEvent.change(screen.getByLabelText('Mostrar expedientes'),{target:{value:'pending'}});
  expect(screen.getByRole('link',{name:'Subsanar documentos'}).getAttribute('href')).toBe('/postulaciones/9/participacion');
});

it('el revisor separa lo que espera al estudiante de las subsanaciones que debe revisar',async()=>{
  vi.spyOn(api,'get').mockResolvedValue({data:{applications:[
    {id:9,title:'Espera al estudiante',status:'submitted',participation:'active',eligibility:'pending',pending_action:null},
    {id:10,title:'Corrección enviada',status:'submitted',participation:'active',eligibility:'pending',pending_action:'review_amendment'},
    {id:11,title:'Ventana vencida',status:'submitted',participation:'active',eligibility:'pending',pending_action:'close_expired_amendment'},
  ]}});
  render(<MemoryRouter><Applications workspace="reviewer"/></MemoryRouter>);
  await screen.findByText('Corrección enviada');
  fireEvent.change(screen.getByLabelText('Mostrar expedientes'),{target:{value:'pending'}});
  expect(screen.queryByText('Espera al estudiante')).toBeNull();
  expect(screen.getByRole('link',{name:'Revisar subsanación'}).getAttribute('href')).toBe('/postulaciones/10/participacion');
  expect(screen.getByRole('link',{name:'Resolver plazo vencido'}).getAttribute('href')).toBe('/postulaciones/11/participacion');
});
