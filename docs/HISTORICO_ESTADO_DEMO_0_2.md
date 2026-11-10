# Estado y continuidad — DEMO 0.2

Fecha: 10/11/2026. **Incremento ejecutable verificado; sistema integral y uso institucional pendientes.** Continúa DEMO 0.1 sin reconstruirla. El estado anterior está en `HISTORICO_ESTADO_DEMO_0_1.md`.

## Implementado

- Perfiles, activación/desactivación, permisos por convocatoria temporales, renovación, revocación individual, permisos efectivos e historial; invalidación de sesiones nuevas y antiguas.
- Control de autoelevación, último administrador activo, edición optimista y reasignación autorizada, conservando autor de notas anteriores.
- Solicitudes de rectificación de méritos/exámenes con motivo, evidencia por referencia, responsable y decisión separada; versiones inmutables y cálculo exacto.
- Revisión identificable de rankings, comparación de posiciones/valores, nuevos empates, selección bajo revisión y aprobación explícita de una nueva publicación con acta. Publicaciones anteriores inmutables.
- Consistencia mediante cerrojo transaccional común, versiones y restricciones PostgreSQL. Desempates vinculados a revisión y rondas sucesivas DEMO.
- Interfaz administrativa, cola de solicitudes, historial de calificaciones/resultados y reportes PDF/XLSX con revisión y alerta de publicación pendiente.

## Evidencias ejecutadas

| Control | Resultado | Evidencia |
|---|---|---|
| Línea de base nueva antes de cambios | 39 pruebas / 139 aserciones | `evidence/baseline-01/` |
| Regresión y pruebas nuevas finales | 70 pruebas / 229 aserciones, cero fallos; 39 anteriores conservadas + 31 nuevas | `evidence/tests.txt`, `phpunit.xml` |
| Concurrencia original | Tres carreras con procesos independientes: cupos de postulación, nota y publicación | `evidence/concurrency.json` |
| Concurrencia 0.2 | Correcciones incompatibles, revocación/nota, renovación simultánea | `evidence/governance-concurrency.json` |
| Migración incremental aplicada | Huellas de once tablas originales idénticas antes/después | `evidence/migration-preservation.json` |
| Restauración de DEMO y documentos | Quince tablas idénticas, 12 archivos SHA-256 y cuatro triggers | `evidence/restore.json` |
| Restauración con rectificaciones | Solicitudes, cinco versiones de calificaciones y una revisión conservadas; triggers restaurados | `evidence/governance-restore.json` |
| HTTP real, sesión y CSRF | Siete controles de seguridad originales repetidos; administración/cola/versiones y exportación ampliada verificadas | `evidence/security-http.json`, `governance-http.json` |
| PDF/XLSX ampliados | PDF de una página renderizado e inspeccionado; Excel con 11 columnas y celdas de texto | `evidence/reporte-demo-02.pdf/.xlsx`, `screenshots-02/reporte-02.png` |
| Navegador | Administración y rectificaciones en escritorio/móvil 390px sin desbordamiento global | `evidence/screenshots-02/` |
| Instalación independiente | Carpeta y dependencias nuevas, otra base, migraciones, seed, recorrido, vistas y rutas | `evidence/clean-install-02.json` |
| Calidad y dependencias | Pint pasó; Composer válido, sin avisos ni paquetes abandonados reportados en la consulta | `evidence/release-02.json` |

Los resultados no constituyen auditoría independiente ni pruebas exhaustivas de carga o accesibilidad. Las exportaciones/capturas/instalación con nombres anteriores a 0.2 siguen conservadas como antecedentes; no se cuentan como validaciones nuevas. Las decisiones sobre notas se ensayaron en `ficct_test`; la DEMO conserva sus calificaciones/publicación 0.1.

## Conservación y ejecución

Los ocho CU y treinta RF canónicos siguen intactos. No se modificaron matrices, fechas ni textos de fuentes históricas. La migración agrega tablas/metadatos y backfill conservador, sin inventar versiones pasadas o responsables desconocidos. Hay protección de PostgreSQL para publicaciones, versiones, revisiones y auditoría; no protege frente a un operador de infraestructura que desactive esa protección.

Iniciar con `INICIAR_DEMO.ps1`; portal `http://127.0.0.1:8099`. Credenciales sintéticas privadas en `.local/ACCESOS_DEMO.txt`. El ZIP no contiene `.env`, contraseñas, documentos privados, respaldos ni dependencias locales. Pruebas exclusivamente con `scripts/test.py` y `ficct_test`. No ejecutar `migrate:fresh` sobre la DEMO.

Detalles de migración, decisiones de bloqueo, alcance, pruebas, errores corregidos y recorrido: `DEMO_0_2_CAMBIOS_Y_VALIDACION.md`. Trazabilidad actualizada: `02-TRAZABILIDAD.md`.

## Siguiente incremento

Subsanaciones tras envío; retiros/renuncias; aceptación e incompatibilidades de varias plazas ganadas; reclamos; actas adjuntas y firmas; estadísticas/plantillas institucionales; clonación asistida; accesibilidad, carga y seguridad independiente. Están clasificados por impacto, dependencia y complejidad en el informe 0.2. Las rondas sucesivas de desempate y las alertas de revisión de reportes ya se implementaron.

## Validación institucional pendiente

Vigencia/autenticidad de fuentes; reglamento complementario; CPD real y formularios; composición de áreas/componentes, escala, redondeo y agregación multievaluador; condiciones de cada nuevo examen; autoridades/firmas; reclamos; datos, retención e infraestructura. La aprobación separada de correcciones y las rondas sucesivas son procedimientos técnicos DEMO, no normativa inventada.

OFICIAL y las convocatorias históricas permanecen bloqueados para escrituras del dominio. **No se declara el sistema oficial, completo para toda operación institucional ni listo para producción.**
