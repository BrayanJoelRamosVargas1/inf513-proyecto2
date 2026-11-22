# FICCT / UAGRM — Dictamen de preparación para uso institucional

**Fecha:** 21 de noviembre de 2026. **Objeto:** versión entregada en `INF513_P2_GRUPO09_ENTREGA_VERIFICADA.zip` y lectura del repositorio público `BrayanJoelRamosVargas1/inf513-proyecto2` (rama `main`, último commit observado `72e0911faaebae9ddd1f4ff4799cb2de79ef8ef0`).

**Decisión al momento de la revisión: NO APTO PARA EJECUTAR UN CONCURSO INSTITUCIONAL REAL.** Es un bloqueo de habilitación oficial, no un juicio de inutilidad del software. Se conservan las capacidades académicas ya construidas y no se autoriza ni se realiza ninguna alteración de datos, despliegue o migración en el servidor remoto.

## Evidencia comprobable en el código

| ID | Ubicación | Observación | Implicación |
|---|---|---|---|
| P0-01 | `config/ficct.php:5`; `app/Domain/Selection.php:47-50,62-68`; `app/Domain/Workspace.php:16-19` | `official_enabled=false`; las nuevas convocatorias tienen `mode=DEMO` y la recepción exige ese modo. | No existe flujo de operación oficial activable por una configuración aprobada. No basta cambiar el flag. |
| P0-02 | `app/Domain/Selection.php:430-445` | Ranking ordena habilitados por nota final, sin filtro de mínimo aprobatorio institucional confirmado. | Se requiere aprobación formal del criterio por modalidad para determinar plaza desierta. |
| P0-03 | `app/Domain/Scores.php:22-24,51-63` | Ponderación y redondeo `HALF_UP_FINAL` implementados con la configuración académica. | Sin rúbrica y fórmula autorizada para cada régimen, no emitir notas oficiales. |
| P0-04 | `app/Http/Controllers/PortalController.php:57-75`; `app/Domain/Workspace.php:22-32` | Autoregistro con `faculty` y `career` escritos por el propio solicitante; se usa el perfil para el alcance. | No verifica estudiante activo, récord académico, afiliación o condiciones de convocatoria en el sistema oficial. |
| P0-05 | `app/Domain/Selection.php:540-579` | `approve()` publica un snapshot DEMO con `minutes` textual; no hay acreditación de resolución HCF / Decano o circuito de firma institucional. | La publicación técnica no es nombramiento ni acto oficial. |
| P0-06 | `app/Domain/Selection.php:47-68`; `app/Domain/DemoJourney.php`; `database/seeders/DemoSeeder.php` | Datos de práctica y recorridos sintéticos coexisten con históricos representados parcialmente. | Se necesita migración de datos oficiales verificados, segregación de entornos e importación solo de fuentes autorizadas. |
| P1-01 | `routes/api.php`, `app/Http/Middleware/SecureResponse.php` | Ya existen API, sesión, CSRF y controles de cabeceras. | Reutilizar, ampliando prueba independiente de todas las rutas, privacidad y robustez bajo cargas representativas. |
| P1-02 | `app/Domain/Access.php`, migraciones | Transacciones, ámbitos de acceso y algunos controles de integridad. | Validar mínimos privilegios reales en PostgreSQL, revocación y auditoría externa resistente a pérdida de BD. |
| P1-03 | `app/Http/Controllers/Api/SandboxController.php` | Los pagos son simulados y separados de la postulación. | El módulo cumple el enunciado académico; no debe convertirse en cobro real ni exigirse para postular sin autorización específica. |
| P1-04 | `docs/INF513_P2_IMPLEMENTACION.md`; `evidence/inf513-p2/instalacion-phpunit.xml` | React, Laravel REST, PostgreSQL, pruebas y registros de recorridos. | Evidencia importante pero no equivale a pentest externo, prueba de desastre, aprobación jurídica o autorización de tratamiento de datos. |

## Fuentes reales de 2026

- Las convocatorias FICCT **01/2026** y **02/2026** adjuntas al proyecto son fuentes históricas, con plazos ya transcurridos. Sus tablas, requisitos y fechas se deben conservar literalmente, vinculando cada dato al documento y su versión, sin reabrir automáticamente postulaciones.
- El sitio oficial UAGRM publicó el **24/02/2026** el comunicado `COMUNICADO – AUXILIARES FICCT`, con repetición del examen de Redes y Soporte Técnico para estudiantes observados/notificados el **25/02/2026, 16:00**. Fuente: https://uagrm.edu.bo/index.php/carreras/ficct-ir/noticias/comunicado-auxiliares-ficct_b7da9a . Es un acto adicional que requiere un flujo de *reprogramación/nuevo examen autorizado por observación*, separado del *desempate por última plaza*.
- La Resolución Vicerrectoral 021/2023 y sus artículos son referencia normativa disponible, **sin confirmación de vigencia y modificaciones posteriores**. No asumir conformidad jurídica por coincidencia de PDF.

## Plan de ingeniería y aprobación, con entregables verificables

| Etapa | Entregable técnico/institucional | Prueba de aceptación |
|---|---|---|
| 1 — Congelamiento | Versión Git identificada, respaldos base + archivos + configuración, copia de ensayo aislada y prohibición de mezclas con bases de la materia. | Restauración sin pérdida; checksum de versión y evidencia reproducible. |
| 2 — Catálogo oficial | Históricos 01/02 + comunicados y correcciones, identificados con fuente, URL, resolución, firma/hash, fecha, alcance y vigencia. | Reconciliación de cada registro contra PDF/acto original por un responsable autorizado; sin datos inventados. |
| 3 — Identidad | Verificación de estudiante/autoridad por canal institucional aprobado o revisión humana formal con evidencia y separación de funciones. | No es posible habilitarse por declarar un texto arbitrario de carrera/facultad. |
| 4 — Motor de normativa | Versiones inmutables por convocatoria, régimen y oferta; umbral, fórmulas, escalas, redondeo, casos de ausencia, múltiples evaluadores. | Casos de prueba aprobados con ejemplos oficiales emitidos por autoridad académica. |
| 5 — Circuito oficial | Aprobación de convocatoria con acto facultativo, comisión acreditada, CPD verificado, exámenes ordinarios y extraordinarios, actas, resultados, recursos y publicación formal. | Cada transición exige autorización y documentos válidos; trazabilidad de firmas/actas; no hay autodesignación. |
| 6 — Seguridad/privacidad | Roles y privilegios efectivos, cifrado y protección de expedientes, plan de retención, logs fuera del sistema transaccional, detección y respuesta a incidentes. | Revisión externa y evidencia por rol, auditoría de accesos, restauración; ninguna clave ni documento personal en GitHub. |
| 7 — Operación | Infraestructura aprobada por la UAGRM, backups automatizados y ensayos de restauración, monitoreo, actualizaciones, contingencia, soporte y reversión. | SLO y capacidad autorizados; ensayo de recuperación medido; responsable técnico y suplente nominados. |
| 8 — Salida controlada | Prueba piloto con datos sintéticos o expresamente autorizados, aceptación por Vicedecanato, Decanato/CPD y administración TI según competencias. | Acta escrita de aceptación y autorización; solo entonces abrir a postulantes reales. |

## Acciones posibles sin depender de decisiones normativas

Se puede continuar desde ya con la refactorización de la distinción histórico/práctica/oficial, versión de fuentes documentales, pruebas de seguridad, observabilidad, copia de respaldo, catálogo de contratos API, automatización CI y pruebas de flujos sin datos personales. **Los pasos de activación para personas o concursos reales permanecen cerrados** hasta contar con autorización y criterios firmados.

## Acciones que no deben ejecutarse como atajo

No cambiar directamente `mode='DEMO'` a `OFICIAL`; no marcar todas las convocatorias anteriores como actuales; no usar credenciales compartidas, datos extraídos sin autorización, ni conectar bases del CPD; no trasladar pagos de práctica a pagos reales; no llamar a un ranking interno resolución académica; no ejecutar migraciones destructivas o pruebas sobre la BD institucional compartida.

## Alcance de esta revisión

Inspección estática del ZIP actual y tres archivos del GitHub público. Se corroboraron **189 pruebas / 1026 aserciones** en el XML adjunto y un registro de 18 pruebas React, pero **no se ejecutaron ahora** PHPunit, navegador ni ensayo de carga en el servidor docente. El sitio `tecnoweb.org.bo` se señaló como publicado y existe evidencia del recorrido remoto, pero aquí no se ha verificado su estado en vivo de forma independiente. Tampoco hubo auditoría de seguridad externa ni aceptación universitaria.
