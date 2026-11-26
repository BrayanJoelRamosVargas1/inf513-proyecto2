import { describe, it, expect } from 'vitest';
import { pageTitle } from './shell';
describe('Título de página por ruta (F-21)', () => {
  it('identifica cada pantalla principal', () => {
    expect(pageTitle('/convocatorias')).toBe('Convocatorias · Auxiliares FICCT');
    expect(pageTitle('/convocatorias/12')).toBe('Convocatoria · Auxiliares FICCT');
    expect(pageTitle('/convocatorias/12/auditoria')).toBe('Auditoría de la convocatoria · Auxiliares FICCT');
    expect(pageTitle('/postulaciones/7/rectificaciones')).toBe('Rectificaciones · Auxiliares FICCT');
    expect(pageTitle('/cuenta')).toBe('Mi cuenta · Auxiliares FICCT');
    expect(pageTitle('/entrenamiento')).toBe('Materiales de entrenamiento · Auxiliares FICCT');
  });
  it('usa Inicio para la raíz y para rutas desconocidas', () => {
    expect(pageTitle('/')).toBe('Inicio · Auxiliares FICCT');
    expect(pageTitle('/ruta-inexistente')).toBe('Inicio · Auxiliares FICCT');
  });
  it('las rutas más específicas ganan a las generales', () => {
    expect(pageTitle('/convocatorias/nueva')).toBe('Nueva convocatoria · Auxiliares FICCT');
    expect(pageTitle('/ofertas/3/resultados')).toBe('Resultados de la oferta · Auxiliares FICCT');
  });
});
