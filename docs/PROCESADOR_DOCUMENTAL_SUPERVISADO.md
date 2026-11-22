# Procesador documental supervisado — FICCT/UAGRM

Fecha: 22 de noviembre de 2026. Ampliación local de la preparación institucional. Se conserva NO_GO_INSTITUCIONAL y el contrato de 8 CU / 30 RF. No se despliega este refuerzo en el sitio académico remoto.

## Qué cambia

Cada fuente institucional nueva registra, en la misma transacción de carga, una solicitud persistente de análisis. PostgreSQL conserva tareas y reservas temporales; los intentos, resultados y eventos son evidencia inmutable. Una fuente pendiente, fallida, bloqueada, vencida o con bytes alterados permanece bloqueada.

El trabajador reserva primero la fuente y después la tarea. El índice parcial permite una sola tarea activa por fuente; SKIP LOCKED evita reclamar una fuente que está ocupada. El análisis de archivos ocurre fuera de esa transacción breve. Para registrar el resultado se exige que la reserva siga vigente y que su identificador secreto coincida. Una terminación tardía queda auditada y no produce un resultado limpio.

Al vencer una reserva, la recuperación registra un resultado de error para ese intento y programa otro, sin borrar ni modificar los intentos anteriores. Los errores tienen tres intentos como máximo por solicitud, con esperas de 30 y 60 segundos; una detección de archivo bloqueado no se reintenta automáticamente. Una nueva solicitud explícita queda registrada como una tarea distinta.

La política técnica está en config/document_processor.php: reserva de 120 segundos, máximo tres intentos, detección de análisis antiguos sin cola tras 300 segundos y limpieza de temporales terminados tras 3600 segundos. No son plazos universitarios. La reserva debe superar el tiempo máximo del escaneo más los diez segundos de consulta de versión; una configuración insuficiente impide reclamar trabajos.

## Operación

Ejecutar desde la raíz de una instalación configurada, con una cuenta de servicio limitada y almacenamiento privado. No ejecutar contra una base ajena ni publicar el directorio .local.

- Consultar: `php artisan ficct:scan-queue-status`.
- Conservar una solicitud para una fuente existente: `php artisan ficct:scan-enqueue ID`.
- Procesar una tarea disponible: `php artisan ficct:scan-worker --once`.
- Supervisar localmente: `python scripts/run_document_processor.py --name worker-1`.
- Detener de forma controlada: crear el archivo privado `.local/document-processor/worker-1.stop`. Para reiniciar, quitar únicamente ese marcador propio y volver a iniciar el mismo supervisor.
- Inspeccionar recuperación y limpieza sin cambios: `php artisan ficct:scan-maintain`.
- Aplicar recuperación y limpieza verificadas: `php artisan ficct:scan-maintain --apply`.

El supervisor impide dos instancias con el mismo nombre. Sus procesos tienen límites de cantidad y tiempo, reinicio con espera y un watchdog de 180 segundos; si debe interrumpir un trabajador, termina únicamente su árbol de procesos. Las reservas persistentes permiten la recuperación posterior. Sus registros privados rotan a dos MB y conservan un archivo anterior. El historial probatorio permanece en PostgreSQL y no se purga.

Cada lote de trabajo inspecciona temporales terminados. La limpieza elimina exclusivamente payload.bin de directorios job-ID conocidos, antiguos, con resultado registrado y sin reserva vigente. Exige rutas canónicas, ausencia de enlaces y archivos inesperados, y un bloqueo exclusivo del directorio. Conserva el marcador vacío .lock y el directorio para no cambiar el objeto de bloqueo frente a otro proceso. Los temporales antiguos con formato desconocido se reportan y se dejan intactos. Nunca se eliminan originales, expedientes, publicaciones ni evidencia de análisis.

Los análisis antiguos iniciados fuera de la cola se recuperan solamente si no tienen resultado ni eventos de cola y su área de trabajo no está bloqueada. Las fuentes heredadas que no tengan ningún intento necesitan una solicitud explícita con scan-enqueue; la migración no las libera ni las marca limpias.

No existe un botón web para forzar un resultado limpio. El comando anterior scan-institutional-source utiliza ahora esta misma cola. Si ya existe una reserva o la tarea espera un reintento, no la duplica ni salta esa espera.

## Configuración del antivirus y límites

Se mantienen las variables FICCT_CLAMSCAN_BINARY y FICCT_CLAMSCAN_DATABASE y la validación de firmas del adaptador anterior. Sin un motor configurado o ante un error del motor, los intentos fallan conservadoramente y el archivo sigue bloqueado. El paquete no incluye motores, firmas, credenciales ni dependencias instaladas.

El supervisor se entrega como herramienta ejecutable. No se ha instalado como servicio de Windows, tarea de inicio automático ni servicio institucional. Tampoco se ha dejado un trabajador permanente funcionando contra la base operativa con un antivirus sin configurar. La política de disponibilidad, monitorización y cuenta de servicio debe acordarse con la infraestructura real.

Este alcance protege las fuentes de preparación institucional. La extensión a documentos académicos y personales de los postulantes sigue pendiente y requiere su propia intervención y pruebas. Las condiciones institucionales y la autorización oficial siguen sin aprobarse. EICAR continúa NOT_VERIFIED; no se desactivó la protección del equipo para obtener esa evidencia.

## Validación ejecutada

- PHPUnit sobre PostgreSQL: 227 pruebas y 1332 aserciones, sin errores ni fallos. Se conservan los 213 identificadores anteriores y se agregan P01–P14.
- Nuevos escenarios: encolado atómico, reserva única, reintentos y agotamiento, detección sin reintento automático, rechazo de resultados tardíos, mantenimiento simulado, temporales ocupados y desconocidos, recuperación de intentos antiguos, eventos inmutables y configuración de reserva inválida.
- Ensayo aislado: trabajador interrumpido de forma real tras crear su copia temporal; un segundo proceso no obtiene la reserva vigente; recuperación por vencimiento; reintento con ClamAV real; resultado tardío rechazado; original conservado y temporal abandonado eliminado mediante mantenimiento explícito.
- Supervisor real: procesamiento de solicitud persistida, parada mediante marcador y reinicio del mismo supervisor sin repetir reserva.
- Restauración independiente: 49 tablas idénticas, 15 triggers y 39 archivos privados idénticos, incluyendo el PDF sintético y los marcadores vacíos de trabajo. No son 39 documentos originales.
- Durante ese ensayo, las 47 tablas y los 35 archivos de la base operativa anterior permanecieron idénticos. La actualización local posterior agrega dos tablas y un trigger y conserva los datos de negocio anteriores.

La secuencia anonimizada con tiempos, eventos y decisiones está en evidence/document-processor/secuencia-eventos.json. La pausa del primer análisis es inyección de fallo mediante un doble, identificada como tal; el reintento sí usa el motor ClamAV real. Es una prueba de consistencia y recuperación, no un ensayo de carga ni una aceptación integral de seguridad.

Los resultados React, navegación visual, cuatro casos previos del adaptador y validaciones institucionales anteriores conservan su fecha y alcance histórico. No se presentan como nuevas ejecuciones de esta fase. No se modifican pantallas, rúbricas, calificaciones, selección, permisos académicos ni reglas normativas.

La copia independiente preparada para entrega volvió a ejecutar las 227 pruebas y 1332 aserciones sin fallos. Su XML es la evidencia actual indicada en evidence/current-validation.json. La sintaxis de 111 archivos PHP, la sintaxis del supervisor Python, el estilo de los seis archivos revisados y la exclusión de credenciales privadas también pasaron.
