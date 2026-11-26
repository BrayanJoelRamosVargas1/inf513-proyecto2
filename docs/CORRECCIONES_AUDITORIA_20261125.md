# Correcciones de la auditoría general del 25/11/2026

Informe de origen: `documentos_tecno/AUDITORIA_GENERAL_20261125.md` y anexos A–F. Todo el trabajo está en la rama local `correcciones-auditoria`; el respaldo previo está en `respaldo-pre-correcciones`. No se publicó nada en GitHub ni en Tecnoweb, y no se tocó la base `ficct_demo` ni la instancia 8116.

**Verificación final:** PHPUnit 401 pruebas / 2297 aserciones sin fallos (solo sobre `ficct_test`); Vitest 100 pruebas en 20 archivos; `npm run build` correcto.

Leyenda: **Hecho** = corregido y probado · **Parcial** = corregido en parte · **Sin cambio** = decisión consciente · **Diferido** = requiere decisión o trabajo aparte (ver `DECISIONES_ADOPTADAS_20261126.md`).

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
| B-17 | Diferido | Doble trigger de inmutabilidad en `documents`: no es un defecto funcional y retirar uno exige una migración que toca la protección de inmutabilidad; no se arriesgó. |
| B-18 | Sin cambio | `config/ficct.php` y `DocumentSafety::scan` los usan las pruebas. |
| B-19 | Hecho | Variables de sesión, proxies y zona horaria añadidas a `.env.example` y `.env.production.example`. |
| B-20 | Hecho | `ext-pdo_pgsql` declarado en `composer.json` y en la sección `platform` de `composer.lock`; el `content-hash` se recalculó con el algoritmo de Composer (comprobado antes contra el hash vigente) y `composer validate` no reporta el lock desactualizado. |
| B-21 | Hecho | Migración `2026_11_25_000003_add_domain_value_checks` (CHECK `NOT VALID`; `calls.mode` admite OFICIAL). |
| B-22 | Diferido | Unificar idiomas de rutas rompe contratos públicos. |
| B-23 | Hecho | `InstitutionalRegister::csvCell` neutraliza `= + - @`, tabulador y retorno. |
| B-24 | Diferido | Cambiar el mensaje de correo ya registrado es una decisión de producto. |
| B-25 | Hecho | Bloqueo por cuenta y dirección en el login: 5 fallos en 300 s, se limpia al entrar bien. |
| B-26 | Hecho | Limitadores `mutations` y `uploads`, y límite al token CSRF. |
| B-27 | Hecho | `Participation::review` conserva `created_at`. |
| B-28 | Hecho (criterio provisional) | El límite de asignaturas cuenta solo postulaciones con participación activa: una retirada o renunciada libera cupo. La resolución no lo define; si la institución decide otra cosa se cambia en tres lugares (`ApplicationIntake`, `SelectionController`, `CallView`). |
| B-29 | Hecho | `Scores` acepta áreas con clave numérica. |
| B-30 | Hecho | Constantes de puntaje centralizadas en `Scores`. |
| B-31 | Sin cambio | El propio anexo lo marca como posiblemente deseado en la DEMO: `DemoJourney` y las pruebas abren y resuelven desempates de inmediato. Exigir fecha futura necesita un hito de referencia definido. |
| B-32 | Hecho | `DemoSeeder` atómico; el archivo de accesos se escribe tras confirmar. |
| B-33 | Sin cambio | Memoizar `Access::has` por petición arriesga permisos obsoletos dentro de una misma petición y entre pruebas; el costo medido no lo justifica. |
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
| F-15 | Hecho | Pruebas nuevas de `useData`, `uuid`, títulos, `Awards`, `Corrections`, `CreateCall`, `CpdRecord` y `Preferences` (`process-screens.test.jsx`, `navigation-focus.test.jsx`). `ResultHistory` ya se ejercita en `ranking-history.test.jsx` y la pantalla `Call` en `call-readonly.test.jsx`. No se añadió axe a `npm test`. |
| F-16 | Hecho | `ScoresTest` y pruebas de los reportes de la API. |
| F-17 | Hecho (documentado) | `scripts/README.md` lista los requisitos externos. Las guardas de aislamiento faltantes en `academic_business.cjs` no se tocaron. |
| F-18 | Hecho | README reescrito con cifras reales; el anterior está en `ARCHIVO_SISTEMA_FICCT/docs_historicos/HISTORICO_README.md`. |
| F-19 | Hecho | `/entrenamiento` registra su visita. |
| F-20 | Hecho | `uuid` sin contexto seguro, alerta accesible en lugar de `window.alert`, error de conexión visible. |
| F-21 | Hecho | Título por ruta, foco al contenido al navegar (no en la carga inicial) y `scope="col"` en cabeceras React y Blade. No se añadieron `caption` a las tablas Blade. |
| F-22 | Hecho | Quitados `tailwindcss`, `@tailwindcss/vite` y `concurrently` (38 paquetes) con `npm uninstall --offline`, y el script `composer dev` que dependía de `concurrently`. Build y pruebas siguen en verde. |
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

## Limpieza y refactor del 26/11/2026

- **Archivo fuera del proyecto:** `ARCHIVO_SISTEMA_FICCT/` (carpeta hermana de `sistema`) guarda `documentos_tecno`, 49 documentos históricos, la carpeta `evidence` (1643 archivos), 50 guiones de entrega y el manifiesto anterior. En `docs/` quedan 12 documentos vigentes y en `scripts/` los guiones de desarrollo, cuyas salidas van a `.local/salidas/`.
- **Frontend:** `main.jsx` (165 líneas con filas de hasta 7000 caracteres) se dividió en `auth`, `shell`, `call`, `application` y `sandbox`; `processes.jsx` en `form-kit`, `call-forms`, `ranking`, `corrections` y `participation`. Todo el código y las pruebas quedaron con formato multilínea. El bundle compilado pesa lo mismo, y las 100 pruebas pasan.
- **PHP:** los arrays largos se repartieron en líneas y se aplicó Pint (finales de línea LF, imports sin uso). 401 pruebas pasan.
- **Funciones nuevas, todas opcionales y vacías por defecto (no se inventó ningún valor institucional):** período académico, lugar de recepción y lugar del examen en la convocatoria (Res. 021/2023, Art. 8); horas teóricas y prácticas y **nota mínima final de aprobación** en la oferta (Arts. 19-20). Con la nota mínima vacía no se excluye a nadie; con ella, quien no la alcanza queda fuera del ranking, de las plazas y de la selección.
