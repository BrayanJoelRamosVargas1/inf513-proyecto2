# Sistema de Selección de Auxiliares FICCT / UAGRM · Grupo #09

DEMO 0.3 — Expedientes y participación. Continúa DEMO 0.2 conservando sus datos e integridad; agrega subsanaciones versionadas, retiros y renuncias con decisión separada, vacantes retenidas y manifestaciones sobre múltiples adjudicaciones. Los procedimientos nuevos son técnicos DEMO, sujetos a validación institucional. Conserva los 8 CU y los 30 RF en `docs/CU_Y_RF_CANONICOS.md`. Incluye código de aplicación, base de datos real, pantallas, pruebas y evidencia. No es una implantación institucional ni un cierre total del contrato.

## Abrir la aplicación en este equipo

El servidor de desarrollo está en **http://127.0.0.1:8099**. Si está detenido, ejecutar desde esta carpeta:

```powershell
.\INICIAR_DEMO.ps1
```

El script usa PHP/Composer/Python en PATH y PostgreSQL 15 en `C:/Program Files/PostgreSQL/15/bin`. Si está en otra ubicación, definir `$env:FICCT_PG_BIN` con su carpeta `bin`. Inicia un cluster aislado en `127.0.0.1:55439`, sin modificar el servicio PostgreSQL existente. Contraseñas aleatorias de cuentas ficticias en **`.local/ACCESOS_DEMO.txt`**, archivo privado excluido del ZIP y de Git. No se necesitan cuentas reales, Docker, Node ni conexión CPD para utilizar la DEMO.

El script no renueva la clave si ya existe ni sobrescribe el seed cuando hay convocatorias. No ejecuta borrados de datos. Si una política de PowerShell impide abrirlo, usar los comandos manuales siguientes; no es necesario desactivar políticas.

## Instalación desde el ZIP de código (Windows)

Requisitos: PHP 8.3 con BCMath, PDO PostgreSQL, mbstring, gd, zip, dom, fileinfo, intl y openssl; Composer 2; PostgreSQL 15 (binarios); Python 3. El frontend se sirve sin compilación ni CDN.

```powershell
composer install --no-interaction
Copy-Item .env.example .env   # Solo en una carpeta nueva sin .env
python scripts/local_database.py
php artisan key:generate    # Solo primera instalación; no rotar una clave existente
php artisan migrate --force
php artisan db:seed --force
php artisan ficct:demo-recorrido
php artisan serve --host=127.0.0.1 --port=8099 --no-reload
```

`ficct:demo-recorrido` es opcional: deja una convocatoria evaluada con empate, nuevo examen, acta y snapshot; mantiene otra abierta para recorrer la postulación. Ver `docs/MANUAL_Y_DEMO.md`.

En Linux o con PostgreSQL administrado: crear bases nuevas `ficct_demo` y `ficct_test` con un usuario exclusivo, configurar DB_* en `.env`, generar clave, migrar y sembrar. `scripts/local_database.py` y `verify_restore.py` están preparados para los binarios Windows de este equipo; no se afirma haber probado instalación Linux.

## Pruebas verificables

```powershell
python scripts/test.py
python scripts/concurrency.py
python scripts/governance_concurrency.py
python scripts/participation_concurrency.py
python scripts/verify_restore.py ficct_test
python scripts/verify_restore.py
php vendor/bin/pint --test
composer validate --no-check-publish
composer audit
```

El test runner exige `ficct_test` para proteger la DEMO. PHPUnit cubre T-01..T-30 (uno por RF) y negativos adicionales. Es cobertura inicial, no garantía de completitud de cada subflujo. La concurrencia usa dos procesos y conexiones PostgreSQL independientes. El respaldo se restaura en una base nueva y copia documentos a otra carpeta con checksum; nunca sobrescribe la DEMO. Los respaldos y credenciales permanecen en `.local`, fuera del paquete.

Resultados y salidas en `evidence/`; capturas reales en `evidence/screenshots/`; PDF y XLSX descargados desde la aplicación también se conservan como evidencia ficticia. `docs/ESTADO_PROYECTO.md` indica lo completado, parcial y pendiente.

## Qué funciona

Acceso con sesión real; alta de usuarios/permisos por convocatoria; tipos configurables; borrador/publicación/cierre/evaluación; ofertas/cupos y pesos por convocatoria; postulación múltiple; archivos privados versionados; envío con documentos obligatorios; checklist de requisitos; habilitación; registro manual de CPD/méritos; docentes asignados y notas confirmadas; cálculo decimal por postulación; ranking; nuevo examen exclusivo de desempate; decisión y aprobación DEMO; snapshot; auditoría; PDF/XLSX. Históricos 01/02 mantienen diferencias de ofertas y el conflicto cronológico de la fuente.

## Alcance y límites de DEMO 0.3

Los borradores conservan versiones y se congelan al publicar. La administración permite editar perfiles, activar/desactivar, conceder/renovar permisos por convocatoria con inicio y vencimiento, revocarlos y consultar su historia. Cambiar permisos invalida sesiones. Las responsabilidades se reasignan con motivo sin atribuir las notas anteriores al nuevo evaluador.

Las rectificaciones de méritos/exámenes exigen motivo, referencia de evidencia y decisión de otra autoridad. Conservan versiones, recalculan con decimales exactos, generan una revisión del ranking y requieren aprobar una nueva publicación. Los snapshots previos, revisiones, versiones de notas y auditoría tienen protección de inmutabilidad en PostgreSQL. Las rondas sucesivas de desempate conservan cada examen, sin fórmula ni ganador automáticos.

Ver `docs/DEMO_0_3_CAMBIOS_Y_VALIDACION.md` y `docs/03-CONTRASTE_NORMATIVO_DEMO_0_3.md`. Las subsanaciones, retiros/renuncias y manifestaciones sobre varias plazas ya funcionan como DEMO. No se presumen incompatibilidades ni se cubren vacantes automáticamente. Los plazos de subsanación se configuran expresamente por ventana. Las confirmaciones institucionales, firmas y procedimientos de reemplazo siguen pendientes. Los originales PDF/fotografías no están disponibles en las rutas consultadas: el contraste directo continúa NOT_VERIFIED.

OFICIAL e históricos están bloqueados para operaciones. El nuevo examen existe como subflujo, pero condiciones, resultados y acta son registro explícito de la comisión DEMO, sin firma institucional. No hay conexión CPD. No se automatiza una fórmula de áreas × escrito/práctico. El servidor de PHP y el usuario del cluster local no son una configuración de producción. Se requiere validación institucional, operación, política de datos, firmas y endurecimiento antes de otro uso.

Documentación: `docs/01-AUDITORIA-FUENTES.md`, `docs/02-TRAZABILIDAD.md`, `docs/ARQUITECTURA.md`, `docs/MANUAL_Y_DEMO.md`, `docs/ESTADO_PROYECTO.md`. Las fuentes originales no se duplican en el ZIP de código: se mantienen en el paquete documental v2 que aportó el usuario.
