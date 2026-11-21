# CONTRATO FUNCIONAL DEL GRUPO #09

Fuente: listado de ocho casos de uso aprobado según el usuario y 30 requisitos funcionales presentados, proporcionados en esta conversación. Esta lista tiene precedencia de numeración sobre los RF de `Sistema_Seleccion_Auxiliares_FICCT_Analisis_Actualizado.pdf`, cuya enumeración difiere.

## Ocho casos de uso aprobados — conservar IDs y denominaciones

- CU1. Gestión de Usuarios (Decano, Vice, Evaluadores, Postulantes).
- CU2. Gestión de Parametrizar Convocatorias de Auxiliaturas.
- CU3. Gestión de Postulación Múltiple y Cargar Expediente Digital.
- CU4. Gestión para Evaluar Requisitos, Habilitar Postulantes y Controlar Acceso a Examen.
- CU5. Gestión de Méritos, Ponderar Examen de Competencia y Asignar Evaluadores.
- CU6. Gestión de Resultado Final, Generar Ranking y Asignar Plazas.
- CU7. Gestión del Proceso y Auditoría de Calificaciones.
- CU8. Reportes y Estadísticas.

## Los 30 requisitos funcionales canónicos — conservar el texto y numeración

**RF-01 — Gestión de usuarios y roles.** El sistema deberá permitir registrar y administrar usuarios asignándoles roles según su participación en el proceso, tales como postulante, responsable de revisión, docente evaluador, director de carrera, autoridad y administrador, limitando las funciones disponibles de acuerdo con cada rol.

**RF-02 — Gestión de convocatorias.** El sistema deberá permitir registrar, consultar, modificar y administrar convocatorias para la selección de auxiliares, almacenando gestión, número de convocatoria, fechas, estado, requisitos, plazas ofertadas y demás información correspondiente al proceso.

**RF-03 — Gestión de tipos de auxiliares.** El sistema deberá permitir registrar y administrar los diferentes tipos de auxiliatura contemplados en cada convocatoria, como auxiliares de docencia, sala de cómputo, software o programación, redes, electrónica y laboratorio, de acuerdo con la normativa y necesidades de la facultad.

**RF-04 — Gestión de plazas ofertadas.** El sistema deberá permitir definir la cantidad de plazas disponibles para cada tipo de auxiliar, materia, laboratorio o área incluida en una convocatoria, manteniendo actualizado el número de plazas disponibles, cubiertas y vacantes.

**RF-05 — Configuración del alcance de postulantes.** El sistema deberá permitir establecer para cada convocatoria si pueden participar estudiantes de toda la U.A.G.R.M. o únicamente estudiantes pertenecientes a determinadas facultades o carreras, según las condiciones definidas en la convocatoria.

**RF-06 — Configuración de requisitos de postulación.** El sistema deberá permitir registrar y configurar los requisitos generales y específicos exigidos para cada tipo de auxiliar, de manera que puedan adaptarse a futuras modificaciones del reglamento o de las convocatorias sin modificar el sistema.

**RF-07 — Registro de postulaciones.** El sistema deberá permitir que el estudiante registre su postulación a una convocatoria, proporcionando la información requerida y seleccionando el tipo de auxiliatura, materia, laboratorio o área a la que desea postular.

**RF-08 — Postulación a diferentes tipos de auxiliatura.** El sistema deberá permitir que un postulante participe en uno o varios tipos de auxiliatura cuando la convocatoria y normativa vigente lo permitan, manteniendo independiente la evaluación y resultado obtenido para cada categoría.

**RF-09 — Gestión documental de la postulación.** El sistema deberá permitir al postulante cargar digitalmente los documentos solicitados para formalizar su postulación, tales como carta de solicitud, formulario de evaluación de méritos, cédula de identidad, histórico o avance académico, certificados, currículum y documentos de respaldo que correspondan.

**RF-10 — Verificación de documentación obligatoria.** El sistema deberá comprobar que el postulante haya presentado la documentación obligatoria correspondiente al tipo de auxiliatura antes de permitir que la postulación continúe con el proceso de revisión.

**RF-11 — Revisión digital de documentos.** El sistema deberá permitir al personal autorizado consultar y revisar los documentos presentados por cada postulante, evitando depender exclusivamente de la revisión manual de folders y documentación física.

**RF-12 — Verificación de requisitos.** El sistema deberá permitir registrar el cumplimiento o incumplimiento de cada requisito establecido por la convocatoria y la normativa para determinar si un postulante puede continuar en el proceso.

**RF-13 — Habilitación e inhabilitación de postulantes.** El sistema deberá permitir declarar a un postulante como habilitado o inhabilitado una vez concluida la verificación de requisitos, registrando el resultado de la revisión y, cuando corresponda, el motivo de su inhabilitación.

**RF-14 — Control de acceso al examen de competencia.** El sistema deberá impedir que un postulante que no haya sido habilitado continúe a la etapa del examen de competencia, garantizando que únicamente participen candidatos que hayan cumplido previamente los requisitos establecidos.

**RF-15 — Registro del formulario de evaluación de méritos.** El sistema deberá permitir registrar la información del formulario oficial de Evaluación de Méritos emitido por el CPD Facultativo, asociándolo al postulante y a la convocatoria correspondiente.

**RF-16 — Registro del puntaje de méritos.** El sistema deberá permitir registrar el puntaje obtenido por el postulante en la Evaluación de Méritos, considerando el máximo de 40 puntos establecido para esta etapa y conservando el valor utilizado posteriormente para el cálculo final.

**RF-17 — Gestión de áreas del examen de competencia.** El sistema deberá permitir registrar las áreas utilizadas en los exámenes de competencia, tales como soporte técnico, redes, software o programación y electrónica, pudiendo habilitar o deshabilitar áreas según el tipo de auxiliar convocado.

**RF-18 — Configuración de ponderaciones por tipo de auxiliar.** El sistema deberá permitir configurar el porcentaje que corresponde a cada área del examen según el tipo de auxiliar, considerando que las ponderaciones pueden variar entre sala de cómputo, redes, software, electrónica u otras categorías definidas posteriormente.

**RF-19 — Asignación de docentes evaluadores.** El sistema deberá permitir asignar docentes o responsables de evaluación a las áreas, materias o tipos de auxiliares que les corresponda evaluar dentro de una convocatoria.

**RF-20 — Registro de calificaciones por docente.** El sistema deberá permitir que cada docente evaluador registre mediante su propia cuenta las calificaciones correspondientes a los postulantes que tenga asignados, evitando que usuarios no autorizados ingresen notas en su nombre.

**RF-21 — Cálculo del examen de competencia.** El sistema deberá calcular automáticamente el resultado del examen de competencia aplicando las ponderaciones correspondientes a las calificaciones registradas en las diferentes áreas de evaluación, obteniendo el puntaje del examen sobre un máximo de 60 puntos.

**RF-22 — Manejo de notas con dos decimales.** El sistema deberá almacenar, calcular y presentar las calificaciones utilizando dos cifras decimales, manteniendo esta precisión durante la ponderación, tabulación y cálculo del resultado final.

**RF-23 — Cálculo automático del resultado final.** El sistema deberá calcular automáticamente el puntaje final del postulante mediante la suma del resultado de la Evaluación de Méritos, con un máximo de 40 puntos, y el resultado del Examen de Competencia, con un máximo de 60 puntos, obteniendo una calificación final sobre 100 puntos.

**RF-24 — Cálculo por categoría de postulación.** Cuando un estudiante participe en más de un tipo de auxiliatura, el sistema deberá realizar de forma independiente los cálculos correspondientes a cada categoría, aplicando las ponderaciones específicas definidas para cada una.

**RF-25 — Generación de clasificación de postulantes.** El sistema deberá ordenar automáticamente a los postulantes de acuerdo con su calificación final, generando una clasificación o ranking independiente para cada tipo de auxiliar, materia, laboratorio o plaza convocada.

**RF-26 — Determinación de seleccionados según plazas.** El sistema deberá comparar la clasificación obtenida con la cantidad de plazas disponibles para identificar a los postulantes que se encuentren dentro del número de cupos ofertados, sin permitir asignar una cantidad superior a las plazas autorizadas.

**RF-27 — Detección de empates.** El sistema deberá detectar automáticamente cuando dos o más postulantes obtengan la misma calificación final y el empate afecte la asignación de una plaza, dejando el caso identificado para que sea resuelto conforme al procedimiento establecido por la autoridad o normativa correspondiente.

**RF-28 — Consulta consolidada de resultados.** El sistema deberá permitir a las autoridades autorizadas consultar de manera consolidada los puntajes de méritos, calificaciones del examen, ponderaciones, resultado final, posición y estado de selección de todos los postulantes de una convocatoria.

**RF-29 — Generación de reportes del proceso.** El sistema deberá generar reportes de postulantes, documentación presentada, habilitados e inhabilitados, calificaciones, puntajes de méritos, examen de competencia, resultados finales, clasificación, seleccionados y plazas vacantes, incluyendo un reporte resumen similar al formulario utilizado actualmente para consolidar los resultados.

**RF-30 — Auditoría y trazabilidad de calificaciones.** El sistema deberá registrar las operaciones realizadas sobre información sensible del proceso, especialmente el ingreso o modificación de calificaciones, almacenando usuario responsable, fecha, hora, valor anterior, valor nuevo y motivo cuando corresponda, con el propósito de garantizar transparencia y reducir posibles irregularidades.
