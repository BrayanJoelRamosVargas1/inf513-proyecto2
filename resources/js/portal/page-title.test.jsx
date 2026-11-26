import {describe,it,expect} from 'vitest';
import {pageTitle} from './main';

describe('Título de página por ruta (F-21)',()=>{
 it('identifica cada pantalla principal',()=>{
  expect(pageTitle('/convocatorias')).toBe('Convocatorias · FICCT · Proyecto 2');
  expect(pageTitle('/convocatorias/12')).toBe('Convocatoria · FICCT · Proyecto 2');
  expect(pageTitle('/convocatorias/12/auditoria')).toBe('Auditoría de la convocatoria · FICCT · Proyecto 2');
  expect(pageTitle('/postulaciones/7/rectificaciones')).toBe('Rectificaciones · FICCT · Proyecto 2');
  expect(pageTitle('/cuenta')).toBe('Mi cuenta · FICCT · Proyecto 2');
  expect(pageTitle('/entrenamiento')).toBe('Materiales de entrenamiento · FICCT · Proyecto 2');
 });
 it('usa Inicio para la raíz y para rutas desconocidas',()=>{
  expect(pageTitle('/')).toBe('Inicio · FICCT · Proyecto 2');
  expect(pageTitle('/ruta-inexistente')).toBe('Inicio · FICCT · Proyecto 2');
 });
 it('las rutas más específicas ganan a las generales',()=>{
  expect(pageTitle('/convocatorias/nueva')).toBe('Nueva convocatoria · FICCT · Proyecto 2');
  expect(pageTitle('/ofertas/3/resultados')).toBe('Resultados de la oferta · FICCT · Proyecto 2');
 });
});
