# Validación de participación desde el navegador — DEMO 0.3

Fecha: 15/11/2026, Bolivia. Resultado: **13 comprobaciones aprobadas**, ejecutadas ahora con Chromium y formularios reales; no es una revisión de registros anteriores.

## Qué se ejecutó

1. Cierre explícito de la ventana de subsanación vencida, conservando su registro.
2. Apertura de una nueva ventana DEMO por el revisor, con plazo futuro explícito de prueba.
3. Carga de una nueva versión por su propietaria, Carla Ejemplo.
4. Envío a revisión y desaparición del formulario de carga para esa versión enviada.
5. Revisión completa de documentos y requisitos por el revisor, con habilitación DEMO.
6. Denegación al evaluador del detalle y de una decisión POST, con token CSRF válido. Se probó el rechazo por permisos, no por falta de token.
7. Solicitud de retiro por la postulante, conservada como pendiente antes de la decisión.
8. Aprobación del retiro por otra autoridad.
9. Detección de múltiples adjudicaciones y registro de una manifestación de aceptación DEMO.
10. Solicitud separada de renuncia a una plaza adjudicada.
11. Aprobación de renuncia, identificación de la vacante retenida y conservación de la publicación v1.
12. Aprobación explícita de publicación v2, manteniendo v1 y sin adjudicar reemplazante.
13. Rechazo al acceso de otro postulante al expediente y al documento de la subsanación.

Se capturó una imagen por comprobación. Se inspeccionó visualmente el resultado de publicación v2 y retención de vacante. La lista exacta de formularios y respuestas está en recorrido-navegador.json.

## Evidencia técnica

85 respuestas HTTP registradas: 64 de estado 200, 16 redirecciones 302 y cinco rechazos 403 esperados. La cuenta incluye páginas, formularios y recursos del navegador, así como los dos sondeos de permiso realizados con el contexto autenticado del navegador; no equivale a 85 operaciones de negocio. No se registran contraseñas, tokens, cabeceras de sesión ni cuerpos de las solicitudes.

La copia inicial se obtuvo mediante respaldo y restauración en una base y almacenamiento independientes, comprobando igualdad de sus 31 tablas. Al terminar, las 31 tablas de la DEMO disponible permanecieron iguales. En la copia de prueba se conservaron íntegros por comparación de filas los registros previos de cinco publicaciones, diez notas, 22 documentos, ocho snapshots y una manifestación. El historial de documentos compara metadatos y hashes almacenados; esta ejecución no repite una auditoría binaria de todos los archivos históricos. La nueva carga y su enlace se ejercitan desde el navegador.

La prueba confirma que hubo una ventana vencida y otra revisada, un retiro aprobado y una renuncia con vacante retenida. La publicación nueva excluye a la postulante renunciante. No se asignó reemplazante; no había otro aspirante en la oferta C utilizada. El caso con suplente en la oferta A permanece entre los escenarios y pruebas históricos del paquete operativo.

Los controles permanecen idénticos a la entrega operativa: se verificaron los 431 hashes de su contenido local y el SHA-256 del ZIP. No se modificó código de aplicación, configuración normativa, migraciones, 8 CU ni 30 RF. No se volvió a ejecutar PHPUnit en esta continuación porque el código no cambió; las 119 pruebas y 489 aserciones pertenecen a la ejecución local anterior, conservada en el paquete operativo.

## Uso de la DEMO

La instancia principal vuelve a estar disponible en http://127.0.0.1:8116. El iniciador local es ABRIR_DEMO_FICCT_VALIDADA.ps1, en la carpeta del proyecto. Las cuentas ficticias están en el archivo privado indicado por ACCESO_DEMO_FICCT.md.

La ventana preparada en noviembre 11 ya venció: se conserva así en la DEMO principal. No se renovaron plazos ni se reescribieron registros para hacer aparentar vigencia. El cierre de la ventana y todo el nuevo recorrido se realizaron en una copia aislada, cuyo servidor temporal fue detenido al terminar. Para repetirlo, usar una nueva copia de los datos sintéticos, cerrar la ventana con el motivo de prueba y abrir otra con fecha futura explícita; no editar la fecha anterior directamente en la base.

## Repetición y límites

comprobadores-utilizados conserva el código exacto empleado. Está preparado para esta computadora: lee la configuración privada en sistema/.local/operacion-03/state.json, los accesos del runtime, los identificadores de escenarios de OPERACION_Y_RECUPERACION_FICCT_DEMO_0_3/escenarios-guiados.json y las dependencias locales de navegador/PostgreSQL. Esos archivos privados y dependencias no se distribuyen. Para repetir aquí, ejecutar el comprobador original sistema/.local/operacion-03/probar_participacion_ui.py con el Python utilizado en esta instalación. Crea una nueva base y carpeta con fecha, sin reemplazar la base de origen. Las copias incluidas documentan la ejecución; por su resolución de rutas no deben ejecutarse directamente desde el ZIP extraído en otra ubicación sin adaptar la configuración. No se afirma portabilidad de este comprobador ni independencia de infraestructura.

Hubo una primera ejecución de 12 pasos antes de añadir los sondeos POST y entre propietarios; se conserva en otra carpeta local. El resultado distribuido corresponde a la ejecución completa de 13 pasos, no a la suma de ambas.

No es un ensayo de carga, auditoría externa de seguridad ni revisión completa de accesibilidad. No acredita autorización institucional. Se mantiene pendiente la confirmación institucional de umbral aprobatorio, rúbricas, composición de exámenes, autoridades, plazos y aceptación/compatibilidades. La consulta institucional ya redactada sigue en VALIDACION_TECNICA_FICCT_DEMO_0_3/CONSULTA_INSTITUCIONAL_PARA_REVISION.md y no fue enviada. No se inventaron reglas ni se inició DEMO 0.4.

## Documentación histórica

El README y el estado incluidos en la entrega operativa aún encabezan su historial con 118 pruebas y refieren la instalación original en el puerto 8099. La entrega operativa posterior registra 119/489 y el puerto 8116 en docs/OPERACION_Y_RECUPERACION_DEMO_0_3.md. Es una referencia desactualizada que debe corregirse en el siguiente empaquetado; el ZIP operativo y sus hashes permanecen intactos. El contraste original de 0.3 también conserva una nota de fuentes pendientes superada por la adenda normativa: las siete fuentes ya fueron recuperadas y contrastadas; su vigencia y validación institucional siguen pendientes.
