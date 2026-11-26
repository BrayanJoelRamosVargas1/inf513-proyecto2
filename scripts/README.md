# Guiones de `scripts/`

Herramientas de desarrollo y operación. Todos escriben sus salidas en `.local/salidas/` (carpeta ignorada por Git), salvo que se indique otra con una variable.

| Guion | Para qué sirve | Requisitos |
|---|---|---|
| `test.py` | Corre PHPUnit en `ficct_test` con su propia caché y la limpia al terminar (`FICCT_TEST_OUTPUT_DIR` cambia la salida). | PHP y PostgreSQL local |
| `concurrency.py`, `governance_concurrency.py`, `participation_concurrency.py` (+ `*_race.php`, `concurrency_*.php`) | Carreras con dos procesos y conexiones independientes contra una base de prueba. Usan un `LARAVEL_STORAGE_PATH` temporal. | PostgreSQL local |
| `verify_restore.py` | Restaura un respaldo en una base nueva y compara tablas y documentos. Nunca sobrescribe la demostración. | Binarios de PostgreSQL |
| `local_database.py` | Crea/arranca el clúster aislado en `127.0.0.1:55439` y deja el `.env` listo. No reescribe un `.env` que apunta a otra base salvo `FICCT_FORCE_ENV=1`. | Binarios de PostgreSQL 15 (`FICCT_PG_BIN`) |
| `governance_http.py`, `participation_http.py`, `security_http.py` | Recorridos HTTP contra un servidor en marcha (`FICCT_BASE_URL`, por defecto `http://127.0.0.1:8099`). Dependen de datos sembrados concretos. | `pypdf`, `openpyxl` |
| `run_document_processor.py`, `windows/`, `linux/` | Procesador de análisis de documentos como proceso supervisado o servicio. | Ver `docs/PROCESADOR_DOCUMENTAL_SUPERVISADO.md` |
| `verify_clamav_adapter.py` | Comprueba el adaptador local de ClamAV sin tocar la base de datos. | ClamAV |
| `write_frontend_build_manifest.mjs` | Lo ejecuta `npm run build` para registrar los recursos compilados. | Node |

Los guiones de verificación de navegador, de empaquetado de entregas y de aceptación que existieron antes de la limpieza del 26/11/2026 se conservan fuera del proyecto, en `ARCHIVO_SISTEMA_FICCT/scripts_de_entrega/` (carpeta hermana de `sistema`).
