import React from 'react';
import {describe,it,expect,afterEach,vi} from 'vitest';
import {render,screen,cleanup,fireEvent,waitFor} from '@testing-library/react';
import {Alert,ErrorBoundary,Form} from './shared';
import {label,message} from './api';
afterEach(cleanup);
describe('Mensajes y protección del cliente',()=>{
 it('traduce los estados del concurso',()=>{expect(label('enabled')).toBe('Habilitado');expect(label('paid')).toBe('Simulación exitosa');});
 it('muestra todos los errores 422 sin ocultar campos',()=>{expect(message({response:{status:422,data:{errors:{email:['Correo obligatorio'],password:['Contraseña obligatoria']}}}})).toBe('Correo obligatorio Contraseña obligatoria');});
 it('no filtra errores internos del servidor',()=>{expect(message({response:{status:500,data:{message:'password=secreto sql internals'}}})).not.toContain('secreto');});
 it('explica una sesión revocada',()=>{expect(message({response:{status:401}})).toContain('permisos');});
 it.each([[403,'permiso'],[404,'disponibles'],[429,'solicitudes']])('explica en español el estado %s', (status,word)=>{expect(message({response:{status,data:{message:'Internal English detail'}}})).toContain(word);});
 it('traduce decisiones y oculta incluso errores internos por campo',()=>{expect(label('renunciation')).toBe('Renuncia a plaza');expect(label('approved')).toBe('Aprobada');expect(label('expired_window_12')).toBe('Cierre de ventana vencida');expect(message({response:{status:500,data:{errors:{db:['SQL password=privado']}}}})).not.toContain('privado');});
 it('presenta el error con un rol accesible',()=>{render(<Alert text="Archivo no disponible"/>);expect(screen.getByRole('alert').textContent).toBe('Archivo no disponible');});
 it('no muestra un aviso vacío',()=>{render(<Alert text=""/>);expect(screen.queryByRole('alert')).toBeNull();});
 it('conserva el contenido cuando no hay fallo de render',()=>{render(<ErrorBoundary><p>Contenido disponible</p></ErrorBoundary>);expect(screen.getByText('Contenido disponible')).toBeTruthy();});
 it('bloquea envíos repetidos mientras procesa y conserva los datos',async()=>{let finish;const handler=vi.fn(()=>new Promise(resolve=>{finish=resolve;}));render(<Form onSubmit={handler}><label>Referencia<input name="ref" defaultValue="CPD-123"/></label></Form>);fireEvent.submit(screen.getByRole('button',{name:'Guardar'}).closest('form'));expect(screen.getByRole('button',{name:'Procesando…'}).disabled).toBe(true);fireEvent.submit(screen.getByRole('button').closest('form'));expect(handler).toHaveBeenCalledTimes(1);expect(handler.mock.calls[0][0].get('ref')).toBe('CPD-123');finish();await waitFor(()=>expect(screen.getByRole('button',{name:'Guardar'}).disabled).toBe(false));});
 it('muestra validación del servidor y permite corregir y reintentar',async()=>{const handler=vi.fn().mockRejectedValueOnce({response:{status:422,data:{errors:{score:['El puntaje excede el máximo.']}}}}).mockResolvedValueOnce({});render(<Form onSubmit={handler}><label>Puntaje<input name="score" defaultValue="41"/></label></Form>);fireEvent.submit(screen.getByRole('button').closest('form'));await screen.findByRole('alert');expect(screen.getByRole('alert').textContent).toContain('excede');expect(screen.getByLabelText('Puntaje').value).toBe('41');fireEvent.change(screen.getByLabelText('Puntaje'),{target:{value:'40'}});fireEvent.submit(screen.getByRole('button').closest('form'));await waitFor(()=>expect(screen.queryByRole('alert')).toBeNull());expect(handler).toHaveBeenCalledTimes(2);});
});
