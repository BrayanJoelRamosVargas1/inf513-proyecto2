# Cierre de reproducibilidad y accesibilidad — DEMO 0.3

Fecha: 11/11/2026. Continúa DEMO 0.3. Se cierran REP-01, A11Y-01 y A11Y-02 en el alcance comprobado, conservando los ocho CU y treinta RF canónicos. No se inicia DEMO 0.4 ni se incorporan decisiones institucionales pendientes.

## Qué cambió

- REP-01: `tests/Unit/.gitkeep` conserva la carpeta referenciada por PHPUnit al extraer el ZIP. El empaquetador exige el marcador y rechaza sobrescribir un ZIP existente antes de escribir el manifiesto.
- A11Y-01: se oscureció el color secundario común a `#58697b`; encabezados de tabla, pie de página y pasos usan ese color. Se mantienen tamaños de texto, estados y estructura.
- A11Y-02: la navegación móvil permite envolver sus enlaces en varias líneas mediante `flex-wrap: wrap`.
- Se actualizaron README y estado para enlazar este cierre y reflejar la recuperación de fuentes ya realizada. El informe normativo anterior y la adenda conservan su historia.

Los cambios funcionales se limitan a presentación y empaquetado. App, controladores, permisos, migraciones, servicios de dominio, vistas de negocio, casos de prueba existentes y contrato se cotejaron por hash contra el cierre visual: idénticos. Detalle en `../evidence/cierre-accesibilidad-03/codigo-protegido.json`.

## Comprobaciones nuevas

| Control | Resultado |
|---|---|
| PHPUnit en PostgreSQL aislado | 110 pruebas, 415 aserciones, cero errores o fallos |
| Identidades de pruebas del cierre visual | 110/110 conservadas |
| Recorrido HTTP real | 21 comprobaciones y 104 solicitudes, estados esperados |
| axe-core 4.14.0, seis pantallas | 54 ocurrencias previas de contraste → cero avisos en la muestra |
| Reflujo a 320 píxeles | Cuatro pantallas desbordadas → ninguna en la muestra |
| Teclado | Enlace de salto al contenido operativo en las seis pantallas; diez movimientos de foco registrados por pantalla |
| Capturas reales nuevas | 12: escritorio 1280×900 y móvil 320×900 |
| ZIP candidato extraído en carpeta nueva | Unit presente sin crearlo a mano; 110 pruebas descubribles desde esa configuración |
| Protección de entregas | Rechazo de sobrescritura comprobado, ZIP y manifiesto intactos tras ese rechazo |

| Perfil / pantalla | Ocurrencias de contraste antes → avisos después | Ancho de documento a viewport 320, antes → después |
|---|---|---|
| invitado | 5 → 0 | 320 → 320 |
| admin | 14 → 0 | 359 → 320 |
| autoridad | 16 → 0 | 390 → 320 |
| revisor | 5 → 0 | 390 → 320 |
| evaluador | 10 → 0 | 390 → 320 |
| postulante | 4 → 0 | 320 → 320 |

Evidencia nueva: [cierre](../evidence/cierre-accesibilidad-03/cierre.json), [comparación](../evidence/cierre-accesibilidad-03/comparacion-antes-despues.json), [XML PHPUnit](../evidence/cierre-accesibilidad-03/regresion/phpunit.xml), [HTTP](../evidence/cierre-accesibilidad-03/http/recorrido-http.json), [axe y teclado](../evidence/cierre-accesibilidad-03/accesibilidad.json), [reproducibilidad](../evidence/cierre-accesibilidad-03/reproducibilidad-paquete.json) y [conservación](../evidence/cierre-accesibilidad-03/conservacion.json). El archivo de reproducibilidad identifica el candidato; la verificación del paquete final se entrega además por separado y puede repetirse con `scripts/verify_accessibility_release.py`.

## Ejecución y preservación

Se reutilizaron exclusivamente el cluster y las dependencias de la validación aislada anterior; no se reinstaló Composer, porque el lock no cambió. Se copió el CSS corregido a esa instalación, se añadió el marcador y se repitieron PHPUnit y el recorrido con una base y almacenamiento HTTP nuevos. El candidato se extrajo en otra carpeta vacía y se descubrieron las 110 pruebas usando el bootstrap de dependencias de ese entorno. Descubrir pruebas no se presenta como ejecutarlas; la ejecución real está en el XML nuevo.

La DEMO original permaneció detenida. Sus archivos de cluster y sus 18 archivos privados conservaron sus hashes, al igual que las evidencias anteriores y los seis ZIP existentes al comenzar. No se consultaron sus tablas por SQL ni se modificaron sus publicaciones o calificaciones. El servidor y el cluster de ensayo se detuvieron al cerrar la validación.

Las once carreras de concurrencia, restauraciones, sondeos de seguridad y carga de la validación anterior se conservan como evidencia histórica, sin atribuirles una ejecución nueva en este cierre. No se cambiaron componentes de negocio que justifiquen repetirlos por esta corrección visual.

## Límites y pendientes

La muestra cubre login, usuarios del administrador, resultados de autoridad, expediente del revisor, expediente del evaluador y adjudicaciones del postulante. No certifica todas las rutas/estados, lector de pantalla, tareas completas solo con teclado, zoom al 400 por ciento ni conformidad WCAG integral. Sigue pendiente el pentest y endurecimiento de un despliegue representativo. OBS-SEC-01 continúa siendo una observación de endurecimiento de CSP; no se modificó esa política.

Las aclaraciones institucionales —umbral aprobatorio, rúbricas CPD, composición de examen, autoridades, plazos y actos oficiales— siguen pendientes. La consulta preparada no se envió. Este cierre técnico no autoriza uso institucional ni declara terminado todo el sistema.
