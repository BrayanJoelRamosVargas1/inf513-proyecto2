# Rediseño integrado en el sistema existente — 22/11/2026

## Resultado

Se modificó directamente `tecnoproyectoCABa/sistema`. El tarball candidato se utilizó como referencia de presentación, sin reemplazar la aplicación por su backend anterior. No se creó otra aplicación, DEMO, repositorio o paquete ZIP. No hubo despliegue remoto.

La aplicación local está disponible en **http://127.0.0.1:8116/p2**. El proceso que ya estaba activo utiliza el runtime privado del iniciador `ABRIR_DEMO_FICCT_VALIDADA.ps1`; se sincronizaron allí únicamente los archivos afectados y la compilación. La verificación comprueba que esos archivos y el manifiesto coinciden con el código de `sistema`. Se mantuvo su base PostgreSQL y el supervisor documental existente. No se cambió la configuración histórica `.env` de la raíz: para utilizar la instalación operativa se mantiene el iniciador validado y el puerto 8116.

## Cambios de interfaz

- Ingreso y registro con estructura visual académica, formularios identificados y contador persistente público al pie.
- Navegación con iconos SVG locales y menú móvil desplegable. Escape cierra el menú, el foco vuelve al botón de apertura y los enlaces del menú cerrado no quedan disponibles mediante Tab. Se conserva el menú obtenido desde PostgreSQL.
- Encabezado con buscador, identificación de cuenta y salida. Inicio con jerarquía visual, acciones y descripción de responsabilidades. El administrador recibe un inicio de administración en lugar del bloque de expedientes.
- Sistema de estilos compartido para convocatorias, postulaciones, documentos, evaluación, resultados, administración, estadísticas y procesos institucionales. Tablas con desplazamiento contenido; las regiones de expedientes y clasificación pueden recibir foco con teclado.
- Preferencias plegadas inicialmente, conservando Niños/Jóvenes/Adultos, Día/Noche/Automático, contraste y escalado de fuentes.
- Corrección del buscador que desbordaba a 320 px y letra al 200 %. Concordancia de «1 plaza» en ofertas/resultados.
- El evaluador sin notas ya no recibe un enlace de rectificaciones que el servidor rechaza. El historial permanece disponible con notas visibles, y se conserva el acceso de revisor y autoridad. La autorización del servidor no cambió.

## Contador público y conservación de datos

La migración `2026_11_22_000001_add_public_portal_visit_receipts.php` añade una sola tabla de recibos públicos. No cambia `visit_receipts`, no fabrica un usuario visitante ni reinicia `page_visits`. El endpoint público acepta únicamente `/ingresar` y `/registro`, valida UUID, mantiene CSRF y usa su propio límite de solicitudes. Durante la integración se detectó y corrigió la interferencia entre el límite público y el de inicio de sesión.

El incremento y la creación del recibo se ejecutan en una transacción. Una clave repetida no suma otra visita y su reutilización para otra vista se rechaza, incluido el caso de conflicto de inserción concurrente. El cliente conserva la clave al repetirse el efecto de una misma vista y crea una nueva al navegar a otra, incluido volver de registro a ingreso. Se descartan respuestas antiguas al cambiar de pantalla.

Se tomó un respaldo privado antes de la migración. De las 49 tablas anteriores, **44 conservan su huella completa** tras el recorrido. En `access_events`, `audit_events`, `visit_receipts` y `migrations` se verificó que las filas anteriores permanecen idénticas y solo se agregaron registros. Los contadores anteriores no disminuyeron. Las preferencias del postulante ejercitadas durante la prueba se restauraron desde el respaldo previo.

Resultado: **50 tablas y 16 triggers**; los **35 archivos privados originales conservan sus hashes**. Las ocho huellas protegidas —contrato 8 CU/30 RF, Selection, Scores, ApplicantDocuments, DocumentProcessor, DocumentSafety, InstitutionalPreparation y supervisor compartido— coinciden con las tomadas antes de intervenir. DOC-21, cuarentena, ClamAV, subsanaciones y decisiones académicas no fueron sustituidos.

## Verificación ejecutada en esta intervención

| Comprobación | Resultado |
| --- | --- |
| PHPUnit en PostgreSQL `ficct_test`, con cachés separadas de la instalación activa | **276 pruebas, 1712 aserciones; 0 errores y fallos** |
| React/Vitest | **37 pruebas aprobadas** |
| Compilación de producción | PASS |
| Recorrido principal con cinco perfiles | 53 comprobaciones PASS |
| Configuración, resultados, participación/subsanaciones y rectificaciones | 6 comprobaciones adicionales PASS |
| axe en pantallas reales, con etiquetas WCAG A/AA seleccionadas | 53 revisiones sin hallazgos |
| Temas/modos y responsive | 48 combinaciones: 3 temas × 2 modos × 2 escalas/contrastes × 4 anchos |
| Anchos principales | 320, 390, 768 y 1440 px; sin desbordamiento de página |
| Vistas de detalle | También comprobadas a 320/390 px y fuentes 100/200 % |
| Descargas reales de consolidado | PDF: HTTP 200 y firma `%PDF`; XLSX: HTTP 200 y firma `PK` |
| Recibo repetido, reutilización entre vistas y ausencia de CSRF | Comprobados por HTTP real |
| Base, documentos originales y archivos de seguridad protegidos | PASS |

Las pruebas de navegador ejercitan lectura y navegación académica sobre las cuentas sintéticas existentes. Las mutaciones de negocio y sus restricciones se cubren en la regresión PHP; no se presentaron expedientes ni se cambiaron notas/publicaciones para obtener capturas nuevas. La inyección de axe se realizó con bypass de CSP únicamente en los contextos de QA, sin alterar la política de la aplicación. Las comprobaciones automatizadas y la prueba de teclado del menú no equivalen a una auditoría humana completa con lector de pantalla o a una certificación WCAG.

Evidencias nuevas: `evidence/frontend-redesign/final-verification.json`, `phpunit.xml`, `phpunit.log`, `react.log`, `build.log`, `browser.json`, `browser-detalles.json`, `downloads.json` y `pantallas/`. Los archivos `browser-intento-*` y la captura de fallo documentan intentos de integración anteriores; el dictamen final está en `final-verification.json` y los dos registros de navegador PASS. Las evidencias históricas de otras fases no se sobrescribieron.

## Archivos de código modificados o añadidos

1. `resources/js/portal/main.jsx`
2. `resources/js/portal/portal.css`
3. `resources/js/portal/processes.jsx`
4. `resources/js/portal/auth-redesign.test.jsx` — nuevo
5. `resources/js/portal/application-redesign.test.jsx` — nuevo
6. `app/Http/Controllers/Api/WorkspaceController.php`
7. `routes/api.php`
8. `database/migrations/2026_11_22_000001_add_public_portal_visit_receipts.php` — nueva
9. `tests/Feature/PublicVisitCounterTest.php` — nuevo

También se regeneró `public/build` y se actualizó el indicador de entrega de la raíz. Los respaldos y las herramientas de QA permanecen en `.local/frontend-redesign`, ignorado por Git y con acceso NTFS limitado al usuario, SYSTEM y administradores. No publicar esa carpeta, credenciales, sesiones o dumps.

## Pendientes reales

- GitHub, Tecnoweb y el tarball académico anterior **no contienen automáticamente este rediseño**. Su sincronización y eventual despliegue requieren una entrega controlada y autorización para el servidor remoto. No afirmar que ya está publicado allí.
- Falta confirmar la entrega formal en la plataforma, el plazo concreto y ensayar la defensa con la versión que se publique.
- La validación de todos los formularios no está certificada sin excepciones. Se conservaron las validaciones cliente/servidor existentes y se amplió la regresión del acceso y del contador; un recorrido de lectura no prueba cada combinación inválida de cada formulario.
- Evaluación humana de accesibilidad, carga, seguridad independiente y aceptación operativa por plataforma siguen separadas de este rediseño. WIN-02 no se instaló ni se habilitó inicio automático.
- Las decisiones normativas y autorizaciones universitarias permanecen pendientes. **Se conserva `NO_GO_INSTITUCIONAL`.**
