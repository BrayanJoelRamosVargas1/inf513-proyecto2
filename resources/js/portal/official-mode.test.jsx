import { afterEach, describe, expect, it } from 'vitest';
import { isOperable, isTraining, setOperationalMode } from './api';

describe('modo operativo de la instalación', () => {
  afterEach(() => setOperationalMode('DEMO'));

  it('en entrenamiento solo se opera sobre convocatorias DEMO', () => {
    setOperationalMode('DEMO');
    expect(isTraining()).toBe(true);
    expect(isOperable({ mode: 'DEMO' })).toBe(true);
    expect(isOperable({ mode: 'OFICIAL' })).toBe(false);
    expect(isOperable({ mode: 'HISTORICAL' })).toBe(false);
  });

  it('en la instalación real solo se opera sobre convocatorias OFICIAL', () => {
    setOperationalMode('OFICIAL');
    expect(isTraining()).toBe(false);
    expect(isOperable({ mode: 'OFICIAL' })).toBe(true);
    expect(isOperable({ mode: 'DEMO' })).toBe(false);
    expect(isOperable({ mode: 'HISTORICAL' })).toBe(false);
  });

  it('sin dato del servidor se asume entrenamiento y una convocatoria ausente no es operable', () => {
    setOperationalMode(undefined);
    expect(isTraining()).toBe(true);
    expect(isOperable(undefined)).toBe(false);
  });
});
