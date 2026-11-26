# Historial de avisos del README (archivo conservado)

Contenido íntegro del README anterior al 25/11/2026, movido aquí para que el README sea una guía corta y exacta (auditoría 25/11/2026, F-18). Las cifras de pruebas y las rutas que aparecen abajo son **antecedentes fechados**, no el estado actual: ver `README.md` y `docs/CORRECCIONES_AUDITORIA_20261125.md`.

---

# Sistema FICCT/UAGRM — INF513 P2, versión local actual

Aplicación local: http://127.0.0.1:8116/p2 . Una sola base de código conserva React, API Laravel, PostgreSQL, el rediseño y DOC-21. No se ha publicado esta actualización en GitHub/Tecnoweb ni entregado en plataforma.

**Cierre vigente del 24/11:** 306 pruebas PHP / 1973 aserciones y 62 React aprobadas; 123 acciones conservadas (95 verificadas localmente y 28 equivalencias justificadas). [Estado y límites actuales](docs/ESTADO_PROYECTO.md). Las cifras anteriores que siguen son antecedentes identificados; la aceptación del candidato corresponde únicamente al hash de su informe de instalación externo. El paquete incluye la instantánea de matrices en `docs/cierre/`.

[Cierre académico, sincronización reproducible, requisitos y publicación con reversión](docs/CIERRE_INF513_ACTUAL.md) · [Rediseño y resultados visuales](docs/REDISENO_FRONTEND_ACTUAL.md).

23/11/2026: instalación independiente del paquete anterior de 373 archivos aprobada, con otro clúster y dependencias instaladas desde los lockfiles: 276 pruebas PHP/1712 aserciones, 37 React, restauración de 50 tablas y cinco perfiles HTTP. La entrega actual incorpora el conductor corregido y conserva esa evidencia como antecedente. [Procedimiento de instalación](docs/INSTALACION_DESDE_ENTREGA.md) y `evidence/clean-install-current` identifican el ensayo anterior; `evidence/release-acceptance/installation.json`, externo al tarball, identifica y acepta el hash definitivo cuando su resultado es PASS.

Antecedente del 24/11/2026, anterior al cierre: 298 pruebas PHP / 1911 aserciones aprobadas en PostgreSQL aislado (`ficct_test`). En ese corte la auditoría integral de archivos seguía abierta; sus resultados y pendientes están en `../AUDITORIA_INTEGRAL_INF513_20261123/PROGRESO.json`. Ese registro React contiene 57 pruebas aprobadas. Las herramientas de sincronización/empaquetado tienen doce regresiones separadas. Consultar `evidence/academic-close` para los resultados del recorrido de operaciones, preservación y paquete. No sumar cifras históricas ni confundir una entrega preparada con publicación o aceptación institucional. `NO_GO_INSTITUCIONAL` continúa vigente.

## Antecedentes técnicos conservados

### WIN-01 — configuración instalada verificada

WIN-01 reproducido y corregido: once regresiones PowerShell aprobadas. Las acciones administrativas usan la configuración instalada y rechazan copias externas diferentes. Instalación SCM, LocalService y reinicio real siguen pendientes. Detalles: [WIN01_CONFIGURACION_INSTALADA.md](docs/WIN01_CONFIGURACION_INSTALADA.md). NO_GO_INSTITUCIONAL.

# Operación documental de Windows — instalación pendiente

Adaptador de servicio y diagnóstico implementados; ciclo de vida probado con procesos reales en consola. SCM y arranque tras reinicio siguen sin verificar porque esta sesión no es administrativa. Instrucciones: [OPERACION_SERVICIO_DOCUMENTAL_WINDOWS.md](docs/OPERACION_SERVICIO_DOCUMENTAL_WINDOWS.md). El supervisor local anterior continúa activo; NO_GO_INSTITUCIONAL. Las pruebas PHP/React citadas a continuación corresponden a DOC-21 y no se reejecutaron en esta intervención operativa.

# Actualización local DOC-21

La vigencia documental se comprueba antes de nuevas evaluaciones, sin revocar habilitaciones ni resultados históricos. Validación: 270 pruebas PHP / 1.675 aserciones y 31 pruebas React. Detalles y pendientes: [DOC21_VIGENCIA_DOCUMENTAL.md](docs/DOC21_VIGENCIA_DOCUMENTAL.md). Se mantiene NO_GO_INSTITUCIONAL.

**Antecedente local (22/11/2026):** Fase 2, seguridad documental de postulantes y subsanaciones. 255 pruebas / 1496 aserciones y 28 pruebas React. [Resultados, operación y límites](docs/FASE2_SEGURIDAD_DOCUMENTAL_POSTULANTES.md). NO_GO_INSTITUCIONAL; las cifras anteriores son antecedentes.

# Sistema de Selección de Auxiliares FICCT / UAGRM · Grupo #09

**Corrección local de integridad en transiciones:** 213 pruebas Laravel / 1223 aserciones. Envío y conclusión de revisión verifican bytes y disponibilidad. ClamAV real: cuatro casos de alcance limitado; EICAR pendiente. [Evidencias y límites](docs/INTEGRIDAD_EN_TRANSICIONES_Y_CLAMAV.md). Las cifras siguientes corresponden a versiones anteriores.


**Refuerzo local de cuarentena institucional:** 209 pruebas Laravel / 1184 aserciones, 22 pruebas React y seis comprobaciones de navegador. El analizador real no está instalado/configurado: las fuentes quedan bloqueadas hasta disponer de un análisis válido. [Operación y límites](docs/CUARENTENA_FUENTES_INSTITUCIONALES.md). Las cifras de versiones anteriores son antecedentes.


**Fase 1 institucional local — 21/11/2026:** expediente de preparación, fuentes versionadas y revisión técnica separada. 199 pruebas / 1123 aserciones, 21 pruebas React y seis comprobaciones de navegador. [Alcance, uso y pendientes](docs/PREPARACION_INSTITUCIONAL_FASE_1.md). No habilita concursos reales; la versión publicada de la asignatura se conserva.

**Antecedente — 21/11/2026:** reconversión INF513 P2 con React, API Laravel y PostgreSQL. Ejecución actual: **189 pruebas / 1026 aserciones**, **18 pruebas React**. [Arquitectura, roles, instalación y límites](docs/INF513_P2_IMPLEMENTACION.md). Interfaz local: `/p2`. [Aplicación publicada](https://www.tecnoweb.org.bo/inf513/grupo09sa/proyecto2/p2) · [Repositorio](https://github.com/BrayanJoelRamosVargas1/inf513-proyecto2). Recorrido remoto real de 22 comprobaciones con datos sintéticos; validación institucional pendiente.

**Antecedente — 18/11/2026:** revisión ampliada de roles, navegación, permisos y descargas; ayuda por perfil y muestras PDF A4. Ejecución: **164 pruebas / 803 aserciones**, **20 comprobaciones con navegador** y consulta de cinco perfiles en la instancia activa. [Informe y límites](docs/REVISION_INTEGRAL_ROLES_Y_DOCUMENTOS.md). Los escenarios documentales están en convocatorias #7 y #8 de este equipo; otras instalaciones generan sus propios IDs.

**Antecedente — corrección inicial del 18/11/2026:** postulación y espacios por perfil corregidos; registro nuevo conserva la sesión. Regresión ejecutada: **148 pruebas / 685 aserciones**; recorrido real: **13 comprobaciones / 97 respuestas HTTP**. Ver [informe](docs/CORRECCION_ROLES_Y_POSTULACION.md). Las cifras de entregas anteriores se conservan como antecedentes. Validación institucional pendiente.

**Antecedente — 15/11/2026:** cierre funcional de RF-02/RF-03/RF-28/RF-29: gestión/número, administración de tipos, consolidado por convocatoria y exportaciones detalladas. Nueva ejecución: **136 pruebas / 585 aserciones** y recorrido de **16 comprobaciones de interfaz**. [Cambios, evidencias y límites](docs/CIERRE_FUNCIONAL_RF02_RF03_RF28_RF29.md). No es autorización institucional.


**Antecedente — 15/11/2026:** la instalación operativa registró **119 pruebas / 489 aserciones** el 12/11. El 15/11 se ejecutaron **13 comprobaciones adicionales desde Chromium**, con 85 respuestas HTTP, en una copia aislada. El código no cambió en esta continuación y no se volvió a ejecutar PHPUnit. [Entrega vigente, acceso y límites](docs/CONTINUIDAD_VERIFICADA_DEMO_0_3.md). Las cifras que siguen son antecedentes identificados por fecha.

**Última continuación — 11/11/2026:** seguridad de respuestas e identificadores corregida; 118 pruebas / 487 aserciones y revisión de 29 combinaciones de pantalla y perfil. [Informe y límites](docs/SEGURIDAD_Y_ACCESIBILIDAD_AMPLIADAS_DEMO_0_3.md).

**Corrección técnica — 11/11/2026:** cierre de REP-01 (directorio de pruebas en el paquete), A11Y-01 (contraste) y A11Y-02 (navegación móvil). Evidencia y alcance: [informe de cierre](docs/CIERRE_REPRODUCIBILIDAD_Y_ACCESIBILIDAD_DEMO_0_3.md). No se modifica el dominio ni se inicia DEMO 0.4.

**Último ajuste de DEMO 0.3:** VIS-01 y VIS-02 corregidos exclusivamente en las vistas, con 110 pruebas / 415 aserciones y un recorrido nuevo de 21 comprobaciones / 104 solicitudes HTTP. Informe y evidencias: [Cierre de presentación](docs/CIERRE_VIS_01_VIS_02_DEMO_0_3.md). Los registros anteriores de 102 pruebas se conservan como evidencia histórica. No se inicia DEMO 0.4 ni se declara validación institucional.

DEMO 0.3 — Expedientes y participación. Continúa DEMO 0.2 conservando sus datos e integridad; agrega subsanaciones versionadas, retiros y renuncias con decisión separada, vacantes retenidas y manifestaciones sobre múltiples adjudicaciones. Los procedimientos nuevos son técnicos DEMO, sujetos a validación institucional. Conserva los 8 CU y los 30 RF en `docs/CU_Y_RF_CANONICOS.md`. Incluye código de aplicación, base de datos real, pantallas, pruebas y evidencia. No es una implantación institucional ni un cierre total del contrato.

## Abrir la instalación operativa validada en este equipo

La instancia operativa separada está en **http://127.0.0.1:8116**. El iniciador `ABRIR_DEMO_FICCT_VALIDADA.ps1` se encuentra en la carpeta del proyecto que contiene este directorio `sistema`. Sus credenciales ficticias están en `sistema/.local/operacion-03/runtime/sistema/.local/ACCESOS_DEMO.txt`, relativo a esa carpeta del proyecto. El archivo privado y el iniciador pertenecen a la instalación local existente; no se distribuyen como un instalador portable en el ZIP.

Esta instancia utiliza una copia de ejecución y una base separadas. Las ventanas de subsanación vencen con el tiempo: no se renuevan al abrir la DEMO. El recorrido del 15/11 cerró la ventana vencida y abrió otra en una copia aislada, sin alterar los datos de la instancia disponible.

## Abrir la instalación original o instalar una copia nueva

El iniciador incluido en el código utiliza **http://127.0.0.1:8099**. Es otra instalación, con su propia base y credenciales. Para usarla desde una copia nueva del código:

```powershell
.\INICIAR_DEMO.ps1
```

El script usa PHP/Composer/Python en PATH y PostgreSQL 15 en `C:/Program Files/PostgreSQL/15/bin`. Si está en otra ubicación, definir `$env:FICCT_PG_BIN` con su carpeta `bin`. Inicia un cluster aislado en `127.0.0.1:55439`, sin modificar el servicio PostgreSQL existente. Contraseñas aleatorias de cuentas ficticias en **`.local/ACCESOS_DEMO.txt`**, archivo privado excluido del ZIP y de Git. No se necesitan cuentas reales, Docker, Node ni conexión CPD para utilizar la DEMO.

El script no renueva la clave si ya existe ni sobrescribe el seed cuando hay convocatorias. No ejecuta borrados de datos. Si una política de PowerShell impide abrirlo, usar los comandos manuales siguientes; no es necesario desactivar políticas.

## Instalación desde el ZIP de código (Windows)

Requisitos: PHP 8.3 con BCMath, PDO PostgreSQL, mbstring, gd, zip, dom, fileinfo, intl y openssl; Composer 2; PostgreSQL 15 (binarios); Python 3. Los recursos compilados están incluidos; para modificar React se requiere Node y npm run build. No se usan CDN.

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

Ver `docs/DEMO_0_3_CAMBIOS_Y_VALIDACION.md` y `docs/03-CONTRASTE_NORMATIVO_DEMO_0_3.md`. Las subsanaciones, retiros/renuncias y manifestaciones sobre varias plazas ya funcionan como DEMO. No se presumen incompatibilidades ni se cubren vacantes automáticamente. Los plazos de subsanación se configuran expresamente por ventana. Las confirmaciones institucionales, firmas y procedimientos de reemplazo siguen pendientes. Los siete originales se recuperaron con hashes verificados; el contraste documental de 82 artículos se conserva en la adenda normativa. La vigencia y conformidad institucional siguen pendientes; ver `docs/03-CONTRASTE_NORMATIVO_DEMO_0_3.md`.

OFICIAL e históricos están bloqueados para operaciones. El nuevo examen existe como subflujo, pero condiciones, resultados y acta son registro explícito de la comisión DEMO, sin firma institucional. No hay conexión CPD. No se automatiza una fórmula de áreas × escrito/práctico. El servidor de PHP y el usuario del cluster local no son una configuración de producción. Se requiere validación institucional, operación, política de datos, firmas y endurecimiento antes de otro uso.

Documentación: `docs/01-AUDITORIA-FUENTES.md`, `docs/02-TRAZABILIDAD.md`, `docs/ARQUITECTURA.md`, `docs/MANUAL_Y_DEMO.md`, `docs/ESTADO_PROYECTO.md`. Las fuentes originales no se duplican en el ZIP de código: se conservan en `ADENDA_NORMATIVA_FICCT_DEMO_0_3.zip`, entrega documental separada.
