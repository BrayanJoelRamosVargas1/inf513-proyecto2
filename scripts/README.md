# Guiones de `scripts/`

Resumen de qué hace cada grupo y qué necesita fuera del repositorio. Los guiones de navegador y de verificación de entrega nacieron para el equipo original y **no son reproducibles solo con `composer install` y `npm ci`** (auditoría 25/11/2026, F-17). Esta tabla deja explícitos los requisitos.

| Grupo | Archivos | Requisitos |
|---|---|---|
| Pruebas del servidor | `test.py` | PHP, PostgreSQL local con la base `ficct_test`. Variable opcional `FICCT_TEST_OUTPUT_DIR`. |
| Concurrencia | `concurrency.py`, `governance_concurrency.py`, `participation_concurrency.py` (+ `*.php`) | PostgreSQL local; usan un `LARAVEL_STORAGE_PATH` temporal que se borra al terminar. |
| Recorridos HTTP | `governance_http.py`, `participation_http.py`, `security_http.py` | Servidor en marcha; `FICCT_BASE_URL` (por defecto `http://127.0.0.1:8099`). Dependen de datos sembrados concretos (ids de oferta/aplicación) y de `pypdf`/`openpyxl`. |
| Base local | `local_database.py` | Binarios de PostgreSQL 15 (`FICCT_PG_BIN`). No reescribe un `.env` que apunte a otra base o puerto salvo `FICCT_FORCE_ENV=1`. |
| Restauración | `verify_restore.py`, `preservation.py` | PostgreSQL local. |
| Navegador (todos los `*.cjs`, `verify_academic_business.py`, `verify_role_workspaces.py`, `verify_accessibility_release.py`) | | **Playwright** (`FICCT_PLAYWRIGHT_MODULE`), **Chromium** (`FICCT_CHROMIUM`), `axe-core` y, según el guion, el laboratorio ClamAV o `.local/operacion-03/state.json`. Ninguno está declarado en `package.json`. Los guiones usan bases y puertos aislados (8119–8121, `ficct_applicants_*`); no apuntarlos a `ficct_demo`. |
| Empaquetado y entrega | `package.py`, `package_academic_release.py`, `verify_clean_install.py`, `test_clean_install.py`, `test_release_package.py`, `verify_continuity_release.py`, `verify_operation_release.py`, `verify_functional_release.py`, `verify_security_release.py` | Entorno del equipo original (`.local/…`, carpeta de auditoría externa). `package_academic_release.py` fija el nombre `INF513-P2_grupo<NN>sa` y no es reproducible fuera de ese equipo. |
| Servicio documental de Windows | `windows/` | Windows con permisos de administrador para instalar el servicio. |
| Contratos | `verify_documentary_contract.py`, `verify_traceability.py` | Solo lectura sobre `docs/` y `evidence/`. |

Cada ejecución de los guiones de recorrido crea bases (`ficct_walkthrough_*`, `ficct_restore_*`, `ficct_applicants_*`) y carpetas en `.local`. Si ya no se necesitan, eliminarlas a mano con `dropdb`.

## Qué no es esto

Estos guiones no sustituyen `php artisan test` ni `npm test`. Un resultado de navegador o de empaquetado solo vale para el árbol de archivos con el que se ejecutó; tras cambiar el código hay que repetirlo y regenerar la evidencia.
