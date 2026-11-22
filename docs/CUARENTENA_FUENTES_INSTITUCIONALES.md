# Refuerzo de Fase 1: cuarentena de fuentes institucionales

Fecha local: 21/11/2026. Base: preparación institucional local 121f3dacb426b0a0b79cb894f2bcc02641197637. Esta ampliación no se desplegó al servidor docente ni habilita concursos reales.

## Qué se corrigió

Antes, una fuente PDF/JPG/PNG podía descargarse inmediatamente tras validar tipo, tamaño y huella. Ahora las fuentes institucionales nuevas y anteriores quedan lógicamente en cuarentena hasta disponer de un análisis válido. No se renombra ni modifica una fuente anterior ni su registro inmutable.

La descarga autorizada requiere el resultado limpio del análisis más reciente iniciado, vigente y realizado bajo la política actual. Un análisis posterior pendiente, bloqueado o con error impide reutilizar un resultado limpio antiguo. La finalización tardía de un trabajo anterior tampoco puede liberar la fuente. Los intentos y resultados se añaden a dos tablas con triggers contra UPDATE/DELETE.

La solicitud de revisión y su conclusión técnica también comprueban la seguridad actual de todas las fuentes. Una observación puede registrarse aunque una fuente siga bloqueada. Los snapshots y revisiones anteriores permanecen intactos; no se reinterpretan como certificaciones de seguridad.

La descarga devuelve exactamente los bytes cuya huella se comprobó, evitando transmitir después un archivo reemplazado en la misma ruta. El trabajador analiza una copia temporal privada, verifica el original antes y después y elimina la copia al terminar normalmente o por excepción. Una interrupción forzada del proceso puede requerir limpieza de temporales por el operador.

Esto se aplica a las fuentes del módulo institucional. No se ha extendido todavía al expediente académico de postulantes ni a todos los documentos heredados. No es un certificado de autenticidad, ausencia absoluta de malware ni acreditación institucional.

## Operación y configuración

El operador instala y mantiene ClamAV en un entorno autorizado y restringe el ejecutable y sus firmas a cuentas de confianza. Configurar FICCT_CLAMSCAN_BINARY con la ruta absoluta del ejecutable y FICCT_CLAMSCAN_DATABASE con la carpeta de firmas. No tomar estas rutas del navegador. Mantener las firmas mediante el procedimiento operativo aprobado; la aplicación no ejecuta actualizaciones ni descarga firmas.

Ejecutar php artisan ficct:scan-institutional-source ID desde la instalación correcta. El comando registra el intento y devuelve 0 solo ante un análisis limpio; devuelve 1 si queda bloqueado o con error. No tiene opción de aprobar manualmente ni existe una ruta web para marcar un archivo como limpio. Un trabajo interrumpido deja la fuente pendiente; otro intento crea un nuevo trabajo.

El adaptador usa argumentos separados, sin construir una orden de shell con datos del documento. Conserva los originales incluso ante detecciones. Aplica límites de tamaño y expansión, alerta ante contenido cifrado o límites excedidos y rechaza firmas demasiado antiguas. La falta del motor, firmas, un resultado completo o el vencimiento mantienen el bloqueo. Estas opciones y los códigos de salida se contrastaron con el [manual oficial de clamscan](https://github.com/Cisco-Talos/clamav/blob/main/docs/man/clamscan.1.in) y la [guía oficial de análisis](https://docs.clamav.net/manual/Usage/Scanning.html).

Política técnica inicial en config/document_safety.php: resultado válido durante 24 horas, firmas con antigüedad máxima de dos días y tiempo máximo de análisis de 60 segundos. Son parámetros de ingeniería sujetos a aceptación, no reglas universitarias. Un cambio en la política invalida el uso de los resultados previos y exige otro análisis. Las firmas y el motor deben gestionarse y validarse en infraestructura institucional.

No hay cola ni programación automática en esta entrega. El operador debe incorporar el comando a un proceso supervisado con límites y permisos adecuados. Los resultados no almacenan la salida bruta del motor ni rutas privadas. El registro del banner identifica el motor; no sustituye una verificación independiente de su instalación o de la procedencia de sus firmas.

## Evidencia real y límites de esta ejecución

- Regresión local: 209 pruebas Laravel / 1184 aserciones, sin fallos. Se conservan los 199 identificadores anteriores y se añaden Q01–Q10. Las pruebas de Fase 1 se adaptaron para analizar sus archivos con un doble de prueba explícito.
- React: 22 pruebas, incluida una nueva que verifica la cuarentena y ausencia del enlace de descarga.
- Navegador: seis comprobaciones en copia aislada, con PDF MUESTRA y comando real de operador sin analizador configurado. La descarga devolvió 423; el envío a revisión fue rechazado; no hubo ruta web de liberación ni errores JavaScript. Axe y pantalla de 320 píxeles pasaron en la pantalla examinada.
- Los casos limpio, detección, error y orden de finalización se prueban mediante dobles de prueba. No se utilizan dobles ni motores de aprobación ficticia en la instalación operativa.
- Este equipo no dispone de clamscan/clamdscan en PATH y Docker no tiene un motor activo. Por ello, el análisis real con ClamAV y firmas está **NOT_VERIFIED**. No se declara cerrada la aceptación antimalware.
- Restauración independiente: 47 tablas idénticas y 14 triggers recuperados. Otro ensayo restauró la copia del navegador con 36 archivos privados y un resultado de análisis fallido; conservó filas, huellas y estado sin convertirlo en aprobado.
- La regresión preservó 45 tablas y 35 archivos privados de la instalación previa. La migración añadió dos tablas y dos triggers, sin liberar archivos automáticamente. El recorrido de navegador conservó las 47 tablas operativas; sus cambios quedaron en una copia aislada.

Consultar evidence/institutional-quarantine/ para los resultados de esta ejecución. Las evidencias anteriores siguen siendo antecedentes, no se suman a la ejecución actual.

## Seguimiento de las seis condiciones de la revisión

| Condición | Estado real |
|---|---|
| Autoridades acreditadas, designación y vigencia | Pendiente de procedimiento y evidencia institucional |
| Identidad y elegibilidad estudiantil | Pendiente; no se conectó ni simuló acceso al CPD |
| Reglas de evaluación vinculadas a aprobación oficial | Pendiente; solo existen fuentes y revisión técnica |
| Actos de comisión, publicación y nombramiento | Pendiente; la publicación DEMO no se convirtió en nombramiento |
| Seguridad documental, recuperación, auditoría externa y carga | Parcial técnico: cuarentena y recuperación local verificadas; motor real, auditoría externa y carga representativa pendientes |
| Aceptación documentada antes de activación | Pendiente; NO_GO_INSTITUCIONAL y protecciones originales conservados |

**0 de 6 condiciones aceptadas institucionalmente.** Este seguimiento no es una certificación. Permanecen pendientes conservación protegida fuera de la base, auditoría centralizada, política de privacidad, autenticidad, pruebas representativas de concurrencia/carga e infraestructura autorizada.

## Instalación y recuperación

Antes de actualizar, respaldar base, archivos y configuración. Aplicar la migración con php artisan migrate --force en una copia aislada; no usar migrate:fresh sobre una instalación que deba conservarse. Reconstruir React y cachés siguiendo la guía base. La cuarentena de fuentes previas es un cambio deliberado: sin analizador operativo, la descarga seguirá bloqueada.

No ejecutar rollback de tablas para solucionar un fallo del analizador. Corregir su configuración y repetir el análisis, conservando intentos anteriores. Ni los hashes ni los triggers protegen por sí solos contra un administrador con privilegios suficientes: las copias externas y una revisión independiente siguen siendo necesarias.
