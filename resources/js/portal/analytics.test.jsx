import React from 'react';
import {afterEach,describe,it,expect,vi} from 'vitest';
import {render,screen,fireEvent,waitFor,cleanup} from '@testing-library/react';
import {MemoryRouter} from 'react-router-dom';
import {Metrics,Search} from './analytics';
import {api} from './api';

afterEach(()=>{cleanup();vi.restoreAllMocks();});
describe('Consulta de estadísticas y búsqueda',()=>{
  it('envía fechas y convocatoria y presenta cifras reales del recurso',async()=>{
    const get=vi.spyOn(api,'get').mockResolvedValue({data:{applications:2,calls:1,documents:6,confirmed_exams:1,active_awards:0,registered_users:null,eligibility:[{eligibility:'pending',total:2}],call_options:[{id:12,title:'Convocatoria de prueba'}],series:[{day:'2026-11-21',total:3}],traffic_scope:'Mi actividad',login_success:1,login_failed:0,popular:[]}});
    render(<MemoryRouter initialEntries={['/estadisticas']}><Metrics/></MemoryRouter>);
    await screen.findByRole('heading',{name:'Postulaciones de mi responsabilidad: 2'});
    expect(screen.getByText('Documentos de estas postulaciones: 6')).toBeTruthy();
    expect(screen.queryByText(/Usuarios registrados en la instalación/)).toBeNull();
    fireEvent.change(screen.getByLabelText('Desde'),{target:{value:'2026-11-01'}});
    fireEvent.change(screen.getByLabelText('Hasta'),{target:{value:'2026-11-21'}});
    fireEvent.change(screen.getByLabelText('Convocatoria'),{target:{value:'12'}});
    fireEvent.submit(screen.getByRole('button',{name:'Aplicar filtros'}).closest('form'));
    await waitFor(()=>expect(get).toHaveBeenLastCalledWith('/metrics?from=2026-11-01&to=2026-11-21&call_id=12'));
  });
  it('permite continuar la búsqueda sin perder la consulta ni el tipo visible',async()=>{
    const get=vi.spyOn(api,'get').mockResolvedValue({data:{calls:{data:[]},offers:[{id:2,title:'Oferta autorizada',call_id:3}],types:[{id:1,name:'Software'}],pagination:{page:1,last_page:2,total:13}}});
    render(<MemoryRouter initialEntries={['/buscar?q=Software']}><Search/></MemoryRouter>);
    await screen.findByRole('link',{name:'Oferta autorizada'});
    expect(screen.getByText('Software',{exact:true})).toBeTruthy();
    fireEvent.click(screen.getByRole('link',{name:'Página siguiente'}));
    await waitFor(()=>expect(get).toHaveBeenLastCalledWith('/search?q=Software&page=2'));
  });
});
