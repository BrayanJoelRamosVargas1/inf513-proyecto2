# WIN-01 — Configuración única para administrar el servicio

Estado: **reproducido y corregido mediante pruebas aisladas**. WIN-02, instalación SCM, LocalService, ACL y reinicio real siguen **NOT_VERIFIED**. Se conserva `NO_GO_INSTITUCIONAL`.

## Reproducción anterior a la corrección

El ensayo ejecutó el bloque real de `EnableAutomatic`, extraído mediante el analizador de PowerShell. Los archivos de configuración eran reales, pero las llamadas de servicio y del diagnóstico eran dobles controlados: no se registraron servicios ni se cambió el Registro de Windows.

La copia externa indicaba la dependencia `ExternalOld`; el archivo instalado indicaba `InstalledWanted`. El bloque seleccionó `ExternalOld` para configurar dependencias y pasó al diagnóstico la ruta del archivo instalado. La regresión falló porque esperaba una fuente coherente. La evidencia se conserva en `reproduccion-antes.json`; no es una ejecución de SCM.

## Corrección

`service_configuration.ps1` resuelve la configuración antes de cualquier acción administrativa:

- `Build` e `Install` conservan el archivo externo como fuente para preparar una instalación inicial.
- `Start`, `Stop`, `Status`, `EnableAutomatic` y `Uninstall` cargan sus valores exclusivamente del `service.json` instalado en el directorio seleccionado.
- Si `-ConfigPath` apunta a otra copia, se exige identidad SHA-256 de sus bytes con el archivo instalado. Si difiere, la operación se rechaza. Por diseño también se rechaza una copia que solo cambia formato o saltos de línea; utilizar directamente el archivo instalado evita esa ambigüedad.
- Una copia idéntica puede servir de referencia, pero los valores se toman del archivo instalado. Si falta o es inválido, no se utiliza la copia externa como sustituto.
- La identidad de servicio, raíz de aplicación, Python, nombre de trabajador, dependencias y diagnóstico proceden de la misma configuración seleccionada. Permanecen los controles de administrador, registro del ejecutable propio, salud y dependencias existentes.

Las acciones de instalación y administración continúan requiriendo las condiciones descritas en `OPERACION_SERVICIO_DOCUMENTAL_WINDOWS.md`. Esta corrección no concede privilegios ni autoriza la instalación institucional.

## Pruebas nuevas

Se ejecutaron once regresiones en `scripts/windows/test_service_configuration.ps1`:

1. Rechazo de copia desactualizada en las cinco acciones administrativas.
2. Selección del archivo instalado para esas cinco acciones.
3. Copia externa idéntica aceptada, manteniendo la fuente instalada.
4. Ausencia del archivo instalado sin sustitución externa.
5. Rechazo de divergencias en rutas, Python, trabajador, entorno y base externa.
6. JSON instalado inválido rechazado.
7. Compilación e instalación inicial conservan su fuente externa.
8. Ejecución del bloque real `EnableAutomatic` con dependencias y ruta del diagnóstico coherentes; llamadas SCM simuladas.
9. Rechazo conservador de una copia con formato distinto.
10. Conservación exacta del archivo instalado del ensayo.
11. El script completo rechaza discrepancias antes de administrar el servicio.

Además se ejecutó una compilación real mediante `Build`, sin instalar. Las pruebas usan directorios temporales propios, comprueban el destino antes de limpiarlos y no modifican servicios registrados ni bases de datos. No se repitieron las pruebas PHP, React o del ciclo de vida C#: sus archivos y resultados anteriores se conservan con su alcance y fecha.

Para repetir las regresiones, desde la raíz de la aplicación:

```powershell
powershell -NoProfile -File scripts/windows/test_service_configuration.ps1 -OutputPath C:\FICCT\evidencias\regresiones-configuracion.json
```

El directorio de salida debe existir. No se necesita elevación para estas regresiones; no confundir esa posibilidad con permisos de instalación.

## Seguimiento de los siete controles de la auditoría

| Control | Estado de esta intervención |
| --- | --- |
| Corregir o descartar WIN-01 | Reproducido y corregido; once regresiones PASS |
| Instalación administrativa aislada | Pendiente; sesión sin elevación |
| Identidad LocalService y ACL efectivas | NOT_VERIFIED |
| PostgreSQL correcto bajo la cuenta del servicio | NOT_VERIFIED |
| Start, Stop y recuperación de SCM | NOT_VERIFIED; pruebas anteriores en consola conservadas |
| Reinicio real y recuperación de reservas | NOT_VERIFIED |
| Recepción de alertas externas | Pendiente; diagnóstico local sin receptor externo |

**Uno de siete controles cerrado en su alcance comprobado.** La DEMO conserva su supervisor anterior. No se solicitó elevación automática, no se reinició Windows y no se desplegó esta fase en el servidor docente. PERF-01 continúa pendiente de medición; no se modificó `readiness()` ni se redujeron las comprobaciones documentales.
