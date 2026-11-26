# Sistema de Selección de Auxiliares FICCT / UAGRM — INF513 P2 · Grupo #09

Aplicación web para gestionar convocatorias de auxiliares de docencia y de laboratorio: postulación, revisión documental, habilitación, méritos, examen, ranking, desempates, publicación de resultados, participación (subsanaciones, renuncias) y auditoría.

- **Servidor:** Laravel 12 (PHP 8.3) con PostgreSQL 15. Reglas del proceso en `app/Domain`.
- **Interfaz principal:** React 19 en `/p2`, que consume la API `/api/v1` con la sesión web y CSRF. Se conserva en paralelo la interfaz Blade (`/`), con los mismos servicios de dominio.
- **Estado:** demostración técnica. **NO_GO_INSTITUCIONAL**: no está autorizada para concursos reales; varias reglas dependen de decisiones institucionales (ver más abajo).

## Estado verificado (25/11/2026, tras las correcciones de la auditoría)

| Control | Resultado |
|---|---|
| PHPUnit sobre `ficct_test` | 397 pruebas / 2281 aserciones, sin fallos |
| Vitest (React) | 80 pruebas en 18 archivos, sin fallos |
| Compilación del frontend | `npm run build` correcta |

Estas cifras corresponden a la rama `correcciones-auditoria`. Los recuentos de antecedentes que aparecen en otros documentos están fechados; el historial anterior de este README está en [`docs/HISTORICO_README.md`](docs/HISTORICO_README.md).

## Requisitos

PHP 8.3 con BCMath, PDO PostgreSQL, mbstring, gd, zip, dom, fileinfo, intl y openssl; Composer 2; PostgreSQL 15 (binarios); Python 3; Node 20 o superior y npm.

## Instalación local

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

Usar `.env.production.example` como guía: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS (activa HSTS), `TRUSTED_PROXIES` si hay proxy inverso, `SESSION_SECURE_COOKIE`, ClamAV real (`FICCT_CLAMSCAN_BINARY`) y zona horaria de base `DB_TIMEZONE=America/La_Paz`. El servidor de desarrollo de PHP y el usuario del clúster local no son configuración de producción.

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
| `app/Domain` | Reglas del proceso. `Selection` es una fachada que delega en `CallLifecycle`, `ApplicationIntake`, `EligibilityReview`, `Evaluation` y `ResultsPublication`; además `Scores`, `Access`, `Participation`, `Rectifications`, `ResultRevisions`, `DocumentSafety`, `ScanRenewal` |
| `app/Http/Operations` | Casos de uso compartidos por Blade y API (una clase por área) |
| `app/ReadModels` | Lecturas que arman las pantallas y la API |
| `app/Console/Commands` | Comandos `ficct:*` |
| `resources/js/portal` | SPA React (pruebas `*.test.jsx` junto al código) |
| `resources/institutional` | Fuentes institucionales (catálogo, asignaturas, tres PDF) con hash verificado |
| `database/migrations` | Esquema, restricciones, disparadores de inmutabilidad e índices |
| `docs` | Documentación; empezar por `docs/ARQUITECTURA.md` y `docs/ESTADO_PROYECTO.md` |

## Qué cubre

Registro y sesión; permisos por convocatoria; tipos de auxiliatura configurables; borrador, publicación, cierre, evaluación, resultados y archivo con reglas de transición; ofertas, plazas y pesos; postulación múltiple con límite por convocatoria; documentos privados con versiones, análisis antivirus y validez de 24 h; checklist de requisitos; habilitación; méritos del CPD (registro manual, máximo 40); examen ponderado (máximo 60) con dos decimales y un único redondeo HALF_UP; ranking con desempate por nuevo examen y acta; aprobación y publicación versionada; rectificaciones con revisión de resultados; subsanaciones, retiros y renuncias con vacantes retenidas; auditoría inmutable; reportes PDF y XLSX; registro institucional documental.

## Límites y decisiones pendientes

- No hay nota mínima final: la Resolución 021/2023 habla de "puntaje de aprobación" sin dar cifra; el sistema no inventa un umbral.
- No se controlan los plazos de 48 h, 24 h y 15 días de la resolución ni se modela el nombramiento, la carga horaria, el período académico ni el lugar de la convocatoria.
- No existe el procedimiento de convocatoria desierta/vacante con límite de dos convocatorias consecutivas.
- El enunciado entregado como Proyecto #1 (sistema por correo electrónico) es otro sistema; este repositorio corresponde al P2.
- Detalle y estado de cada hallazgo de la auditoría: [`docs/CORRECCIONES_AUDITORIA_20261125.md`](docs/CORRECCIONES_AUDITORIA_20261125.md). Decisiones que requieren respuesta institucional o de la cátedra: [`docs/DECISIONES_PENDIENTES_20261125.md`](docs/DECISIONES_PENDIENTES_20261125.md).

## Documentación

[`ARQUITECTURA.md`](docs/ARQUITECTURA.md) · [`ESTADO_PROYECTO.md`](docs/ESTADO_PROYECTO.md) · [`MANUAL_Y_DEMO.md`](docs/MANUAL_Y_DEMO.md) · [`CU_Y_RF_CANONICOS.md`](docs/CU_Y_RF_CANONICOS.md) · [`02-TRAZABILIDAD.md`](docs/02-TRAZABILIDAD.md) · [`MATRIZ_NORMATIVA_ACTUAL.md`](docs/MATRIZ_NORMATIVA_ACTUAL.md) · [`INSTALACION_DESDE_ENTREGA.md`](docs/INSTALACION_DESDE_ENTREGA.md) · [`CIERRE_INF513_ACTUAL.md`](docs/CIERRE_INF513_ACTUAL.md)
