# Revisión de roles, documentos y recorridos — 18/11/2026

## Conclusión y alcance

La revisión anterior comprobaba un recorrido limitado. No alcanzaba para afirmar que cada pantalla y cada acción correspondían a su rol. En esta continuación se contrastan permisos del servidor, menús, formularios, descargas y ayuda contra el contrato y las fuentes locales; se corrigen los hallazgos reproducibles. Se mantiene DEMO 0.3 y se conservan los históricos.

La referencia principal de responsabilidades es RF-01 y CU1 del contrato canónico, junto con «Actores y roles preliminares», página 5 de `Analisis_Actualizado_FICCT.pdf`. Se cotejan nuevamente los hashes de los cuatro PDF y tres fotografías con `INVENTARIO_FUENTES.json`. Los ocho CU y treinta RF no se redefinen.

## Responsabilidades y separación

| Perfil/responsabilidad | Datos y acciones que corresponden | Acciones que no concede el perfil por sí solo |
|---|---|---|
| Postulante | Convocatorias publicadas, sus postulaciones y originales, envío y seguimiento, subsanación autorizada, resultados propios y solicitudes de participación | Expedientes ajenos, revisar/habilitarse, calificarse, aprobar selección |
| Revisor por convocatoria | Expedientes y documentos del ámbito autorizado, criterios, habilitación, registro del respaldo CPD y mérito, subsanación | Notas de examen por otro docente, publicación de seleccionados |
| Evaluador por convocatoria y oferta | Postulantes habilitados y áreas asignadas, notas propias y sus rectificaciones documentadas | Archivos de identidad/académicos, revisión de requisitos, habilitación y publicación |
| Autoridad por convocatoria | Configuración, etapas, responsables, calificaciones y consolidado, ranking, desempates y decisiones/publicaciones DEMO | Acceso automático a archivos privados o ingresar notas como otro evaluador |
| Administrador técnico | Cuentas, perfiles, responsabilidades por convocatoria y vigencia, tipos de auxiliares | Crear convocatoria académica, acceder a originales privados, evaluar o publicar por el solo perfil técnico |

«Personal/docente» es un perfil base; el permiso vigente determina si revisa o evalúa. Una cuenta puede combinar responsabilidades legítimas, conservando restricciones sobre su propio expediente. El sistema no deduce que todo Decano, Vicedecano o Director tenga todos los permisos. La estructura de Director de Carrera es propuesta en la fuente, no confirmada. CPD se mantiene como dependencia externa sin integración automática.

## Hallazgos y correcciones

| Hallazgo | Corrección verificable |
|---|---|
| Imágenes sintéticas pequeñas confundidas con documentos presentables | Se conserva su original histórico, se informa formato/tamaño/dimensiones y se advierte cuando la imagen es pequeña. Se incorporan seis PDF A4 legibles y marcados MUESTRA, con prácticas nuevas; no se convierten en certificados oficiales. |
| Descarga sin contexto y tipo genérico | Pantalla Revisar archivo, apertura original en otra pestaña y descarga con MIME real y nombre original. Ambas entregan bytes idénticos al registro; un hash discrepante bloquea la entrega. |
| API oculta permitía crear convocatoria desde administrador técnico aunque el inicio ya no ofrecía la acción | Creación académica restringida a autoridad también en el servidor. Administrar cuentas no concede automáticamente esa función. |
| Un postulante con permiso de evaluador podía calificarse | Rechazo del servidor y ausencia del formulario de nota propia. |
| Una autoridad podía decidir una rectificación de su propia postulación si otra persona la solicitaba | Rechazo del servidor y ausencia del formulario de decisión propio. No basta con que solicitante y autoridad sean distintos. |
| Un revisor podía cerrar una ventana de subsanación propia, alterando su habilitación | Rechazo del servidor y formulario oculto al propietario. |
| Formularios de participación, decisiones o publicación visibles en etapas cerradas | Se limita su presentación al estado/mode permitido; los históricos siguen consultables. No se ofrece presentar otra solicitud de participación mientras haya una pendiente. |
| Menús personales confundidos con trabajo del funcionario | Mis postulaciones corresponde al perfil postulante; si alguien cambió de perfil y conserva expedientes, se identifican como Mis expedientes anteriores. Funcionarios sin expedientes no ven ese menú. |
| Nota confirmada repetida en formulario y tabla | Una vez confirmadas todas las áreas, se retira el panel de ingreso; la tabla conserva su consulta e historial. |
| Convocatorias antiguas antes de la recepción disponible | Las convocatorias efectivamente abiertas aparecen primero al postulante, sin alterar fechas. |
| Manual acumulaba instrucciones contradictorias sobre funciones ya implementadas | Manual vigente reescrito por rol, escenarios y pasos. Se agrega Qué puedo hacer dentro de la aplicación, con ayuda contextual y muestras para postulante/revisor. |

Se preservan de la corrección anterior el registro con sesión válida, continuar expediente existente, validación de documentos antes de enviar y controles de plazo/ámbito/límite de materias. Las nuevas restricciones son controles técnicos de separación e integridad; no se atribuyen a artículos institucionales no confirmados.

## Qué son los documentos de la práctica

Los PDF son material de prueba explícito. Incluyen carta, respaldo CPD, ficha de identidad, resumen académico, constancia y currículum. No contienen datos personales reales, sellos o firmas. No son las cuatro fuentes normativas ni sustituyen formularios oficiales.

Las descargas nunca transforman los originales del postulante. Si se sube un JPG, se descarga ese JPG; si se sube un PDF, se descarga ese PDF. La nueva vista permite identificar y abrir el archivo antes de descargarlo. Ni el formato PDF ni la presencia del archivo acreditan que un requisito se cumpla.

Se agregan escenarios independientes mediante `ficct:demo-documental`, solo en entorno local. Carla tiene un expediente enviado con seis PDF para revisión; Bruno tiene un borrador por completar. En el otro escenario Andrea está habilitada y lista para evaluación de Software. La segunda ejecución conserva los mismos registros: no renueva plazos ni reemplaza datos anteriores.

## Evidencia y límites

Pruebas nuevas: `DocumentExperienceTest::test_e01_*` a `test_e16_*`. Comprueban bytes/MIME, acceso por perfil y revocación, archivo ausente/alterado, imagen pequeña, muestras, ayuda contextual, creación académica, autoría de nota/decisión, escenarios idempotentes, orden de convocatorias e históricos sin acciones cerradas.

El recorrido de navegador revisa cada enlace del menú para administrador, autoridad, revisor y evaluador; registra un estudiante nuevo, carga PDF correspondientes a cada requisito, envía, revisa, habilita, registra méritos, califica, calcula y publica. También comprueba la descarga real y sus bytes, la apertura original y el acceso privado permitido/denegado para los otros perfiles. Se revisan accesibilidad automática A/AA seleccionada y ancho móvil en las pantallas capturadas. Los resultados ejecutados se adjuntan en `evidence/revision-integral-03/`.

Esto no equivale a una inspección humana de toda combinación posible de cuenta, etapa o dato, ni a una auditoría externa de seguridad/carga. Persisten los pendientes institucionales sobre vigencia, umbral aprobatorio, rúbricas, composición de exámenes, atribuciones formales y procedimientos excepcionales. No se declara el sistema autorizado para un concurso real.

## Resultado ejecutado

**164 pruebas y 803 aserciones**, sin errores ni fallos. Se conservan las 148 identidades de la entrega previa. El recorrido aislado tiene **20 comprobaciones y 144 respuestas observadas desde las páginas**; además se realizan peticiones privadas de comprobación de acceso y de las seis muestras. Catorce pantallas pasaron las reglas automáticas de accesibilidad A/AA seleccionadas y control de ancho móvil. Se consultaron los cinco perfiles en la instalación activa, incluyendo acceso PDF permitido al propietario/revisor y denegado a administrador/autoridad/evaluador.

La incorporación de prácticas conservó los registros anteriores de 31 tablas y los 23 archivos privados previos; agregó doce PDF en dos escenarios. Repetir el comando no produjo cambios. El ajuste final del panel de rectificación sin nota se comprueba en E16 y en la captura suplementaria de `ajuste-final-evaluador`; las capturas del recorrido amplio conservan el estado observado en esa ejecución.


## Revisión del portal React por responsabilidad — 23/11/2026

Esta intervención trabaja en `sistema`, conserva el backend académico y corrige diferencias entre las vistas Blade históricas y el portal React de Proyecto 2. No sustituye el proyecto ni empaqueta otra DEMO.

### Hallazgos comprobados y correcciones

| Hallazgo | Corrección |
|---|---|
| El inicio React distinguía `student` y `admin`, pero los revisores y evaluadores son cuentas base `staff`. Por eso recibían la misma bienvenida y las mismas listas genéricas. | El panel deriva sus espacios de las responsabilidades vigentes por convocatoria. Un revisor recibe su bandeja documental; un evaluador, sus evaluaciones y áreas; una autoridad, seguimiento y gestión; un administrador, control técnico. |
| El menú React tenía Mis postulaciones solo para estudiantes, pero el personal debía buscar sus expedientes dentro de Convocatorias. | Tres entradas nuevas, almacenadas en PostgreSQL: Revisión de expedientes, Mis evaluaciones y Seguimiento de expedientes. No conceden permisos académicos adicionales. |
| La lista genérica mezclaba expedientes propios y ajenos cuando una cuenta combinaba responsabilidades. | El API admite bandejas `own`, `reviewer`, `evaluator` y `authority`. La bandeja propia contiene únicamente registros de la cuenta; las de trabajo excluyen su propio expediente. La de evaluación exige además una asignación de área en esa oferta. |
| El enlace de rectificaciones se ofrecía a cualquier postulante que tuviera notas visibles, aunque el servidor rechazaba ese acceso. | La capacidad de consultar rectificaciones se calcula según los permisos reales del controlador de gobernanza. Consultar las propias notas no concede permiso para rectificarlas. |
| Calcular resultado se mostraba sin comprobar que estuvieran confirmadas todas las áreas. | El formulario requiere todas las áreas confirmadas o rectificadas, esquema y redondeo definidos, además de DOC-21, habilitación, mérito y etapa vigentes. El dominio continúa validando la operación al ejecutarla. |
| El revisor dejaba de ver el mérito CPD después del primer registro. | Puede consultar el valor registrado. Su modificación continúa exigiendo rectificación documentada. No recibe por ello las notas privadas de otros evaluadores. |
| El adaptador de rectificaciones serializaba el mérito incluso al evaluador sin responsabilidad de revisor o autoridad. | Se elimina ese campo de esa respuesta. El evaluador conserva únicamente el alcance de calificaciones e historial que ya autoriza el controlador. |
| El menú del personal presentaba el laboratorio de pagos como una tarea de selección. | Se conserva la práctica obligatoria de INF513 en Mi cuenta, con su explicación explícita de independencia respecto a la postulación. El postulante también conserva su acceso habitual. |
| Estadísticas mostraba al administrador técnico una sección de postulaciones con cero registros y al revisor métricas de examen que no podía consultar. | La presentación distingue estadísticas técnicas, documentales, de evaluación y de resultados según las responsabilidades, sin ampliar acceso a datos. |
| Las firmas locales de ClamAV eran del 21/11 y excedían la política de antigüedad de dos días. | Actualización oficial mediante FreshClam a daily 28192. Se conserva respaldo de las firmas anteriores y no se modifica la política ni se fabrican resultados limpios. La primera ejecución fallida del recorrido queda preservada. |

### Qué corresponde a cada perfil

- Postulante: consultar ofertas, preparar y enviar sus propios documentos, atender subsanaciones autorizadas y seguir sus resultados y manifestaciones DEMO.
- Revisor: cotejar expedientes de sus convocatorias, requisitos, habilitación, subsanaciones y registro inicial de méritos con respaldo CPD.
- Evaluador: consultar los postulantes de sus ofertas y las áreas que tiene asignadas; registrar borradores y confirmar sus notas; consultar el historial permitido y solicitar rectificaciones de sus notas cuando corresponda.
- Autoridad: configurar convocatorias bajo su responsabilidad, asignar evaluadores, conducir etapas, consultar resultados, tramitar decisiones y aprobar publicaciones DEMO con acta.
- Administrador técnico: cuentas, perfiles, vigencias, revocaciones y tipos de auxiliatura; su perfil no concede facultad de revisar expedientes privados, calificar o adjudicar.

Convocatorias, cuenta, búsqueda y apariencia pueden ser compartidas legítimamente. Una cuenta con varias responsabilidades recibe espacios separados; no se inventa una incompatibilidad institucional entre roles. El panel usa vistas resumidas y ofrece las bandejas completas con historial.

### Verificación de esta intervención

- PHPUnit completo: 283 pruebas / 1768 aserciones, sin errores ni fallos, en la base separada `ficct_test`.
- React: 46 pruebas aprobadas, incluyendo separación de cinco perfiles, responsabilidades combinadas, ausencia de asignación y enlaces de rectificación.
- Compilación Vite: aprobada; registro de fuentes y recursos actualizado.
- Evidencias nuevas en `evidence/role-review/`. El recorrido académico con ClamAV y el recorrido visual de cinco perfiles se documentan con su resultado real al concluir, no se deducen de los tests unitarios.

Los 8 CU y 30 RF no cambian. DOC-21, cuarentena, procesador compartido y las reglas de cálculo se conservan. Se mantiene `NO_GO_INSTITUCIONAL`. La aprobación institucional, la rúbrica y el umbral aprobatorio no se deducen de esta revisión de software. Las firmas necesitan mantenimiento periódico: actualizarlas hoy no demuestra que exista una actualización automática permanente.


### Cierre de los recorridos y aplicación local

El recorrido académico ejecutado el 23/11 en `evidence/academic-business/20261123124753/` terminó PASS: 20 comprobaciones, 52 solicitudes instrumentadas, ClamAV real con firmas oficiales y firma inocua adicional, subsanaciones versionadas, DOC-21, registro de notas, cálculo 68/100, publicación y manifestación DEMO, pagos simulados y alta de un usuario sintético. Se restauraron 50 tablas, 16 triggers y 42 archivos de la copia aislada. La primera ejecución de ese día, rechazada por firmas antiguas, sigue en `20261123124311/`.

El recorrido de perfiles terminó PASS en `evidence/role-review/browser/roles-browser.json`: 39 comprobaciones y 399 solicitudes observadas. Recorrió los enlaces del menú de postulante, revisor, evaluador, autoridad y administrador, cinco accesos directos no autorizados, navegación móvil a 390 px y una verificación axe A/AA seleccionada en cada bandeja. No hubo errores HTTP 5xx ni errores JavaScript. Dos intentos previos del comprobador tuvieron selectores ambiguos para cargas simultáneas y botones de cierre del menú; se corrigió el comprobador y se conservaron sus registros. No eran fallos de permisos de la aplicación.

La sincronización conservadora aplicó los cambios al runtime de `http://127.0.0.1:8116/p2`, con copia de reversión de código. La migración aplicada añade únicamente tres entradas y sus relaciones de navegación; no recrea tablas ni modifica reglas académicas. La comprobación de ingreso y menú de los cinco perfiles se repitió contra esa DEMO en `operative-profiles.json`. Se verificó allí una apertura PDF del revisor contra el SHA-256 original y el rechazo 403 para evaluador y autoridad (`operative-document-access.json`).

`preservation.json` comprueba el contrato canónico, los 35 archivos originales y los 16 archivos de dominio sin cambios. Las diferencias esperadas en tablas son navegación, registro de migración, telemetría de los recorridos y evidencia nueva de análisis documental. Los expedientes, las notas, las publicaciones, los responsables y los permisos académicos anteriores se conservan. Los temporales y cerrojos nuevos del trabajador se distinguen de los documentos originales.

Las capturas de los cinco perfiles corresponden a datos sintéticos del entorno aislado y al mismo código compilado que utiliza la DEMO local. El análisis automatizado de accesibilidad cubre las pantallas indicadas; no certifica toda combinación de formulario, dispositivo o tecnología asistiva. Esta intervención no genera un nuevo tarball ni publica cambios remotos. La entrega académica anterior conserva su propio hash y no debe confundirse con este código local actualizado.

El procesador local existente terminó los 34 análisis de los documentos históricos: 34 resultados clean, sin cambios en los 35 archivos originales. La evidencia es `evidence/role-review/operative-documents-scanned.json`; `final.json` reúne el cierre. La actualización de firmas no otorga autenticidad académica a las muestras ni sustituye la revisión de requisitos.


## Segunda revisión detallada por rol — 23/11/2026

Se continúa sobre el mismo proyecto y la misma DEMO. Los resultados anteriores se conservan como históricos; esta revisión detecta problemas adicionales que aquella batería no cubría.

| Perfil | Hallazgo reproducido | Corrección aplicada |
|---|---|---|
| Postulante | La respuesta JSON de participación incluía la instantánea completa de una publicación, con campos internos de otros aspirantes. | La API devuelve solo identificador, versión y fecha de la adjudicación. La publicación histórica en base no se modifica. D01 reproduce la filtración antes del arreglo. |
| Postulante | El historial mostraba la solicitud, pero omitía el fundamento de la decisión ya conservada por el servidor. | Se presentan decisión, motivo, responsable y fecha del registro autorizado al interesado. |
| Revisor | El formulario volvía a Observado aunque existían criterios guardados como Cumple. La habilitación aparecía disponible sin reunir sus condiciones. | Se recupera cada criterio guardado; se requiere una decisión explícita. Habilitar se ofrece con revisión completa y documentos vigentes; la inhabilitación motivada conserva sus restricciones del servidor. |
| Evaluador | Tras reasignar un área, el docente entrante no veía la nota confirmada por el anterior, porque se filtraba por autor de la nota en vez de responsable actual del área. | La lectura sigue las áreas actualmente asignadas, conserva la autoría original y no ofrece registrar otra nota confirmada. El cambio sigue exigiendo rectificación. El anterior responsable pierde el acceso que ya no le corresponde. D04 reproduce la ausencia de nota antes del arreglo. |
| Evaluador y revisor | La rectificación de méritos permitía introducir hasta 100 en el cliente, aunque el máximo del dominio es 40. | El formulario distingue mérito /40 y examen /100; las decisiones de aprobación requieren una selección explícita. |
| Autoridad | Se ofrecía asignar un área ya cubierta o reasignarla a la misma persona; el servidor rechazaba esas acciones. | Se ofrecen áreas libres y responsables alternativos vigentes. Se muestra el nombre disponible del responsable y se conserva el historial de reasignación. |
| Administrador | Renovar un permiso mostraba fechas vacías y podía eliminar su vigencia por omisión. También se ofrecían acciones sobre el propio administrador que el servidor rechaza. | Se precargan inicio y vencimiento en hora de Bolivia, incluidos segundos. Se retiran esas acciones sobre la propia cuenta y se explica la intervención de otro administrador. |

El backend mantiene las mismas facultades: administrar cuentas no habilita revisar documentos o calificar; revisar documentos no concede evaluar áreas; evaluar exige asignación vigente; aprobar publicaciones corresponde a la autoridad. Los espacios comunes de convocatorias, cuenta y apariencia siguen siendo legítimos para varios perfiles. No se inventan incompatibilidades entre responsabilidades acumuladas.

### Ejecuciones de esta segunda revisión

- PHPUnit: **287 pruebas y 1797 aserciones**, cero errores y cero fallos, en `ficct_test` separado de la base operativa. Cuatro regresiones nuevas de API comprueban privacidad, condiciones de habilitación, respuesta sobre solicitudes propias y reasignación de notas.
- React: **53 pruebas aprobadas**. Siete regresiones nuevas cubren fechas de permisos, opciones de asignación, criterios guardados, habilitación explícita, límites de rectificación, motivos de decisión y autoría de notas tras reasignar.
- Compilación: PASS; fuentes y recursos registrados en `public/build/source-hashes.json`.
- Recorrido de cinco perfiles: **39 comprobaciones / 398 respuestas observadas**, sin errores JavaScript ni respuestas 5xx. Se verifican menús, rechazo de rutas ajenas, navegación móvil a 390 px y reglas axe A/AA seleccionadas en cinco bandejas. Capturas y registro en `evidence/role-review-deep/browser/`.
- Recorrido académico: `evidence/academic-business/20261123132351/`, **20 comprobaciones / 52 solicitudes instrumentadas**. Incluye documentos, subsanaciones, ClamAV real con firma adicional inocua de prueba, DOC-21, méritos, notas, resultado 68/100, publicación y manifestación DEMO, pagos simulados y creación de usuario desde la interfaz.
- Restauración de la copia aislada: **50 tablas / 16 triggers / 76 archivos privados**. Los 76 incluyen documentos y archivos de trabajo del procesador; no equivalen a 76 documentos académicos. Se preservaron las 50 tablas y 69 archivos operativos durante ese recorrido, según su registro; los originales documentales se verifican separadamente por hash.

Los nuevos registros están en `evidence/role-review-deep/`. `changed-files.json` enumera los archivos de aplicación y pruebas cambiados respecto al respaldo previo de esta intervención. La sincronización local usa el mecanismo existente, con respaldo reversible; no ejecuta migraciones ni cambia la base de datos. La comprobación final de la instalación activa y su conservación se registra en `operative-profiles.json`, `preservation.json` y `final.json` una vez terminada.

Esta batería no certifica ausencia de cualquier defecto, accesibilidad universal completa, carga, aceptación antivirus EICAR ni autorización institucional. No se publican cambios remotos ni se genera otra aplicación. Se conservan los 8 CU, 30 RF, DOC-21 y `NO_GO_INSTITUCIONAL`.


### Cierre de la segunda revisión en la DEMO activa

La comprobación final terminó PASS: los cinco perfiles ingresaron a la instalación existente de `http://127.0.0.1:8116/p2`; el postulante recibe únicamente referencias mínimas de sus adjudicaciones, sin instantáneas de ranking. La compilación del runtime coincide con las fuentes actuales y la sincronización vuelve a informar MATCH en sus 186 archivos gestionados.

Se compararon las 50 tablas con el respaldo previo: solo cambian `access_events` y `audit_events` por los ingresos y salidas de comprobación. Los 35 archivos originales mantienen su SHA-256; los 16 archivos de dominio y el contrato canónico permanecen idénticos. No se aplicaron migraciones en esta segunda revisión. El respaldo reversible de código está en `.local/runtime-sync/20261123231943460321`.

Al retomar la sesión, el servidor y el procesador anteriores estaban detenidos. PostgreSQL registró una interrupción previa y completó su recuperación ordinaria antes de abrir la aplicación; no se forzó una reparación ni se recreó la base. Se reanudó el supervisor `fase2-local` con su misma configuración. El estado de cola comprobado tenía 34 tareas completadas, cero trabajos listos o fallidos y salud `ok`. Esto no constituye aceptación de un servicio Windows permanente.

El cierre consolidado está en `evidence/role-review-deep/final.json`. Los hallazgos concretos descritos quedan corregidos y probados dentro del alcance indicado; permanecen las limitaciones y pendientes institucionales.


## Recorrido documental completo y descarga explícita — 23/11/2026

Se atiende la comprobación de carga, lectura por el receptor autorizado, observación, subsanación y descarga. No se elimina el bloqueo institucional: `official_enabled = false` impide activar concursos oficiales sin las aprobaciones pendientes; no impide estos procesos funcionales en DEMO.

### Defectos de presentación encontrados

1. El portal React solo ofrecía Abrir para el original. Ahora separa **Abrir**, **Descargar original** y **Ver detalles**. La nueva ruta de descarga utiliza el mismo control de acceso, cuarentena, vigencia e integridad que la vista previa. Se preserva también el nombre del archivo en la variante ASCII de Content-Disposition.
2. El portal omitía el motivo guardado de la revisión por criterio. Ahora el postulante y los perfiles autorizados ven **Observaciones y fundamentos de revisión**, con criterio, decisión, motivo, responsable y fecha. Un evaluador no recibe esos datos privados.
3. Los detalles que ya existían en la vista histórica no estaban disponibles en React. Se agregan formato real, tamaño original, dimensiones cuando es imagen y advertencia para imágenes menores de 100 píxeles en alguno de sus lados. El archivo no se redimensiona, convierte ni sustituye. La advertencia no afirma que una imagen mayor sea necesariamente legible.

### Responsabilidades y lectura

El postulante sube sus propios archivos. El revisor vigente de la convocatoria puede abrirlos, leer su contenido y descargarlos para cotejarlos. Registra Cumple, Observado o No cumple y un motivo; una subsanación autorizada conserva las versiones y exige nueva revisión. La condición de autoridad, evaluador o administrador técnico no concede por sí sola descarga del expediente privado. Se conservan las responsabilidades combinadas que ya admite el sistema.

No existe aquí una afirmación de OCR ni de autenticación automática de documentos. ClamAV analiza amenazas; la disponibilidad, huella y vigencia son controles técnicos. La evaluación del contenido y su correspondencia con el requisito corresponde al revisor. Que un PDF pase un análisis no acredita su validez académica.

### Pruebas de esta intervención

PHPUnit completo: **291 pruebas / 1832 aserciones**, sin errores ni fallos. React: **55 pruebas**, todas aprobadas. Las nuevas E17–E20 comprueban descarga original para propietario y revisor, metadatos sin rutas internas, rechazo para otros perfiles y permisos revocados, archivo alterado o ausente, análisis pendiente y conservación de una imagen de 1×1 sin redimensionarla. React comprueba enlaces diferenciados, advertencia y motivos de observación visibles al postulante sin acciones de revisor.

Las rutas adicionales están inventariadas en `docs/INF513_P2_OPENAPI.json`. Las pruebas y el cierre del recorrido real se conservan en `evidence/document-usability/`, junto con la referencia a la copia aislada usada para cargar y subsanar archivos desde el navegador. El respaldo de código local está en `.local/runtime-sync/20261123233635687659`; no se aplican migraciones ni se recrea la base.


### Resultado del recorrido documental y aplicación local

El recorrido real de `evidence/academic-business/20261123193222/` terminó PASS: **21 comprobaciones / 53 solicitudes instrumentadas**. El navegador del revisor descargó el mismo PDF cargado por el postulante: mismo nombre y bytes exactos. Se completaron observación, dos subsanaciones, conservación de versiones, nueva revisión, DOC-21 y los pasos posteriores de evaluación y publicación. Se restauraron 50 tablas, 16 triggers y 76 archivos de la copia aislada, incluidos archivos de trabajo del procesador. Durante ese recorrido no cambiaron las 50 tablas ni los 69 archivos de la instalación operativa.

El recorrido de cinco perfiles en `evidence/document-usability/browser/` terminó PASS: **41 comprobaciones / 419 respuestas**, cero errores JavaScript. Incluye presentación de metadatos, fundamentos de revisión y acceso a la descarga para propietario y revisor, así como comprobaciones móviles de sus documentos. El panel final integra los motivos en la revisión por criterio para evitar listas duplicadas al interesado.

En la instalación activa, el revisor descargó un original de 3775 bytes: la huella coincide con el archivo original preservado antes de esta revisión. Administrador, autoridad y evaluador recibieron 403 tanto en descarga como en metadatos. Los cinco perfiles ingresaron correctamente. La comprobación de 50 tablas solo detecta nuevos ingresos y salidas en `access_events` y `audit_events`; se conservan los 35 archivos originales, los 16 archivos de dominio y el contrato canónico. La matriz mantiene los 30 RF y sus 127 métodos de prueba referenciados aparecen en la nueva ejecución. Registros: `operative-profiles.json`, `preservation.json`, `contract.json` y `final.json`.

La sincronización final de la presentación tiene respaldo adicional en `.local/runtime-sync/20261123234211499169`. No se publica al servidor remoto, no se alteran decisiones institucionales y no se reemplazan imágenes históricas por documentos inventados. Los originales se entregan tal como fueron cargados; una imagen pequeña se advierte para que el revisor valore su legibilidad.

La vista documental final, con los fundamentos integrados sin duplicar el panel, pasó una comprobación adicional: metadatos visibles, un único encabezado de observaciones, sin desborde a 390 px y sin infracciones axe A/AA seleccionadas. Capturas y resultado en `evidence/document-usability/final-document-screen/`. La compilación del runtime coincide con las fuentes actuales.
