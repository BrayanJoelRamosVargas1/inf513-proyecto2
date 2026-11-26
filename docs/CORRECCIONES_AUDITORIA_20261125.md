# Correcciones de la auditoría general del 25/11/2026

Informe de origen: `documentos_tecno/AUDITORIA_GENERAL_20261125.md` y anexos A–F. Todo el trabajo está en la rama local `correcciones-auditoria`; el respaldo previo está en `respaldo-pre-correcciones`. No se publicó nada en GitHub ni en Tecnoweb, y no se tocó la base `ficct_demo` ni la instancia 8116.

**Verificación final:** PHPUnit 397 pruebas / 2281 aserciones sin fallos (solo sobre `ficct_test`); Vitest 80 pruebas en 18 archivos; `npm run build` correcto.

Leyenda: **Hecho** = corregido y probado · **Parcial** = corregido en parte · **Sin cambio** = decisión consciente · **Diferido** = requiere decisión o trabajo aparte (ver `DECISIONES_PENDIENTES_20261125.md`).

## Anexo A — backend

| Id | Estado | Qué se hizo / por qué no |
|---|---|---|
| B-01 | Hecho | Renovación del análisis antimalware: `ScanRenewal` y comando `ficct:scan-renew` (informa por defecto, `--apply` escribe), programado cada hora junto con `scan-worker` y `scan-maintain`. |
| B-02 | Hecho | Pasar a evaluación exige que no queden postulaciones enviadas, activas ni con elegibilidad pendiente. |
| B-03 | Hecho | Publicar exige un esquema calculable y redondeo HALF_UP final. |
| B-04 | Hecho | `DB_TIMEZONE` (por defecto `America/La_Paz`) en `config/database.php` y `.env.example`. |
| B-05 | Hecho | No se pueden dar permisos a quien postula en la misma convocatoria, ni postular con autoridad, revisión o evaluación en ella. |
| B-06 | Hecho | `ProcessController::mutate` usa un mapa explícito `OPERATIONS`, sin reflexión ni análisis de URL. |
| B-07 | Parcial | Catálogo institucional en caché por archivo y convocatoria memorizada en adjudicaciones. El cerrojo global se mantiene: quitarlo cambia el modelo de concurrencia. |
| B-08 | Parcial | Las revisiones de resultados guardan una instantánea sin datos personales (`ResultRevisions`). Las instantáneas de `publications` no cambian: `result-history.jsx` lee los nombres del ranking. |
| B-09 | Hecho | HSTS bajo HTTPS, Permissions-Policy, `TRUSTED_PROXIES`, `.env.production.example`. |
| B-10 | Hecho | Migración `2026_11_25_000002_add_missing_indexes` (11 índices, `IF NOT EXISTS`). |
| B-11 | Sin cambio | Se conserva el reporte heredado; la ruta nueva coexiste. |
| B-12 | Hecho | `apply` usa `Workspace::applicationBlock` y la convocatoria usa `PortalScope::calls`. |
| B-13 | Hecho | Desempate por id en el ranking y una sola instantánea (`ResultRevisions`). |
| B-14 | Hecho | Backend versionado en commits locales por tema. |
| B-15 | Hecho | `tests/Feature/AuditCorrectionsTest.php` cubre B-01 a B-05 y las demás correcciones. |
| B-16 | Sin cambio | Renombrar una migración ya aplicada rompería instalaciones existentes; el orden por nombre completo es correcto. |
| B-17 | Diferido | Doble trigger de inmutabilidad: retirarlo exige migración y revisión de la protección. |
| B-18 | Sin cambio | `config/ficct.php` y `DocumentSafety::scan` los usan las pruebas. |
| B-19 | Hecho | Variables de sesión, proxies y zona horaria añadidas a `.env.example` y `.env.production.example`. |
| B-20 | Diferido | Declarar `ext-pdo_pgsql` requiere regenerar `composer.lock`; el intento quedó colgado por red y se revirtió. |
| B-21 | Hecho | Migración `2026_11_25_000003_add_domain_value_checks` (CHECK `NOT VALID`; `calls.mode` admite OFICIAL). |
| B-22 | Diferido | Unificar idiomas de rutas rompe contratos públicos. |
| B-23 | Hecho | `InstitutionalRegister::csvCell` neutraliza `= + - @`, tabulador y retorno. |
| B-24 | Diferido | Cambiar el mensaje de correo ya registrado es una decisión de producto. |
| B-25 | Hecho | Bloqueo por cuenta y dirección en el login: 5 fallos en 300 s, se limpia al entrar bien. |
| B-26 | Hecho | Limitadores `mutations` y `uploads`, y límite al token CSRF. |
| B-27 | Hecho | `Participation::review` conserva `created_at`. |
| B-28 | Diferido | Si las postulaciones retiradas cuentan para el límite de asignaturas lo decide la institución. |
| B-29 | Hecho | `Scores` acepta áreas con clave numérica. |
| B-30 | Hecho | Constantes de puntaje centralizadas en `Scores`. |
| B-31 | Diferido | Validar la fecha del desempate necesita un hito de referencia definido. |
| B-32 | Hecho | `DemoSeeder` atómico; el archivo de accesos se escribe tras confirmar. |
| B-33 | Diferido | Consultas por petición en los middleware: optimización sin urgencia. |
| B-34 | Hecho | `InstitutionalController::download` devuelve 415 en lugar de fallar. |
| R-1 a R-7 | Hecho | Clases de operación (`app/Http/Operations`), modelos de lectura (`app/ReadModels`), `Selection` como fachada de cinco clases, `ResultRevisions`, `DocumentProcessor` dividido (358 → 219 líneas), 11 comandos en `app/Console/Commands`. |

## Anexo B — frontend, pruebas y scripts

| Id | Estado | Qué se hizo / por qué no |
|---|---|---|
| F-01, F-02 | Hecho | Todo versionado; `.gitattributes` con `* -text` conserva los bytes de los PDF. |
| F-03 | Hecho | `INICIAR_DEMO.ps1` comprueba `public/build/manifest.json` y lista migraciones pendientes; el README dice que hay que compilar. |
| F-04 | Hecho | `phpunit.xml` fija `ficct_test`; la guarda exige además host local. |
| F-05 | Hecho | La prueba s09 usa una caché de rutas temporal propia. |
| F-06 | Hecho | `cacheResult` desactivado; `scripts/test.py` limpia su carpeta. |
| F-07 | Hecho | Archivo de accesos configurable (`portal.access_file`); las pruebas lo apuntan a un temporal. |
| F-08 | Hecho | Concurrencia con `LARAVEL_STORAGE_PATH` temporal. |
| F-09 | Hecho | `FICCT_BASE_URL` en los recorridos HTTP. |
| F-10 | Hecho | `local_database.py` no reescribe un `.env` que apunta a otra base o puerto sin `FICCT_FORCE_ENV=1`. |
| F-11 | Hecho | `useData` conserva los datos al recargar (foco y mensajes se mantienen). |
| F-12 | Diferido | Mantener Blade y React en paralelo es decisión de arquitectura; se reduce la duplicación con `Operations` y `ReadModels`. |
| F-13 | Hecho | Mismo cambio que B-06. |
| F-14 | Hecho | Orden de etapas en una constante. |
| F-15 | Parcial | Se añadieron pruebas de `useData`, `uuid` y títulos. Faltan pantallas grandes (adjudicaciones, creación de convocatoria, preferencias, historial, convocatoria). |
| F-16 | Hecho | `ScoresTest` y pruebas de los reportes de la API. |
| F-17 | Hecho (documentado) | `scripts/README.md` lista los requisitos externos. Las guardas de aislamiento faltantes en `academic_business.cjs` no se tocaron. |
| F-18 | Hecho | README reescrito con cifras reales; el anterior está en `docs/HISTORICO_README.md`. |
| F-19 | Hecho | `/entrenamiento` registra su visita. |
| F-20 | Hecho | `uuid` sin contexto seguro, alerta accesible en lugar de `window.alert`, error de conexión visible. |
| F-21 | Parcial | Título por ruta y `scope="col"` en cabeceras React. Faltan gestión de foco al navegar y las tablas Blade. |
| F-22 | Diferido | Quitar `tailwindcss`, `@tailwindcss/vite` y `concurrently` obliga a regenerar `package-lock.json` (dependencia de red). |
| F-23 | Hecho (parcial) | Directorios huérfanos de pruebas limpiados; las cifras de siembra en pruebas se dejan como están. |
| F-24 | Parcial | Archivos residuales borrados; `.env` local no se tocó. |
| F-25 | Sin cambio | Se conservan los bytes exactos (`* -text`); unificar finales de línea sería un commit masivo aparte. |
| F-26 | Hecho | 122 cachés vacías de PHPUnit y `__pycache__` eliminados. |
| F-27 | Diferido | Cosmético (favicon vacío). |

## Anexo C — Resolución Vicerrectoral 021/2023

El sistema no puede cumplir sin decisiones institucionales casi nada de lo que marca el anexo. Lo corregido:

- **Matriz normativa:** D-21, D-22 y L-20 a L-23 figuraban "Implementado parcialmente" sin referencia. Ahora citan `InstitutionalRegister.php::record` y dicen que el sistema solo registra el acto, sin controlar el plazo ni el período (JSON y MD).
- **Documentación contradictoria:** el contraste normativo 0.3 ya no dice a la vez que los originales están recuperados y que no están disponibles.

No se implementó (ver decisiones pendientes): nota mínima final, plazos de 48 h/24 h/15 días, campos de carga horaria/período/lugar, convocatoria desierta/vacante con límite de dos, aprobación del HCF, incompatibilidades, composición de comisión y reparto 60/40 forzado. En ninguno se inventó un valor.

## Anexo D — enunciado, convocatorias y RF/CU

- **Enunciado:** el PDF entregado describe el Proyecto #1 (sistema por correo con comandos `LISPER`/`INSPER`). Este repositorio es el P2. No se construyó un sistema de correo sin que se confirme cuál es la entrega vigente.
- **Documentos corregidos:** `REVISION_TRES_PDF` ya no afirma "0/49 ofertas"; `CIERRE_INF513_ACTUAL` concilia los plazos 15:00 del 20/11 y 20:00; `CIERRE_INF513_ACTUAL` e `INSTALACION_DESDE_ENTREGA` aclaran que `installation.json` es del paquete del 23/11; `02-TRAZABILIDAD.md` y `ESTADO_PROYECTO.md` tienen el estado vigente; el README no repite "123 acciones" ni "no publicado".
- **Sin cambio:** las diferencias entre `DemoSeeder` e `InstitutionalReferenceSeeder` (fecha de examen de la convocatoria 01, requisitos genéricos de sala y laboratorio históricos) y la palabra "original" en dos requisitos del catálogo. Son datos sembrados y de catálogo con hash; cambiarlos requiere una decisión.
- **RF/CU:** la lectura estricta del anexo deja RF-01 (sin rol director de carrera), RF-15 y RF-29 como parciales; las matrices CSV no se modificaron.

## Paquete de entrega

El tar `2026-2_INF513-P2_grupo09sa.tar.gz` ya no representa el árbol (42 archivos distintos, 37 ausentes). No se regeneró: hacerlo es una entrega, y el plazo del enunciado ya venció. `MANIFIESTO_SHA256.json` se regenera con el estado actual del repositorio.
