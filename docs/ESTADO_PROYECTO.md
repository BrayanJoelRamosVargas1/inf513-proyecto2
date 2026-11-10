# Estado y continuidad — DEMO 0.3

Fecha: 10/11/2026. **Incremento técnico ejecutado y probado; no constituye implementación institucional validada.** Continúa DEMO 0.2, commit base `2211f50`. El estado anterior completo está en `HISTORICO_ESTADO_DEMO_0_2.md`.

## Hecho en 0.3

- Subsanaciones antes de evaluación: observación registrada, documentos autorizados, plazo explícito DEMO, cargas privadas anexadas, nuevo envío congelado y revisión completa por responsable distinto del propietario. Los documentos enviados y sus revisiones se conservan en snapshots.
- Cierre explícito de ventanas vencidas con motivo, conservación de cargas y posibilidad de otra ventana. Las cargas pendientes o vencidas no se convierten en expediente vigente.
- Retiros de postulaciones y renuncias a plazas adjudicadas con solicitud, motivo/evidencia y decisión de otra autoridad. Historial de expedientes y calificaciones conservado.
- Revisiones de resultados por retiro/renuncia y nueva aprobación de publicación. Las publicaciones anteriores no se sobrescriben. Las renuncias retienen vacantes; no se adjudican reemplazantes automáticamente, tampoco al aprobar la nueva publicación.
- Detección de múltiples adjudicaciones visibles y manifestaciones de aceptar/diferir por propietario, publicación y versión. No se presume incompatibilidad ni se certifica aceptación institucional.
- Nuevas pantallas, estados en bandeja, reportes PDF/XLSX con participación/vacantes y migración incremental 000004. Se conservan permisos y controles de 0.2.

## Verificado ahora

| Control | Resultado / evidencia |
|---|---|
| Base 0.2 antes de cambiar | 70 pruebas / 229 aserciones y restauración: `evidence/baseline-02/`. |
| Regresión y pruebas finales 0.3 | **102 pruebas / 311 aserciones, cero fallos**. Las 70 anteriores más 32 de participación: `evidence/tests.txt`, `phpunit.xml`, `release-03.json`. |
| Concurrencia | Once carreras con procesos separados: tres originales, tres de gobernanza y cinco de participación. Ver los tres informes de concurrencia. |
| Migración incremental aplicada | Quince tablas originales idénticas en filas/columnas previas: `migration-03-preservation.json`. |
| Restauración con nuevos flujos | Veintiuna tablas y archivos idénticos, nueve triggers; incluye subsanaciones, decisiones, vacante y manifestación: `participation-restore.json`. |
| Restauración de DEMO | Veintiuna tablas idénticas, doce documentos SHA-256 y nueve triggers: `restore.json`. |
| HTTP real | Seguridad/CSRF y lectura de procesos/adjudicaciones/exportaciones: `security-http.json`, `participation-http.json`. |
| Exportaciones | PDF de una página, trece columnas sin recortes, Excel con valores como texto: reportes 03 y `screenshots-03/reporte-03.png`. |
| Navegador | Expediente y adjudicaciones; móvil 390px sin desbordamiento global: `screenshots-03/`. |
| Instalación separada | Carpeta/dependencias/base nuevas en este equipo; migraciones, seed, recorrido, vistas y rutas: `clean-install-03.json`. |
| Calidad | Pint, sintaxis PHP, contrato canónico y dependencias: `release-03.json`. |

Las pruebas de modificaciones de expedientes/notas/selecciones se ejecutaron en `ficct_test`. La DEMO existente conserva las calificaciones y adjudicación de 0.2; solo recibe metadatos/snapshots de conservación y auditoría de accesos/exportaciones. No se afirma auditoría externa, carga institucional, accesibilidad certificada ni pruebas en una máquina sin herramientas.

## Contraste normativo y límite institucional

**Originales: NOT_VERIFIED en esta continuación.** No están en las rutas consultadas los cuatro PDF ni las tres fotografías. Se revisaron la auditoría heredada, el inventario y fuentes públicas relacionadas, sin usarlas para inventar un procedimiento general. Ver `03-CONTRASTE_NORMATIVO_DEMO_0_3.md`.

Siguen pendientes plazos y autoridades formales, subsanación posterior a evaluar, recursos, efectos de retirar sobre límites, compatibilidades, aceptación institucional, efectos de renunciar, cobertura de vacantes y firmas. Las decisiones técnicas DEMO están identificadas. Se conservan el nuevo examen de desempate, las matrices por convocatoria y los calendarios originales, con sus limitaciones previas.

Ocho CU y treinta RF intactos; SHA-256 canónico: `fc7bd40b944002fc698eb56234816de4b36ce4ee11486e335673edd8f17e11e2`.

## Continuar y entregar

Iniciar `INICIAR_DEMO.ps1`; portal `http://127.0.0.1:8099`. Credenciales sintéticas privadas: `.local/ACCESOS_DEMO.txt`. No se incluyen contraseñas, `.env`, archivos privados, respaldos o dependencias locales en el ZIP. Pruebas únicamente con `scripts/test.py` y `ficct_test`; nunca `migrate:fresh` sobre la DEMO.

Migración, decisiones técnicas, pruebas y recorrido: `DEMO_0_3_CAMBIOS_Y_VALIDACION.md`. Trazabilidad: `02-TRAZABILIDAD.md`. Archivos: `ARCHIVOS_CAMBIADOS_0_3.md`.

Siguiente trabajo: recuperar y contrastar originales; confirmar procedimientos institucionales; implementar recursos, firmas y cobertura de vacantes únicamente según decisiones confirmadas; pruebas independientes de carga, accesibilidad, seguridad e infraestructura. No ampliar hacia pagos, becas o gestión laboral posterior.

**No se declara el sistema oficial, íntegramente terminado para toda operación universitaria ni apto para despliegue institucional.**
