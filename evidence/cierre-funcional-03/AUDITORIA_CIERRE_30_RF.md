# Revisión de los 30 RF canónicos — 15/11/2026

Se cotejaron los 30 textos canónicos, código y referencias de prueba. Se resolvieron cuatro faltantes técnicos concretos: identificación de convocatorias, administración de tipos, consolidado por convocatoria y reporte detallado. Se corrigió además la opción de facultad en el formulario.

La base funcional DEMO tiene evidencia ejecutada para los 30 RF. Esto no equivale a probar todos sus subcasos ni a cerrar cumplimiento institucional. Los identificadores y textos canónicos permanecen intactos.

| RF | Nombre canónico | Resultado técnico y límite |
|---|---|---|
| RF-01 | Gestión de usuarios y roles | Usuarios, roles, vigencias y revocaciones; perfiles académicos reales pendientes. |
| RF-02 | Gestión de convocatorias | Gestión/número separados y edición del borrador; no se deducen datos legados ni se implementa clonación asistida. |
| RF-03 | Gestión de tipos de auxiliares | Alta y administración de tipos; identidad de tipos usados protegida y disponibilidad para ofertas nuevas. |
| RF-04 | Gestión de plazas ofertadas | Cupos, adjudicaciones activas y vacantes/retenciones visibles; cobertura formal pendiente. |
| RF-05 | Configuración del alcance de postulantes | Ámbitos UAGRM/facultad/carrera; corregido el selector HTML de facultad; perfil académico sigue declarado. |
| RF-06 | Configuración de requisitos de postulación | Documentos y criterios configurables por oferta; contenido oficial debe validarse. |
| RF-07 | Registro de postulaciones | Registro independiente, duplicados y plazo; sin conexión académica institucional. |
| RF-08 | Postulación a diferentes tipos de auxiliatura | Postulación múltiple y resultados separados; compatibilidades pendientes. |
| RF-09 | Gestión documental de la postulación | Archivos privados versionados; antivirus y retención de producción pendientes. |
| RF-10 | Verificación de documentación obligatoria | Comprobación de documentos requeridos antes de enviar; contenido oficial por convocatoria pendiente. |
| RF-11 | Revisión digital de documentos | Consulta/revisión según permisos; legitimidad de originales requiere cotejo institucional. |
| RF-12 | Verificación de requisitos | Decisiones por criterio y subsanaciones con revisión nueva; procedimiento formal pendiente. |
| RF-13 | Habilitación e inhabilitación de postulantes | Habilitación con criterios y motivo; validación institucional de requisitos pendiente. |
| RF-14 | Control de acceso al examen de competencia | Bloqueo de notas sin habilitación; no sustituye control físico de acceso al examen. |
| RF-15 | Registro del formulario de evaluación de méritos | Referencia, copia y puntaje manual CPD; sin emisión, firma ni integración CPD real. |
| RF-16 | Registro del puntaje de méritos | Puntaje máximo y rectificaciones; aclaración de rúbrica oficial pendiente. |
| RF-17 | Gestión de áreas del examen de competencia | Áreas configuradas por oferta; sin imposición de catálogo global fijo. |
| RF-18 | Configuración de ponderaciones por tipo de auxiliar | Pesos por oferta suman 100; composición institucional áreas/componentes pendiente. |
| RF-19 | Asignación de docentes evaluadores | Asignación/reasignación auditada; composición formal de comisiones pendiente. |
| RF-20 | Registro de calificaciones por docente | Notas por cuenta asignada; confirmación y rectificación preservan historia. |
| RF-21 | Cálculo del examen de competencia | Ponderación exacta sobre 60; ausencia no equivale a cero y composición oficial pendiente. |
| RF-22 | Manejo de notas con dos decimales | Almacenamiento/presentación decimal; redondeo técnico pendiente de homologación. |
| RF-23 | Cálculo automático del resultado final | Suma mérito/examen sobre 100; puntaje aprobatorio no se presume. |
| RF-24 | Cálculo por categoría de postulación | Cálculos independientes por postulación y oferta. |
| RF-25 | Generación de clasificación de postulantes | Clasificación por oferta; bloqueada cuando hay revisiones/resultados pendientes. |
| RF-26 | Determinación de seleccionados según plazas | Cupos y aprobación/publicación versionada DEMO; resolución oficial pendiente. |
| RF-27 | Detección de empates | Empate en corte y nuevo examen con condiciones/acta; decisiones de comisión no automáticas. |
| RF-28 | Consulta consolidada de resultados | Nueva consulta de toda la convocatoria, notas/pesos/posición y publicación separada. |
| RF-29 | Generación de reportes del proceso | Nuevos reportes de resultados, áreas, documentación y plazas; no formato oficial CPD. |
| RF-30 | Auditoría y trazabilidad de calificaciones | Actores, antes/después y motivos; versiones protegidas; administración de infraestructura privilegiada. |

## Cierre verificable y trabajo pendiente

Nueva regresión completa: 136 pruebas / 585 aserciones, cero errores y fallos finales. Se conservan las 119 pruebas de la entrega operativa y se añaden 17: C01–C09 y D01–D08. El nuevo recorrido real de interfaz tiene 16 comprobaciones.

No queda otro faltante técnico específico confirmado en esta revisión del núcleo DEMO; no se afirma exhaustividad ni cierre integral de los 30 RF. Siguen pendientes decisiones institucionales sobre umbral, rúbricas, composición, autoridades/plazos, compatibilidades y expediente digital; despliegue y validación externa en infraestructura representativa; antivirus/retención; integraciones y formatos oficiales. La clonación asistida y reportes avanzados son mejoras no ejecutadas, no exigencias añadidas al contrato canónico.

El corpus original ya se recuperó y contrastó. No se inventaron valores ni se modificó normativa. No se inició DEMO 0.4.
