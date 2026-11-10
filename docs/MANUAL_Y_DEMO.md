# Manual breve y guion de demostración

Abre http://127.0.0.1:8099. Las contraseñas aleatorias se guardan en `.local/ACCESOS_DEMO.txt`; no se publican en el repositorio ni en el ZIP de código. Son cuentas ficticias del equipo local. Si trasladas el proyecto, el seed genera otras.

## Por perfil

Administrador (`admin@ficct.test`): Usuarios y permisos → crear usuario → asignar responsabilidad y convocatoria. No descarga expedientes. Catálogos permiten crear tipos para nuevas ofertas.

Autoridad (`autoridad@ficct.test`): ver convocatorias y sus ofertas; crear convocatoria DEMO en borrador, agregar ofertas con documentos/requisitos/pesos; publicar; cerrar recepción; abrir evaluación; asignar docentes; calcular resultados; abrir consolidado; gestionar examen adicional si hay empate; aprobar snapshot; descargar PDF/XLSX y consultar auditoría. Las operaciones irreversibles se indican en la pantalla. Los históricos 01/02 no se pueden operar.

Postulante (`estudiante@ficct.test`): ya tiene un borrador en la convocatoria abierta. Adjuntar los documentos ficticios requeridos (PNG/JPG/PDF hasta 5 MB) → verificar lista → confirmar envío definitivo. Su resultado en una oferta no se comparte con otras. Puede crear otras postulaciones permitidas; no vuelve a enviar la misma oferta.

Revisor (`revisor@ficct.test`): abrir convocatoria → expediente → descargar evidencia autorizada → registrar decisión por cada criterio y motivo → habilitar o inhabilitar con fundamento. Para habilitar deben cumplir todos los criterios obligatorios. Registrar formulario CPD/fecha como referencia y puntaje sobre 40. Un archivo subido no equivale a una validación CPD.

Docente (`docente@ficct.test`): abrir convocatoria asignada → expediente habilitado → área propia → guardar borrador o confirmar nota DEMO sobre 100 en etapa de evaluación. Una nota confirmada no se sobrescribe desde su formulario; la rectificación autorizada conserva auditoría.

## Recorrido de 8–12 minutos

1. (1 min) Mostrar panel y las diferencias históricas 01/02. Explicar la convocatoria ficticia abierta y el aviso DEMO.
2. (2 min) Entrar como postulante, revisar borrador y lista documental. Usar solo archivos sintéticos.
3. (2 min) Revisor: revisar por criterio y mostrar separación entre presencia, cumplimiento y habilitación. Mostrar el CPD ficticio como evidencia manual.
4. (2 min) En `Recorrido probado · Evaluación y desempate DEMO`, mostrar docente/áreas, 20.10 de mérito, 48.00 de examen y 68.10 final. No mezclar este fixture ya ejecutado con la convocatoria abierta.
5. (2 min) Autoridad: abrir consolidado. Los dos aspirantes conservan 68.10 inicial; el nuevo examen tiene condiciones, participantes, resultados y acta. La decisión de comisión se registra; el programa no inventa ganador.
6. (1 min) Descargar PDF/XLSX, mostrar auditoría y snapshot. Aclarar qué falta para uso institucional.

`php artisan ficct:demo-recorrido` crea ese segundo recorrido si aún no existe, ejecutando los servicios reales con documentos ficticios. No reinicia ni sobrescribe una convocatoria existente. Sus estados se avanzan de forma acelerada para demostrar la secuencia, no como cronograma de concurso real.

## Operaciones deliberadamente bloqueadas

OFICIAL e históricos, fechas incoherentes para publicación operativa, composición de examen pendiente, faltantes convertidos a cero, acceso ajeno, duplicados, notas confirmadas editadas silenciosamente, aprobación con empate sin nuevo examen/acta y exceso de plazas.

Subsanación tras envío, renuncias, reclamos, revisión de comisión con firmas formales y recálculo después de publicar requieren completar su procedimiento y validación; no se anuncian como resueltos.

## Continuación DEMO 0.2

El recorrido original sigue disponible y no se reescribe. Para usuarios, temporalidad, revocación, reasignación y rectificaciones con decisión separada, seguir DEMO_0_2_CAMBIOS_Y_VALIDACION.md, sección Inicio y demostración. La cola está en /rectificaciones; cada expediente enlaza solicitudes y versiones. La evidencia se registra por referencia a un respaldo custodiado, sin firma institucional. Las plazas no se reasignan al aprobar una rectificación: requieren revisar y aprobar una nueva publicación.
