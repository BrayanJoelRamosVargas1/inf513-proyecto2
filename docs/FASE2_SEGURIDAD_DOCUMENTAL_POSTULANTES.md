# Fase 2 — Seguridad documental de postulantes FICCT/UAGRM

Fecha: 22 de noviembre de 2026. Implementación local sobre el procesador existente. Se conserva NO_GO_INSTITUCIONAL. No se crean criterios normativos ni se despliega esta ampliación en el servidor remoto.

## Funcionamiento para estudiantes y revisores

Las cargas iniciales y las subsanaciones guardan cada versión en almacenamiento privado y registran su tarea de análisis en la misma transacción. Las nuevas cargas usan applicant-quarantine; los archivos anteriores permanecen en su ubicación original. Las versiones documentales son inmutables en PostgreSQL: no se sobrescriben ni eliminan para corregir un expediente.

El estudiante puede enviar un expediente completo mientras los archivos esperan análisis. Esa recepción conserva el expediente y no aprueba sus documentos. La interfaz identifica los estados pendiente, bloqueado, error, vencido y válido, y ofrece actualizar su estado. No ofrece abrir un archivo sin un resultado limpio vigente.

La descarga, vista previa y aprobación de documentos exigen análisis válido y comprobación de los bytes actuales mediante SHA-256. Un archivo alterado o ausente después del análisis no puede respaldar una nueva aprobación. Las verificaciones previas y posteriores a la lectura deben corresponder al mismo intento de análisis.

Marcar un documento como Cumple requiere comprobar la versión vigente. La habilitación vuelve a comprobar el conjunto obligatorio, aunque ya existan revisiones positivas. Las revisiones y habilitaciones nuevas conservan en auditoría las versiones, huellas, identificadores de análisis y hora de comprobación. Analizar un archivo nunca habilita automáticamente al estudiante.

Las observaciones siguen disponibles: un revisor puede registrar que un documento necesita corrección sin abrir un archivo bloqueado. Cada subsanación conserva las versiones anteriores y exige un análisis independiente de la versión corregida antes de aprobarla. Un análisis limpio de una versión anterior no se reutiliza para otra versión.

## Privacidad y permisos

Solo el propietario activo y el revisor con permiso vigente en la convocatoria pueden acceder al contenido. Ser autoridad, evaluador o administrador no concede acceso a los archivos académicos y personales. Un resultado limpio tampoco restablece permisos revocados.

La API mantiene listas explícitas de campos. El resumen visible de seguridad contiene únicamente state, download_allowed y finished_at. No entrega rutas privadas, huellas, tokens de reserva, identificadores de trabajos ni resultados internos del motor en las listas documentales. No existe una ruta web que permita forzar un resultado limpio.

## Un procesador compartido

DocumentTarget identifica de forma explícita una fuente institucional o una versión documental de postulante. Ambas usan DocumentProcessor, DocumentSafety, el adaptador ClamAV, las reservas, los reintentos, PROC-01, los registros inmutables y el limpiador existentes. No hay una segunda implementación de cola ni un segundo supervisor.

Se conservan los nombres históricos institutional_scan_jobs, institutional_scan_tasks, institutional_scan_results e institutional_scan_events. Las tablas correspondientes agregan document_id, manteniendo source_id para las fuentes institucionales. Una restricción exige exactamente un destino por registro y un índice impide más de una tarea activa por versión documental. Los identificadores numéricos coincidentes de una fuente y un documento no mezclan sus análisis.

La migración amplía el esquema y agrega un trigger de inmutabilidad documental. No mueve archivos, no marca documentos anteriores como limpios y no reescribe publicaciones, calificaciones, revisiones ni snapshots históricos. El rollback se niega a descartar evidencia de análisis de postulantes existente.

## Operación local

La DEMO está disponible en http://127.0.0.1:8116/p2. Se configuró el ClamAV local con sus firmas oficiales y se inició un supervisor local para las nuevas cargas. La firma adicional de prueba se utilizó solamente en el laboratorio aislado; no está configurada en la DEMO operativa.

El trabajador local no es un servicio instalado en Windows ni una garantía de inicio después de reiniciar el equipo. Su proceso y un evento reciente fueron comprobados directamente; el diagnóstico de cola por sí solo no demuestra disponibilidad del trabajador.

Para una instalación nueva: instalar las dependencias desde sus archivos de bloqueo, configurar una base exclusiva y almacenamiento privado, generar la clave de aplicación solo al instalar, ejecutar las migraciones sin borrar datos y compilar con npm run build. Configurar FICCT_CLAMSCAN_BINARY y FICCT_CLAMSCAN_DATABASE con un motor y firmas aceptados. No incluir credenciales, motores, firmas ni documentos privados en el repositorio público.

Comandos desde la raíz de la instalación:

- Consultar la cola compartida: `php artisan ficct:scan-queue-status --check`.
- Procesar una tarea disponible: `php artisan ficct:scan-worker --once`.
- Supervisar nuevas cargas: `python scripts/run_document_processor.py --name fase2-local --idle-seconds 5`.
- Detener ese supervisor: crear `.local/document-processor/fase2-local.stop`. Para reiniciar, revisar que se haya detenido, quitar solo ese marcador y volver a ejecutar el comando.
- Solicitar análisis de una versión anterior: `php artisan ficct:scan-enqueue-document ID`.
- Inspeccionar recuperación y limpieza: `php artisan ficct:scan-maintain`. Para aplicar los controles: agregar `--apply`.

Los documentos heredados sin análisis permanecen bloqueados hasta encolarlos y analizarlos. No se analizaron ni liberaron en masa los documentos históricos durante esta actualización. Las nuevas cargas y subsanaciones se encolan automáticamente. Una cola vacía no acredita que todos los archivos heredados hayan sido analizados.

Los generadores de recorridos sintéticos también pasan por el procesador y ya no pueden crear nuevas revisiones positivas sin un análisis válido. Las pruebas históricas usan un analizador sintético explícito para sus fixtures; no se desactiva la cuarentena ni se cambia su política en el código de producción.

## Verificación ejecutada

- PostgreSQL: 255 pruebas y 1496 aserciones sin errores ni fallos. Conserva los 235 identificadores anteriores y agrega D01–D20.
- Copia independiente de entrega: ejecución completa de las mismas 255 pruebas y 1496 aserciones. El XML actual está indicado en evidence/current-validation.json.
- Interfaz: 28 pruebas React aprobadas; seis nuevas comprueban los enlaces y estados documentales. Compilación de producción correcta.
- Recorrido real en copia aislada: carga desde la interfaz, envío pendiente, bloqueo de descarga y revisión, análisis limpio, control de acceso por roles, dos subsanaciones, conservación de las tres versiones y habilitación mediante revisión autorizada.
- ClamAV real: dos versiones limpias y una versión detectada mediante una firma adicional inocua de prueba. El archivo detectado se conserva. Es evidencia acotada del motor y la integración; EICAR continúa NOT_VERIFIED.
- El registro contiene 54 solicitudes API explícitas y diez comprobaciones del recorrido. No representa el total de todas las peticiones de recursos y navegación del navegador.
- Inspección de capturas de escritorio y móvil, sin errores JavaScript, sin desbordamiento a 320 px y sin hallazgos axe en la página y reglas comprobadas. No es una certificación integral de accesibilidad.
- Concurrencia: dos procesos PHP independientes; PostgreSQL confirmó que el nuevo análisis espera al bloqueo del documento mientras se comprueban sus bytes. Al finalizar la comprobación, el nuevo análisis devuelve la versión a pendiente.
- Restauración: 49 tablas y 16 triggers; 41 archivos privados idénticos en la copia restaurada, incluyendo los tres documentos sintéticos nuevos y marcadores vacíos de trabajo.
- Preservación en el laboratorio: los 34 documentos, diez snapshots, diez calificaciones y cinco publicaciones anteriores conservaron sus registros originales. Las 49 tablas operativas y los 35 archivos privados se mantuvieron intactos durante el ensayo aislado.
- Actualización local posterior: 48 tablas anteriores idénticas, registro de migraciones ampliado y 35 archivos privados idénticos. No se rehízo la base.

Scores.php, config/ficct.php y el contrato canónico de 8 CU y 30 RF mantienen sus bytes. En Selection.php cambian exclusivamente upload, review y eligibility para integrar la seguridad documental; los demás métodos, incluidos los de cálculo y ranking, conservan su contenido. También se adaptan las cargas y revisiones de subsanación, los controladores, las pantallas y los fixtures de prueba. Se conserva intacto el ZIP anterior.

## Pendientes y límite institucional

Se mantiene NO_GO_INSTITUCIONAL y siguen pendientes las condiciones de aceptación institucional. No se modificaron umbrales, rúbricas, desempates ni normativa. La operación permanente, el monitoreo externo, la disponibilidad sostenida, la recuperación automática del servidor, la carga representativa y la aceptación antimalware independiente requieren su siguiente validación. EICAR no se presenta como verificado.

Comprobaciones finales: sintaxis de 115 archivos PHP, estilo de once archivos intervenidos, sintaxis del supervisor Python y exclusión de credenciales privadas correctos. La fuente de trabajo coincide con el código de la copia probada.
