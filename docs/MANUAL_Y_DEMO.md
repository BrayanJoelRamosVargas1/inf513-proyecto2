# Manual de uso y presentación — DEMO 0.3

Actualizado el 18/11/2026. Aplicación local: http://127.0.0.1:8116. El iniciador del equipo es `ABRIR_DEMO_FICCT_VALIDADA.ps1`, en la carpeta del proyecto. Las credenciales ficticias se guardan de forma privada en `sistema/.local/operacion-03/runtime/sistema/.local/ACCESOS_DEMO.txt`. No son cuentas de personas reales.

## Escenarios para presentar

- **Práctica guiada · Postulación y revisión documental:** recepción abierta; Carla tiene un expediente enviado con seis PDF A4 de muestra para el revisor; Bruno tiene un borrador vacío para practicar la carga. Un estudiante nuevo puede postular a esa oferta. Los plazos se indican en pantalla.
- **Práctica guiada · Evaluación con expediente PDF:** Andrea está habilitada, con respaldo de mérito 20.00 de ejemplo. El evaluador tiene Software asignado para registrar su nota. La autoridad puede calcular y publicar después de su confirmación.
- Los escenarios anteriores se conservan. Algunos contienen imágenes pequeñas usadas como pruebas técnicas: no son documentación oficial. La pantalla Revisar archivo indica formato, tamaño y dimensiones antes de abrir o descargar.

Las prácticas se agregan una sola vez mediante `php artisan ficct:demo-documental` en entorno local. Volver a ejecutarlo no renueva plazos ni reemplaza expedientes. No ejecutar seeds, reinicios ni restauraciones para abrir la demostración.

## Qué hace cada perfil

| Perfil | Recorrido | Fuera de su función base |
|---|---|---|
| Postulante | Cuenta → convocatoria abierta → oferta → iniciar/continuar → cargar documentos → confirmar envío → consultar estado y resultados propios | Revisar requisitos, calificarse, aprobar resultados o ver expedientes ajenos |
| Revisor | Bandeja → expediente enviado → revisar archivo original → registrar criterios y motivo → habilitar/inhabilitar → registrar CPD y méritos; atender subsanaciones | Calificar exámenes o publicar selección |
| Evaluador | Convocatoria/oferta asignada → habilitados → nota por área → guardar borrador/confirmar; solicitar rectificación documentada cuando corresponda | Documentos privados, habilitación, edición de notas ajenas o publicación |
| Autoridad | Configurar borrador → ofertas/plazas/requisitos → publicar → asignar evaluadores → cerrar recepción → evaluación → consolidar → resolver empates mediante comisión → aprobar publicación y reportes | Ingresar notas por otro evaluador; acceso implícito a los archivos privados |
| Administrador técnico | Usuarios y permisos → crear/editar/desactivar → asignar o revocar responsabilidad por convocatoria → tipos de auxiliatura | Crear convocatoria académica, revisar documentos, calificar o aprobar por el solo perfil técnico |

Las responsabilidades se asignan por convocatoria y vigencia. Una cuenta puede acumular funciones legítimas, pero no revisar ni calificar su propio expediente. Si una cuenta cambió de perfil, sus expedientes anteriores se conservan como historia personal. Director de Carrera no tiene una atribución inventada: su estructura final sigue pendiente. CPD es una dependencia externa, sin integración automática.

## Documentos y descargas

En **Qué puedo hacer** el postulante y el revisor encuentran seis PDF de ejemplo, todos A4 y marcados MUESTRA. Sirven para practicar, no para acreditar requisitos. No hay certificados oficiales precargados.

En el expediente, **Revisar archivo** muestra nombre original, versión, formato, tamaño y dimensiones cuando es imagen. **Abrir original** lo presenta en otra pestaña; **Descargar original** devuelve los mismos bytes cargados y el tipo correcto. El sistema comprueba el hash antes de entregar el archivo. No convierte un PDF en una imagen pequeña. Solo propietario y revisor autorizado pueden acceder; autoridad, administrador y evaluador no obtienen esos archivos por defecto.

## Cómo presentar en orden

1. Administrador: mostrar los permisos separados por convocatoria.
2. Bruno o estudiante nuevo: cargar PDF de muestra y confirmar envío; mostrar que un borrador incompleto no se envía.
3. Revisor: abrir el expediente de Carla del escenario documental, consultar un PDF y registrar revisión.
4. Evaluador: abrir el escenario de evaluación, registrar y confirmar Software sobre 100.
5. Autoridad: calcular mérito + examen, revisar ranking y aprobar publicación DEMO; descargar consolidado PDF/Excel.
6. Andrea: consultar el resultado de su propia postulación.

## Procedimientos adicionales y límites

Subsanaciones, retiros, renuncias, manifestaciones sobre adjudicaciones y rectificaciones están implementados como procedimientos DEMO con versiones y decisiones separadas. No representan validación institucional de plazos, compatibilidades o autoridades formales. Una renuncia no selecciona reemplazantes; una rectificación no sustituye automáticamente una publicación histórica. En convocatorias archivadas se consulta el historial, sin ofrecer operaciones cerradas.

No se emiten nombramientos, certificados CPD, documentos de identidad ni firmas. El umbral aprobatorio y las ambigüedades de rúbricas y composición siguen sujetos a aclaración institucional. La aprobación de pruebas técnicas no significa cumplimiento normativo integral.
