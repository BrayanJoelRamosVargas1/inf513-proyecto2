# Procesamiento documental como servicio de Windows

**Estado: adaptador implementado y probado con procesos reales en modo consola. Servicio de Windows no instalado; reinicio de Windows no verificado.** Se conserva `NO_GO_INSTITUCIONAL`.

Esta intervención continúa la auditoría de DOC-21. No modifica el control documental, el motor académico, las reglas de selección, los 8 CU ni los 30 RF. Las 270 pruebas PHP y 31 React pertenecen a la validación anterior DOC-21; no se presentan como una nueva ejecución de esta intervención operativa.

Actualización WIN-01: las acciones de administración cargan únicamente la configuración instalada y rechazan una referencia externa con bytes diferentes. Compilación e instalación inicial mantienen la entrada externa. Reproducción, once regresiones y límites: `WIN01_CONFIGURACION_INSTALADA.md`. Esta actualización no cierra la aceptación bajo LocalService ni el reinicio real.

## Qué se incorpora

- `scripts/windows/DocumentProcessorService.cs`: adaptador nativo del administrador de servicios, compilable con .NET Framework ya disponible. Ejecuta el supervisor Python existente, conserva su exclusión de instancias y sus reservas en PostgreSQL. No analiza archivos ni emite resultados por su cuenta.
- El adaptador contiene su árbol de procesos en un Job Object de Windows. Crea el supervisor suspendido, lo incorpora antes de ejecutarlo y cierra sus descendientes si cae el supervisor o el propio adaptador. La recuperación usa espera creciente; una interrupción no convierte un análisis en limpio.
- `manage_document_service.ps1`: compilación y administración explícita. La instalación exige administrador, rechaza otra instalación existente y utiliza LocalService con SID propio del servicio. Protege su ejecutable y configuración; no concede escritura al código ni a los originales. La preparación de permisos de ejecución/datos del runtime sigue siendo una tarea administrativa pendiente de validar.
- La instalación comienza en modo manual. `EnableAutomatic` exige un servicio ejecutándose, identidad y latidos válidos, trabajador y cola saludables, además de declarar las dependencias de arranque de la base o confirmar una base externa. Solo entonces configura inicio automático diferido.
- `check_document_service.py`: diagnóstico de solo lectura para un monitor externo. Comprueba edad de los latidos, identidad de ejecutables/procesos, resultado del trabajador y diagnóstico de cola. Una cola vacía no basta para declarar que el trabajador existe. Devuelve 0 saludable, 1 degradado o 2 no verificable. No envía avisos ni modifica tareas.

No se instaló software adicional, no se cambió el antivirus y no se alteró el supervisor local que ya mantiene la DEMO disponible.

## Evidencias ejecutadas

La prueba privada de aceptación registró once controles, incluidos procesos PHP reales contra una copia aislada de PostgreSQL, exclusión de otra instancia, caída y recuperación del supervisor, cierre de descendientes, parada controlada, reinicio del adaptador, caída abrupta del adaptador, rechazo de configuración incompleta y rechazo administrativo sin instalación. Confirmó también la preservación de las 49 tablas y 35 archivos privados de la base operativa.

El comprobador portable entregado volvió a ejecutar ocho controles de ciclo de vida sobre la copia aislada. Sus resultados están separados de las ocho pruebas sintéticas del clasificador de salud. Las pruebas sintéticas simulan estados; las pruebas de ciclo de vida utilizan procesos reales. Ninguna equivale a una prueba de arranque del equipo ni a una ejecución del servicio bajo LocalService.

Las reservas de trabajos interrumpidos se conservan en la copia de laboratorio. No se borró historial para facilitar las pruebas ni se liberaron documentos de la DEMO operativa.

## Bloqueos que siguen abiertos

1. La sesión actual dispone de un token de usuario sin elevación. El instalador lo detectó antes de registrar un servicio o cambiar carpetas protegidas. Hace falta ejecutar la instalación en una sesión administrativa.
2. La base PostgreSQL local del proyecto utiliza su instancia aislada en el puerto 55442. Su arranque es administrado por las herramientas locales existentes. No se ha demostrado que arranque como servicio al reiniciar Windows. No se debe declarar como dependencia otra instancia PostgreSQL solo por tener un nombre parecido o usar el puerto 5432.
3. Falta probar permisos efectivos bajo el SID de servicio: lectura de aplicación/configuración, PHP, Python, motor y firmas; escritura limitada a registros, estados operativos, caché necesaria y temporales de escaneo. Los originales deben seguir sin permiso de modificación para el trabajador.
4. Falta validar `Start-Service`, `Stop-Service`, recuperación de SCM y arranque tras reinicio real. No se reinició el equipo del usuario.
5. El diagnóstico está disponible, pero no hay un sistema externo de alertas conectado. PERF-01 sigue sin medición de carga representativa; no se optimiza `readiness()` ni se reduce la verificación transaccional.
6. EICAR, capacidad, respaldos externos y aceptación institucional G01–G10 siguen pendientes.

## Procedimiento administrativo preparado

Conservar el respaldo de la instalación actual y mantener detenido cualquier servicio nuevo hasta revisar sus permisos. Copiar `service.example.json` a una ubicación privada y ajustar rutas absolutas a una instalación completa. La configuración de ejemplo no contiene credenciales ni supone un nombre real de servicio de base de datos.

Desde la raíz de la aplicación, PowerShell permite revisar y compilar sin instalar:

```powershell
.\scripts\windows\manage_document_service.ps1 -Action Build -ConfigPath C:\FICCT\privado\service.json -InstallDirectory C:\FICCT\compilacion
```

La instalación, en PowerShell administrativo y con configuración revisada:

```powershell
.\scripts\windows\manage_document_service.ps1 -Action Install -ConfigPath C:\FICCT\privado\service.json
```

Después de instalar, el SID `NT SERVICE\FICCTDocumentProcessor` debe tener lectura/ejecución sobre el runtime y sus ejecutables/firmas. Conceder modificación únicamente en `.local/document-processor`, `storage/logs`, el directorio de caché necesario y `storage/app/private/institutional-scan-work`. Revisar los permisos existentes y los directorios padres; no usar permisos generales de escritura sobre la aplicación o sobre `storage/app/private`. Estas ACL no se han aplicado en esta sesión.

Tras preparar el arranque de la base y los permisos, detener el supervisor local anterior de forma controlada y verificar su cierre antes de sustituirlo. No ejecutar ambos por accidente con el mismo nombre. Iniciar el servicio nuevo con `-Action Start`, verificar su diagnóstico, probar parada y recuperación, y solo entonces usar `-Action EnableAutomatic`. Para administrarlo después, usar el archivo instalado `C:\ProgramData\FICCT\DocumentProcessor\service.json`; no una copia desactualizada.

Para consultar salud:

```powershell
python .\scripts\windows\check_document_service.py --config C:\ProgramData\FICCT\DocumentProcessor\service.json --host-binary C:\ProgramData\FICCT\DocumentProcessor\FicctDocumentProcessor.exe
```

La aceptación portable exige una copia desechable cuya base efectiva se llame `ficct_service_test_*` o `ficct_applicants_*`. Verifica el nombre efectivo de Laravel antes de iniciar procesos. Nunca utilizar la base de la DEMO ni una base institucional:

```powershell
python .\scripts\windows\test_service_lifecycle.py --config C:\FICCT\privado\laboratorio.json --host-binary C:\FICCT\compilacion\FicctDocumentProcessor.exe --output C:\FICCT\evidencias\ciclo.json
python .\scripts\windows\test_service_health.py
```

La desinstalación exige detener el servicio propio y usar `-Action Uninstall`. Retira el registro del servicio, pero conserva runtime, configuración, originales y evidencia. La recuperación tras reinicio debe registrarse como una comprobación nueva, no deducirse de que exista una opción de inicio automático.

## Referencias de implementación

- [ServiceBase y el administrador de servicios de Windows](https://learn.microsoft.com/en-us/dotnet/api/system.serviceprocess.servicebase?view=netframework-4.7).
- [Solicitud de tiempo durante la parada](https://learn.microsoft.com/en-us/dotnet/api/system.serviceprocess.servicebase.requestadditionaltime?view=netframework-4.8.1).
- [Cuenta LocalService](https://learn.microsoft.com/en-us/windows/win32/services/localservice-account).
- [SID por servicio y permisos específicos](https://learn.microsoft.com/en-us/windows/win32/services/service-security-and-access-rights), [SERVICE_SID_INFO](https://learn.microsoft.com/en-us/windows/desktop/api/winsvc/ns-winsvc-service_sid_info).

Son referencias técnicas de Windows; no normativa universitaria ni autorización de producción.
