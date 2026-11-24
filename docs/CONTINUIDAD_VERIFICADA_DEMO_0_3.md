# Entrega vigente — DEMO 0.3, Grupo #09

**Estado vigente — 15/11/2026:** cierre funcional de RF-02/RF-03/RF-28/RF-29: gestión/número, administración de tipos, consolidado por convocatoria y exportaciones detalladas. Nueva ejecución: **136 pruebas / 585 aserciones** y recorrido de **16 comprobaciones de interfaz**. [Cambios, evidencias y límites](CIERRE_FUNCIONAL_RF02_RF03_RF28_RF29.md). No es autorización institucional.


Estado al 15/11/2026: DEMO técnica local, con validación de procesos de participación desde el navegador. No cuenta con autorización institucional.

## Acceso correcto

| Instalación | Dirección | Inicio y cuentas |
|---|---|---|
| Operativa validada, separada de la original | http://127.0.0.1:8116 | En la carpeta del proyecto: `ABRIR_DEMO_FICCT_VALIDADA.ps1`. Cuentas privadas: `sistema/.local/operacion-03/runtime/sistema/.local/ACCESOS_DEMO.txt`. |
| Original o instalación nueva del ZIP | http://127.0.0.1:8099 | En la carpeta `sistema`: `INICIAR_DEMO.ps1`. Cuentas privadas: `.local/ACCESOS_DEMO.txt`, propias de esa instalación. |

El iniciador de la instalación operativa y sus dependencias ya existen en esta computadora; el ZIP no incluye bases, credenciales ni dependencias. Para instalar en otra computadora se deben cumplir los requisitos e instrucciones del README. No se afirma haber probado instalación Linux ni despliegue de producción.

Los escenarios tienen fechas reales de DEMO y pueden vencer. La ventana de subsanación original se conserva vencida; el recorrido nuevo cerró esa ventana y abrió otra en una copia aislada mediante los formularios. No se actualizaron fechas históricas en la base principal.

## Evidencia por ejecución

| Ejecución | Resultado | Evidencia |
|---|---|---|
| Cierre visual previo | 110 pruebas / 415 aserciones | `evidence/cierre-visual-03/` y su informe de fase. |
| Seguridad ampliada previa | 118 pruebas / 487 aserciones; 29 pantallas | `evidence/seguridad-extendida-03/`. |
| Instalación y operación del 12/11 | 119 pruebas / 489 aserciones; 11 escenarios de concurrencia; 300 consultas autenticadas | `evidence/operacion-03/`. Dos ejecuciones de PHPUnit, incluida la de cachés separadas, sin errores finales. Los intentos previos conservan sus fallos y su explicación. |
| Recuperación del 12/11 | 31 tablas comparadas; nueve triggers; 22 descargas privadas verificadas; cinco perfiles | `evidence/operacion-03/recuperacion-ejecutable.json`. |
| Navegador del 15/11 | 13 comprobaciones; 85 respuestas HTTP; 13 capturas | `evidence/navegador-participacion-03/`. Ejecución nueva con Chromium sobre otra base y almacenamiento. |

Las 85 respuestas incluyen recursos y redirecciones: 64 de estado 200, 16 de estado 302 y cinco rechazos 403 esperados. Los formularios ejercitaron subsanación, revisión, retiro, manifestación, renuncia y nueva publicación. También se probaron permisos de decisión y acceso a expedientes/documentos entre propietarios.

La copia aislada conservó los registros históricos existentes de cinco publicaciones, diez notas, 22 documentos, ocho snapshots y una manifestación. Las 31 tablas de la instancia disponible permanecieron iguales. No se ejecutó PHPUnit nuevamente el 15/11: no cambió código de aplicación.

## Qué cambió en esta entrega

Se corrigieron las instrucciones y el estado en README, estado del proyecto, manual y contraste inicial. Se integraron las evidencias del navegador y este punto de entrada. No se modificaron aplicación, rutas, dominio, permisos, pruebas PHP, vistas, estilos, migraciones ni contrato canónico. La comprobación contra el manifiesto del ZIP operativo identifica los cuatro documentos actualizados y exige igualdad de todos los archivos restantes que ya existían.

Los informes y verificadores de fases previas conservan sus cifras y hashes de referencia. `verify_operation_release.py` se aplica al ZIP operativo anterior: su lista de conservación incluye documentos que ahora se actualizaron. Para este ZIP se usa `scripts/verify_continuity_release.py`, que verifica la integridad completa y distingue explícitamente esos cuatro documentos de todos los archivos conservados. Esto no convierte los verificadores de paquetes en nuevas ejecuciones de Laravel.

## Fuentes y decisiones pendientes

Los cuatro PDF y tres fotografías originales ya se recuperaron y cotejaron. La adenda normativa del proyecto contiene la matriz de 82 artículos y sus límites; no se debe interpretar el antiguo `NOT_VERIFIED` de la implementación inicial como si aún faltaran esos archivos.

Quedan pendientes la confirmación de vigencia institucional, puntaje final aprobatorio, aclaración de rúbricas, composición de exámenes, autoridades, hitos/plazos, validez del expediente digital y aceptación/compatibilidades. El sistema no aplica un umbral supuesto de 51 o 55 ni adjudica automáticamente reemplazos. La publicación DEMO no equivale a resolución o nombramiento oficial.

La consulta ya redactada sigue en el paquete de validación técnica del proyecto y no fue enviada. Las pruebas son locales en el mismo equipo, no una auditoría externa, ensayo representativo de producción ni validación institucional. No se inició DEMO 0.4.
