# Manual de uso

Aplicación web en `/p2`. En la instalación real (`FICCT_EXPERIENCE=institutional`) no hay cuentas de ejemplo: la administración crea las cuentas de la autoridad, de las personas revisoras y de las evaluadoras, y los postulantes se registran solos. El modo entrenamiento (`training`), con escenarios de práctica y cuentas ficticias en `.local/ACCESOS_DEMO.txt`, se instala aparte y se describe en el README.

Para desplegar, ver `DESPLIEGUE.md`. Las reglas y su fundamento, en `DECISIONES_ADOPTADAS_20261126.md`.

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

## Recorrido de una convocatoria

1. Administración: crea las cuentas y asigna a cada persona su función en la convocatoria.
2. Autoridad: crea el borrador, define ofertas, plazas, pesos del examen y nota mínima; asigna evaluadores y publica.
3. Postulante: carga los documentos y confirma el envío dentro del plazo de recepción.
4. Revisor: revisa cada documento y criterio, habilita o inhabilita y registra los méritos del CPD.
5. Autoridad: cierra la recepción y abre la evaluación.
6. Evaluador: registra y confirma la nota de las áreas asignadas.
7. Autoridad: calcula, resuelve empates con la comisión, aprueba la publicación con el acta y descarga el consolidado PDF/Excel.
8. Postulante: consulta su resultado; puede manifestar aceptación, retiro o renuncia.

## Procedimientos adicionales y límites

Subsanaciones, retiros, renuncias, manifestaciones sobre adjudicaciones y rectificaciones están implementados con versiones y decisiones separadas; los plazos los fija la autoridad de la convocatoria. Una renuncia no selecciona reemplazantes; una rectificación no sustituye automáticamente una publicación histórica. En convocatorias archivadas se consulta el historial, sin ofrecer operaciones cerradas.

No se emiten nombramientos, certificados CPD, documentos de identidad ni firmas. La nota mínima de aprobación se propone en 51 puntos y cada oferta la puede ajustar. Los resultados rigen cuando la autoridad competente los aprueba por resolución.
