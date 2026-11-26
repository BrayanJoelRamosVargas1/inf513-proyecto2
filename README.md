# Sistema de Selección de Auxiliares FICCT / UAGRM — INF513 P2 · Grupo #09

Aplicación web para gestionar convocatorias de auxiliares de docencia y de laboratorio: postulación, revisión documental, habilitación, méritos, examen, ranking, desempates, publicación de resultados, participación (subsanaciones, renuncias) y auditoría.

- **Servidor:** Laravel 12 (PHP 8.3) con PostgreSQL 15. Reglas del proceso en `app/Domain`.
- **Interfaz:** React 19 en `/p2`, que consume la API `/api/v1` con la sesión web y CSRF. En la instalación real es la única interfaz; las vistas Blade (`/`) quedan para el modo entrenamiento.
- **Dos modos de instalación**, según `FICCT_EXPERIENCE`: `institutional` (instalación real, convocatorias OFICIAL, sin datos de ejemplo) y `training` (entrenamiento con datos sintéticos DEMO, en otra base y otro servidor). Para desplegar la real, ver [`docs/DESPLIEGUE.md`](docs/DESPLIEGUE.md).
- **Reglas:** las decisiones adoptadas para la operación (nota mínima, plazos, roles) y su fundamento en la resolución y las convocatorias están en [`docs/DECISIONES_ADOPTADAS_20261126.md`](docs/DECISIONES_ADOPTADAS_20261126.md).

## Estado verificado (26/11/2026)

| Control | Resultado |
|---|---|
| PHPUnit sobre `ficct_test` | 414 pruebas / 2352 aserciones, sin fallos |
| Vitest (React) | 103 pruebas en 21 archivos, sin fallos |
| Compilación del frontend | `npm run build` correcta |

Estas cifras corresponden a la rama `correcciones-auditoria`. La documentación histórica, la evidencia de cierres anteriores y los guiones de entrega se movieron el 26/11/2026 a `ARCHIVO_SISTEMA_FICCT/` (carpeta hermana de `sistema`, fuera del repositorio); sigue disponible en el historial de Git.

## Requisitos

PHP 8.3 con BCMath, PDO PostgreSQL, mbstring, gd, zip, dom, fileinfo, intl y openssl; Composer 2; PostgreSQL 15 (binarios); Python 3; Node 20 o superior y npm.

## Instalación local de entrenamiento

Es el modo `training` (cuentas y datos ficticios). Para una instalación real siga [`docs/DESPLIEGUE.md`](docs/DESPLIEGUE.md).

```powershell
composer install --no-interaction
npm ci
npm run build                 # public/build no está versionado: sin este paso /p2 no carga
Copy-Item .env.example .env   # solo en una carpeta nueva sin .env
python scripts/local_database.py
php artisan key:generate      # solo en la primera instalación; no rotar una clave existente
php artisan migrate --force
php artisan db:seed --force
php artisan serve --host=127.0.0.1 --port=8099 --no-reload
```

`INICIAR_DEMO.ps1` hace lo anterior en Windows con un clúster PostgreSQL propio en `127.0.0.1:55439`, sin tocar el servicio PostgreSQL existente. Las contraseñas de las cuentas ficticias se generan al azar en `.local/ACCESOS_DEMO.txt` (archivo privado, fuera de Git y del paquete).

La URL por defecto es `http://127.0.0.1:8099` (`APP_URL` de `.env.example`). El puerto `8116` que aparece en documentos antiguos es una instalación operativa separada de este equipo (otra base y otro clúster); no es la que crea este procedimiento.

`php artisan ficct:demo-recorrido` (opcional) deja una convocatoria evaluada con empate, nuevo examen, acta y publicación, y otra abierta para recorrer la postulación.

### Tareas programadas

Los trabajos de análisis de documentos y renovación de vigencia se programan en `routes/console.php`. Hace falta ejecutar `php artisan schedule:run` cada minuto (cron en Linux, Programador de tareas en Windows) o `php artisan schedule:work` en desarrollo.

- `ficct:scan-renew` renueva el análisis de documentos próximos a vencer (24 h de validez); por defecto solo informa, `--apply` escribe.
- `ficct:scan-worker` procesa la cola de análisis; `ficct:scan-maintain` hace el mantenimiento.

### Producción

Guía completa en [`docs/DESPLIEGUE.md`](docs/DESPLIEGUE.md); `php artisan ficct:verificar-instalacion` comprueba que la instalación esté lista. Usar `.env.production.example` como guía: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS (activa HSTS), `TRUSTED_PROXIES` si hay proxy inverso, `SESSION_SECURE_COOKIE`, ClamAV real (`FICCT_CLAMSCAN_BINARY`) y zona horaria de base `DB_TIMEZONE=America/La_Paz`. El servidor de desarrollo de PHP y el usuario del clúster local no son configuración de producción.

## Pruebas

```powershell
php artisan test                 # PHPUnit: phpunit.xml fija la base ficct_test
python scripts/test.py           # lo mismo con su propia caché y limpieza
npm test                         # Vitest (React)
python scripts/concurrency.py    # carreras con dos procesos y conexiones independientes
python scripts/governance_concurrency.py
python scripts/participation_concurrency.py
python scripts/verify_restore.py ficct_test
php vendor/bin/pint --test
composer validate --no-check-publish
composer audit
```

`tests/TestCase.php` aborta si la base no es `ficct_test` o si el servidor no es local, para proteger `ficct_demo`. Nunca se ejecuta `migrate:fresh` sobre la demostración. Los scripts de `scripts/` y sus requisitos externos están descritos en [`scripts/README.md`](scripts/README.md).

## Estructura

| Ruta | Contenido |
|---|---|
| `app/Domain` | Reglas del proceso. `Selection` es una fachada que delega en `CallLifecycle`, `ApplicationIntake`, `EligibilityReview`, `Evaluation` y `ResultsPublication`; además `Scores`, `Access`, `Participation` (fachada de `Amendments`, `ParticipationRequests` y `AwardChoices`), `Rectifications`, `ResultRevisions`, `DocumentSafety`, `ScanRenewal` |
| `app/Http/Operations` | Casos de uso compartidos por Blade y API (una clase por área) |
| `app/ReadModels` | Lecturas que arman las pantallas y la API |
| `app/Console/Commands` | Comandos `ficct:*` |
| `resources/js/portal` | SPA React (pruebas `*.test.jsx` junto al código) |
| `resources/institutional` | Fuentes institucionales (catálogo, asignaturas, tres PDF) con hash verificado |
| `database/migrations` | Esquema, restricciones, disparadores de inmutabilidad e índices |
| `docs` | Documentación vigente; empezar por `docs/ARQUITECTURA.md` |

## Qué cubre

Registro y sesión (con cambio de clave); permisos por convocatoria; tipos de auxiliatura configurables; borrador, publicación, cierre, evaluación, resultados y archivo con reglas de transición; ofertas, plazas y pesos; postulación múltiple con límite por convocatoria; documentos privados con versiones, análisis antivirus y validez de 24 h; checklist de requisitos; habilitación; méritos del CPD (registro manual, máximo 40); examen ponderado (máximo 60) con dos decimales y un único redondeo HALF_UP; ranking con desempate por nuevo examen y acta; aprobación y publicación versionada; rectificaciones con revisión de resultados; subsanaciones, retiros y renuncias con vacantes retenidas; auditoría inmutable; reportes PDF y XLSX; registro institucional documental.

## Alcance y límites

- El sistema cubre la **selección**: convocatoria, postulación, revisión, habilitación, méritos, examen, ranking, resultados y auditoría. El nombramiento, los certificados, el CPD, el envío de correos y el cruce de horarios quedan fuera y los hace la autoridad con los reportes y el acta que el sistema produce.
- La **nota mínima de aprobación** se propone en 51 puntos (`FICCT_NOTA_MINIMA`, ajustable por oferta); los plazos de 48 h, 24 h y 15 días se registran y avisan, no bloquean. El fundamento de cada decisión está en [`docs/DECISIONES_ADOPTADAS_20261126.md`](docs/DECISIONES_ADOPTADAS_20261126.md).
- Estado de cada hallazgo de la auditoría: [`docs/CORRECCIONES_AUDITORIA_20261125.md`](docs/CORRECCIONES_AUDITORIA_20261125.md).

## Documentación

[`ARQUITECTURA.md`](docs/ARQUITECTURA.md) · [`DESPLIEGUE.md`](docs/DESPLIEGUE.md) · [`MANUAL_DE_USO.md`](docs/MANUAL_DE_USO.md) · [`DECISIONES_ADOPTADAS_20261126.md`](docs/DECISIONES_ADOPTADAS_20261126.md) · [`CU_Y_RF_CANONICOS.md`](docs/CU_Y_RF_CANONICOS.md) · [`02-TRAZABILIDAD.md`](docs/02-TRAZABILIDAD.md) · [`MATRIZ_NORMATIVA_ACTUAL.md`](docs/MATRIZ_NORMATIVA_ACTUAL.md) · [`SOLICITUDES_INSTITUCIONALES.md`](docs/SOLICITUDES_INSTITUCIONALES.md) · [`RUNBOOK_BD_INCIDENTES.md`](docs/RUNBOOK_BD_INCIDENTES.md) · [`INF513_P2_OPENAPI.json`](docs/INF513_P2_OPENAPI.json)
