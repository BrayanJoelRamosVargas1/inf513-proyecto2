# Cierre de robustez del procesador documental — PROC-01

Fecha: 22 de noviembre de 2026. Alcance local, sin activación oficial ni cambios en el motor académico. Esta intervención continúa el procesador de la entrega anterior; no crea otro módulo académico ni modifica las fuentes institucionales originales.

## PROC-01: reproducción y corrección

Se reprodujo el caso comunicado: un intento heredado antiguo sin resultado, seguido por un análisis limpio posterior. El mantenimiento anterior creaba otro análisis y cambiaba el estado efectivo de limpio a pendiente. La prueba de reproducción falla justamente en esa comparación; se conserva en evidence/processor-robustness/reproduccion-antes/. Ese XML documenta el fallo anterior y no es la batería final.

La recuperación comprueba ahora, mientras mantiene bloqueada la fuente, si el intento sigue siendo el último. Si hay un intento posterior, agrega un resultado de error con motivo unmanaged_attempt_superseded y un evento inmutable unmanaged_superseded que identifica el intento que lo sustituyó. No crea un trabajo ni una tarea nuevos y no modifica el resultado posterior. Esta regla vale también si el intento posterior está bloqueado o pendiente: recuperar un intento viejo no decide el estado del más reciente.

Si el intento heredado todavía es el último, se conserva la recuperación con reintento de la entrega anterior. El bloqueo de la fuente, compartido con el inicio de análisis y las decisiones, protege la comprobación frente a nuevas transacciones. Las comprobaciones de área ocupada siguen vigentes. Una segunda ejecución no duplica resultados ni eventos.

## Observabilidad de la cola

El comando `php artisan ficct:scan-queue-status` conserva los totales por estado y agrega tareas disponibles, reintentos programados, antigüedad de la tarea disponible más atrasada, reservas vencidas, tareas fallidas aún vigentes y últimos intentos heredados abandonados.

`php artisan ficct:scan-queue-status --check` devuelve código 0 si no encuentra alertas y código 1 cuando encuentra alguna. Los códigos documentados son QUEUE_OVERDUE, LEASES_EXPIRED, CURRENT_TASKS_FAILED y UNMANAGED_CURRENT_PENDING. Sin --check, devuelve el diagnóstico sin convertir las alertas en un error del comando.

El umbral técnico de atraso es de 300 segundos, configurable en document_processor.backlog_seconds. Se mide desde available_at; un reintento que espera su turno no se cuenta como trabajo disponible atrasado. Una tarea fallida histórica sustituida por un análisis posterior no genera una alerta de fallo vigente. Un intento heredado obsoleto tampoco se confunde con el último intento abandonado.

La salida es agregada: no muestra nombres, correos, rutas privadas, huellas de documentos, identificadores de expedientes ni tokens de reserva. Es un diagnóstico operativo de solo lectura y puede cambiar entre consultas concurrentes. No autoriza descargas ni reemplaza los controles transaccionales del dominio.

El comando permite integrar un monitor autorizado. No se instaló un servicio permanente, no se programó una automatización y no se configuró envío externo de avisos. La disponibilidad real, el escalamiento de alertas y la cuenta de servicio siguen requiriendo aceptación de infraestructura.

## Validación ejecutada

- Reproducción anterior de PROC-01: fallo confirmado, de limpio a pendiente.
- Batería final sobre PostgreSQL: 235 pruebas y 1402 aserciones, sin errores ni fallos. Conserva los 227 identificadores anteriores y agrega P15–P22.
- Copia independiente de entrega: nueva ejecución de las mismas 235 pruebas y 1402 aserciones, sin errores ni fallos. El XML actual está indicado en evidence/current-validation.json.
- Regresiones nuevas: análisis posterior limpio, bloqueado y pendiente; simulación sin escrituras e idempotencia; distinción entre atraso y espera programada; fallos históricos; intentos abandonados vigentes; privacidad del diagnóstico y códigos de salida de --check.
- Tres procesos PHP simultáneos y dos fuentes: dos reservas distintas y un trabajador sin tarea, sin duplicar reservas. Las asignaciones se conservan mediante alias en la secuencia anonimizada.
- Bloqueo real de una fuente: PostgreSQL confirmó la transacción abierta; otro trabajador omitió la fuente ocupada y procesó la siguiente; al liberar el bloqueo se procesó la restante.
- Interrupción forzada, recuperación de reserva y rechazo de resultado tardío: repetidos en un entorno aislado. El supervisor se detuvo y reinició correctamente.
- Respaldo tomado después de interrumpir al trabajador: restaurado con tablas y archivos idénticos; la fuente siguió pendiente. Desde esa base restaurada se recuperó el intento y se ejecutó un reintento limpio con ClamAV real.
- Restauración final adicional: 49 tablas idénticas, 15 triggers y 42 archivos privados idénticos, incluidos dos documentos sintéticos y marcadores vacíos de trabajo. La cifra de archivos no representa 42 documentos originales.
- Actualización local: cuatro archivos reemplazados después de un respaldo privado; 49 tablas y 35 archivos privados idénticos antes y después. Sin migraciones.

El análisis se pausa mediante un doble para provocar el fallo. La ronda de concurrencia de tres trabajadores utiliza analizadores sintéticos identificados como TEST-CONCURRENCY. Las recuperaciones indicadas con ClamAV sí ejecutan el motor real. Se separan estas evidencias para no presentar una simulación como aceptación antimalware. Este ensayo comprueba consistencia y recuperación; no demuestra rendimiento con volumen representativo.

## Conservación y pendientes

Selection.php, Scores.php, config/ficct.php y el contrato de 8 CU / 30 RF conservan sus bytes y sus hashes respecto de la entrega anterior. Se conserva el ZIP anterior sin cambios y toda su evidencia histórica. No se cambian las rúbricas, publicaciones, calificaciones, permisos académicos ni decisiones institucionales.

Se mantiene NO_GO_INSTITUCIONAL. Continúan pendientes la protección equivalente de documentos académicos y personales de postulantes, EICAR, la aceptación antimalware independiente, pruebas de carga representativas, operación permanente y las aprobaciones institucionales. Este paquete no se desplegó en el servidor académico remoto.

Comprobaciones finales: sintaxis correcta de 111 archivos PHP, sintaxis del supervisor Python, estilo de los cuatro archivos intervenidos, exclusión de credenciales privadas y respuestas HTTP 200 en /login y /p2.
