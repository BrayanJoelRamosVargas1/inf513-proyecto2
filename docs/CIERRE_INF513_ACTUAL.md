> Estado del 24/11/2026: consultar [ESTADO_PROYECTO.md](ESTADO_PROYECTO.md) y la instantánea `cierre/10_ESTADO_ACTUAL.md` del candidato. Las ejecuciones, cifras y comprobaciones remotas fechadas en este documento se conservan como antecedentes; no describen automáticamente el nuevo hash ni una publicación actual. El procedimiento de publicación y reversión sigue aplicándose al mismo sistema.

# INF513 P2 — cierre local y publicación preparada

Fecha: 22/11/2026. Fuente vigente: el proyecto `sistema`, con DOC-21 y el rediseño integrado. **No se publica ni se entrega en la plataforma mediante esta preparación.** `NO_GO_INSTITUCIONAL` continúa vigente.

## Aceptación de instalación — 23/11/2026

Se instaló desde cero el tarball auditado de 373 archivos/372 hashes, SHA-256
`0dfed12b495f6c506c42896b36607cc9db8a48492bf33c10e544fb1b0c27dfc3`.
Composer y npm instalaron sus dependencias sin copiar las de la DEMO; se creó otro
clúster PostgreSQL con almacenamiento nuevo y cuentas sintéticas. Pasaron 276
pruebas PHP/1712 aserciones, 37 React, compilación, migraciones, restauración de
las 50 tablas con contenido idéntico y acceso HTTP de cinco perfiles. Se crearon
16 triggers; el clúster de ensayo quedó detenido. Evidencia nueva, no histórica:
`evidence/clean-install-current/installation.json`, `phpunit.xml` y `vitest.json`.

El conductor actualizado está en `scripts/verify_clean_install.py`; sus cuatro
regresiones de entrada pasaron. Procedimiento y límites en
[INSTALACION_DESDE_ENTREGA.md](INSTALACION_DESDE_ENTREGA.md). Este ensayo no configura
ClamAV ni repite aceptación visual humana: conserva el bloqueo documental y no
fabrica análisis limpios. Las pruebas PHPUnit emplean los dobles declarados en
sus fixtures; no se presentan como una ejecución del antivirus real.

**Este primer ensayo corresponde al paquete anterior de 373 archivos.** Se
conserva su copia por SHA-256 en `.local/release-backups`. La entrega actual se
prepara desde el mismo proyecto e incorpora el conductor corregido, sus pruebas
y el procedimiento de instalación. Su hash e inventario se registran en
`evidence/academic-close/package.json` y en el archivo `.sha256` junto al tarball.
La aceptación de ese nuevo hash se registra fuera del paquete en
`evidence/release-acceptance/installation.json`; solo un resultado PASS de ese
archivo acredita su instalación independiente. Se deja fuera para evitar un
hash circular, no se atribuye automáticamente el ensayo anterior al paquete nuevo.

Comprobación remota nueva, solo lectura: Tecnoweb responde HTTP 200 con cabecera
PHP 8.4.28; PostgreSQL 18.6 mantiene 41 tablas/9 triggers en `ficct_p2`. FTP
rechaza conexión y SSH rechaza las credenciales guardadas. No se pudieron
inspeccionar las extensiones PHP, configuración privada, permisos efectivos ni
ClamAV/trabajador. GitHub conserva `portal.css` de 4853 bytes frente a 17156 bytes
locales (comparación sin salto final), por lo que continúa pendiente actualizarlo.
Los fallos DNS del intento anterior quedan como antecedente, no como estado actual.
Ver `remote-recheck.json`, `remote-current.json`, `remote-ssh-current.json` y
`github-current.json` en la misma carpeta de evidencia.

La DEMO estaba detenida al retomar la sesión. Se inició su clúster existente y se
esperó su recuperación automática tras una interrupción; no se eliminaron archivos
de PostgreSQL ni se recreó la base. La aplicación vuelve a responder en 8116 y se
retomó el mismo supervisor documental local. Se conservan 50 tablas, 35 archivos
privados y las huellas de las ocho fuentes protegidas. No se instaló un servicio
Windows. No hubo cambios remotos ni entrega en plataforma.

## Una versión de código, compilación y runtime

`npm run build` genera ahora `public/build/source-hashes.json`: registra las huellas de las fuentes React/CSS, los archivos de configuración de compilación y los recursos generados. Una edición sin recompilar hace que la sincronización se rechace. El registro detecta diferencias accidentales; no sustituye una firma de confianza ni una auditoría del código.

Desde `sistema`, para comparar sin cambiar archivos:

```text
python scripts/sync_runtime.py --target .local/operacion-03/runtime/sistema
```

Con el código y la compilación ya revisados:

```text
npm run build
python scripts/sync_runtime.py --target .local/operacion-03/runtime/sistema --apply
python scripts/sync_runtime.py --target .local/operacion-03/runtime/sistema
```

La última comprobación debe responder `MATCH`. El script verifica los lockfiles de dependencias, compara los archivos gestionados y copia solo diferencias. Conserva los recursos compilados antiguos para las pestañas abiertas y cambia el manifiesto Vite al final. Actualiza las cachés de rutas/vistas, comprueba las huellas y conserva un respaldo privado de los archivos reemplazados. No copia `.env`, dependencias, sesiones, documentos ni bases; tampoco ejecuta migraciones o elimina archivos antiguos fuera del conjunto copiado.

El resultado de `--apply` indica la carpeta privada del respaldo. Para una reversión local de código:

```text
python scripts/sync_runtime.py --target .local/operacion-03/runtime/sistema --rollback RUTA_DEL_RESPALDO
```

La reversión comprueba el destino, el respaldo y que no haya ediciones posteriores; ante cambios nuevos se detiene para no sobrescribirlos. Un fallo detectado durante la actualización restaura los archivos anteriores. No es un despliegue transaccional de todo el sistema: una interrupción abrupta puede requerir revisar el estado `prepared` y el bloqueo privado antes de recuperar. No borrar ese bloqueo ni repetir la actualización sin revisar el respaldo y el proceso anterior. Las migraciones de base necesitan un procedimiento independiente.

Se añadieron cinco pruebas de sincronización: preservación de secretos/documentos al aplicar y revertir, rechazo de ediciones posteriores, reversión por fallo de caché, rutas inseguras y fuentes sin compilar. Otras tres pruebas del empaquetado cubren credenciales, contenido alterado y enlaces inseguros del tarball.

## Comprobación funcional y límites

La regresión actual está en `evidence/academic-close`: PHPUnit sobre `ficct_test`, React/Vitest y las pruebas de herramientas se ejecutan con sus salidas registradas. El ensayo de operaciones utiliza **el mismo código de aplicación**, otra base y almacenamiento de prueba; no crea otra copia de Laravel o React ni modifica los expedientes de la DEMO.

El recorrido conecta formularios, peticiones reales y datos: postulación, carga inválida y válida, cuarentena, revisión, dos subsanaciones, ClamAV real, méritos, asignación, etapas, nota borrador/confirmada, cálculo, publicación, manifestación DEMO, pagos/cuotas y creación de usuario. La configuración de usuarios y convocatoria inicial es un fixture de prueba; el analizador se ejecuta por una herramienta privada de operador. No se sustituye por un analizador falso. Las restricciones DOC-21 deben bloquear la nota/cálculo mientras exista un nuevo análisis pendiente y restablecerlos tras un análisis válido.

Consultar `navegador/recorrido-http.json` y `http-restauracion-preservacion.json` para el resultado efectivo, sus límites y la restauración. Los intentos anteriores con orden incorrecto de publicación no constituyen resultados aprobados. El dominio exige aprobar las ofertas antes de cerrar la etapa de resultados; el ensayo debe respetarlo, sin modificar esa regla.

Resultados nuevos de esta continuación:

| Control | Resultado ejecutado |
| --- | --- |
| PHPUnit | 276 pruebas, 1712 aserciones; cero fallos/errores |
| React | 37 pruebas aprobadas |
| Herramientas de publicación/sincronización | 8 regresiones aprobadas, en temporales |
| Operaciones de negocio por interfaz/API real | 20 comprobaciones; 52 solicitudes instrumentadas, no el total de tráfico del navegador |
| ClamAV real | Dos versiones limpias y detección de firma adicional inocua; nuevo análisis invalida la validación anterior |
| Restauración del ensayo | 50 tablas idénticas, 16 triggers y 42 archivos privados recuperados |
| Preservación de la DEMO | 50 tablas y 35 archivos privados sin cambios durante el ensayo |
| Sincronización final | MATCH; las fuentes gestionadas y recursos compilados coinciden |

El resultado calculado por el formulario fue 68/100, a partir de méritos 20/40 y examen 80/100 según el esquema DEMO existente. Se publicó una versión y se registró la manifestación del postulante. Los errores probados incluyen archivo no admitido, documento pendiente/bloqueado, motivo demasiado breve, correo inválido y nota fuera de rango. No se afirma cobertura exhaustiva de todos los formularios o combinaciones.

El código del ensayo está en `scripts/verify_academic_business.py` y `scripts/academic_business.cjs`. El ejecutor está preparado para esta instalación local Windows registrada: requiere su estado privado, ClamAV real con las firmas de ensayo, PostgreSQL y Playwright/Chromium. No contiene sus credenciales. Desde `sistema`, `python scripts/verify_academic_business.py` genera fixtures y otra base/almacenamiento de prueba; las salidas nuevas van a `evidence/academic-business/<fecha>` y el servidor de ensayo se detiene al terminar. Los caminos de Playwright y Chromium pueden especificarse mediante `FICCT_PLAYWRIGHT_MODULE` y `FICCT_CHROMIUM_EXECUTABLE`. No se presenta este ejecutor como instalador universal ni debe apuntarse a una base oficial.

La matriz visual de la fase anterior conserva sus resultados y límites en [REDISENO_FRONTEND_ACTUAL.md](REDISENO_FRONTEND_ACTUAL.md). No se suman sus pruebas a una ejecución nueva ni se presenta axe como certificación humana de accesibilidad.

## Contraste con la consigna de INF513

| Requisito | Implementación y comprobación | Cierre pendiente |
| --- | --- | --- |
| Diseño y navegación | React/CSS compartidos; recorrido visual por perfil, móvil y temas | Ensayo final en el alojamiento que se publicará |
| Dos roles de negocio más administrador | Postulante, revisor, evaluador y autoridad; administración técnica separada | Cuentas de defensa y responsabilidades verificadas en destino |
| Menú desde PostgreSQL | `navigation_items`, `navigation_roles`, API de navegación y permisos en servidor | Comparar configuración del destino |
| Laravel API + React cliente HTTP + PostgreSQL | `routes/api.php`, Axios y servicios de dominio compartidos | Configuración PHP/SQL/subruta real del servidor |
| Bitácora, IP y recursos | `access_events`, auditoría y estadísticas por ámbito | Verificar cookies, IP/proxy y retención en destino |
| Tres temas y Día/Noche | Niños/Jóvenes/Adultos, Día/Noche/Automático; preferencias persistentes | Evaluación humana de accesibilidad |
| Contraste y fuentes | Controles existentes; matriz de 48 combinaciones y revisiones axe | No equivale a certificación WCAG integral |
| Validación en español | Restricciones HTML, validación Laravel y errores JSON; errores reales de archivo/revisión y rangos | No se garantiza cada combinación inválida de todos los formularios |
| Contador persistente en cada vista | Recibos autenticados y públicos; footer en ingreso/registro y vistas del portal | Repetir idempotencia/CSRF con la sesión/subruta remota |
| Dashboard de negocio y accesos | API de métricas y gráficos filtrados por ámbito | Cifras reales del entorno de presentación |
| Búsqueda en encabezado | Convocatorias, ofertas y tipos autorizados, con paginación | Repetir consultas de defensa en destino |
| Pagos y planes | Métodos, éxito/fallo, cuotas y reporte, siempre simulados | No se necesita ni se configura dinero real |
| GitHub público | Repositorio existente `BrayanJoelRamosVargas1/inf513-proyecto2` | Actualizar al código revisado; no se inventa contribución grupal |
| Tecnoweb y PostgreSQL asignado | Hay evidencias históricas de publicación académica | Preinstalación, actualización autorizada y recorrido remoto nuevos |
| Tarball de entrega | Se prepara desde las fuentes actuales, con manifiesto y revisión de secretos | No prueba por sí solo instalación independiente o entrega en plataforma |
| Presentación antes de las 20:00 del último día de clases | Consigna conservada | Confirmar fecha concreta y entregar en plataforma; no realizado |

## Tarball actual y actualización controlada

```text
python scripts/package_academic_release.py --runtime .local/operacion-03/runtime/sistema
```

El script actualiza el **mismo** `2026-2_INF513-P2_grupo09sa.tar.gz` de la raíz del proyecto. Primero verifica la compilación, selecciona fuente/documentación/pruebas y evidencias publicables, compara contra credenciales privadas conocidas y patrones de tokens, genera el manifiesto y reabre el archivo para verificar todos los bytes. No incluye `.env`, `vendor`, `node_modules`, sesiones, dumps o documentos privados. Conserva la entrega anterior por su SHA-256 en `.local/release-backups`. El análisis de secretos reduce riesgos conocidos; no garantiza detectar cualquier secreto desconocido.

Se incluye el código académico y DOC-21 de las fuentes actuales; no se reutiliza el tarball académico antiguo como backend. El resultado del empaquetado se registra en `evidence/academic-close/package.json`, fuera del propio archivo para evitar un manifiesto circular. La comprobación byte por byte no equivale a instalar el tarball desde cero en otra máquina.

## Preinstalación remota obligatoria antes de autorizar cambios

1. Identificar la versión, esquema y rutas reales de Tecnoweb sin cambiar datos. Las evidencias de 41 tablas/9 triggers corresponden a una versión académica anterior; no deben confundirse con las 50 tablas/16 triggers locales actuales.
2. Confirmar PHP/extensiones, dependencias del lockfile, permisos mínimos, almacenamiento privado, HTTPS/subruta y posibilidad de ejecutar el trabajador documental con ClamAV y firmas vigentes. Si el alojamiento compartido no admite el motor o el trabajador, registrar el bloqueo. **No sustituir el análisis por resultados limpios ficticios ni omitir DOC-21 para la defensa.**
3. Tomar respaldo verificado de código, configuración privada, esquema/datos y documentos. Ensayar restauración antes de cambios. Conservar hash y versión de cada parte del respaldo; no subirlos al repositorio público.
4. Aprobar la actualización revisando el tarball, manifiesto, diferencias y migraciones pendientes. Programar una ventana sin nuevas escrituras para la migración. No ejecutar `migrate:fresh`, seeders de reinicio ni SQL que elimine historia.
5. Preparar recursos con `VITE_ASSET_BASE=/inf513/grupo09sa/proyecto2/build/`, conservar la misma fuente y comprobar el nuevo registro de compilación. Configurar `APP_URL`, `SESSION_PATH`, clave, base y ClamAV privados del destino; no copiar la configuración local.
6. Aplicar solo las migraciones aditivas revisadas, activar el código completo y comprobar trabajador/cola/descargas, los cinco perfiles y los requisitos académicos. Los originales y resultados anteriores deben conservarse. Repetir el recorrido de presentación en la URL pública.
7. Ante un fallo, suspender nuevas escrituras y descargar evidencias antes de revertir. Una reversión de código no deshace migraciones ni documentos nuevos. No volver al backend académico antiguo sin cuarentena sobre una base con nuevos documentos: recuperar un conjunto coherente y seguro de código/base/archivos bajo el procedimiento aprobado, o mantener mantenimiento hasta resolverlo.

La sincronización local no ejecuta esta actualización remota. Tampoco se habilitan servicios de Windows, inicio automático, reinicios, AWS ni concursos oficiales durante el cierre académico.
