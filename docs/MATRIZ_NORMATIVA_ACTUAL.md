# Matriz normativa actual · cierre dirigido 25/11/2026

Clasificación técnica del artículo completo. No es homologación ni porcentaje de cumplimiento. Se conserva el estado anterior y su límite.

| Clasificación de cierre | Artículos |
| --- | ---: |
| Implementado y comprobado íntegramente | 0 |
| Representado documentalmente | 11 |
| Pendiente de homologación | 1 |
| Fuera del alcance de selección | 29 |
| Implementado parcialmente | 40 |
| Pendiente de aclaración | 1 |

## RES-01 · páginas 2

Abrogación de resoluciones anteriores 158/2010 y 037/2013

Clasificación de cierre: Representado documentalmente. Estado técnico anterior: CONTEXTO.

Sin nueva implementación específica en esta fase; se conserva el análisis y el límite anterior.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: No se verificó el registro institucional de vigencia ni normas posteriores.

## RES-02 · páginas 2

Aprobación de los dos reglamentos anexos

Clasificación de cierre: Representado documentalmente. Estado técnico anterior: CONTEXTO.

Sin nueva implementación específica en esta fase; se conserva el análisis y el límite anterior.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: Docencia y laboratorio requieren identificar el régimen aplicable; una categoría informática no sustituye esa clasificación.

## RES-03 · páginas 2, 3

Sistematización anual DICAA y firmas diferenciadas de certificados

Clasificación de cierre: Pendiente de homologación. Estado técnico anterior: NO_IMPLEMENTADO.

Sin nueva implementación específica en esta fase; se conserva el análisis y el límite anterior.

CU: CU8. RF: RF-28, RF-29.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: Los reportes DEMO no son certificados DICAA ni incorporan el circuito de firmas.

## RES-04 · páginas 3

Unidades responsables de cumplir la resolución

Clasificación de cierre: Representado documentalmente. Estado técnico anterior: CONTEXTO.

Sin nueva implementación específica en esta fase; se conserva el análisis y el límite anterior.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: Los roles de software requieren correspondencia institucional y responsables acreditados.

## D-01 · páginas 4

Objeto del reglamento de auxiliares de docencia

Clasificación de cierre: Representado documentalmente. Estado técnico anterior: CONTEXTO.

Sin nueva implementación específica en esta fase; se conserva el análisis y el límite anterior.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: El sistema cubre selección; no toda la relación posterior del auxiliar.

## D-02 · páginas 4

Definición del auxiliar de docencia

Clasificación de cierre: Representado documentalmente. Estado técnico anterior: CONTEXTO.

Sin nueva implementación específica en esta fase; se conserva el análisis y el límite anterior.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: No confundir publicación DEMO con designación y beca institucional.

## D-03 · páginas 4

Funciones de apoyo a la docencia

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: FUERA_ALCANCE.

Fuera del motor de selección: no se añaden asistencia, planillas, becas o seguimiento de personal. Pagos INF513 siguen simulados.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: No se implementa gestión del trabajo docente posterior.

## D-04 · páginas 4

Modalidades de asignatura y laboratorio o sala de cómputo de docencia

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Catálogo original 49/17 relaciones, modalidades y calendarios de ambas fuentes; no inventa cupos de asignaturas.

CU: CU2, CU3. RF: RF-03, RF-08.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_import_preserves_unknown_seats_and_inserts_49_subjects_and_66_relations

Dependencia/límite: Plazas, horas, autorizaciones y vigencia oficial por confirmar.

## D-05 · páginas 5, 6

Asignaturas troncales o cuantitativas susceptibles de auxiliatura

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Catálogo original 49/17 relaciones, modalidades y calendarios de ambas fuentes; no inventa cupos de asignaturas.

CU: CU2. RF: RF-03, RF-04.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_import_preserves_unknown_seats_and_inserts_49_subjects_and_66_relations

Dependencia/límite: Plazas, horas, autorizaciones y vigencia oficial por confirmar.

## D-06 · páginas 5, 6

Identificación de laboratorios o salas sujetos al reglamento

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Catálogo original 49/17 relaciones, modalidades y calendarios de ambas fuentes; no inventa cupos de asignaturas.

CU: CU2. RF: RF-03, RF-04.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_import_preserves_unknown_seats_and_inserts_49_subjects_and_66_relations

Dependencia/límite: Plazas, horas, autorizaciones y vigencia oficial por confirmar.

## D-07 · páginas 5, 6, 7

Requisitos generales y específicos académicos de postulación

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Requisitos y documentos por modalidad configurables y revisables individualmente; condición de graduación permite no aplica con motivo.

CU: CU2, CU4. RF: RF-05, RF-06, RF-12.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_only_documented_conditional_requirement_can_be_not_applicable_with_a_reason

Dependencia/límite: La revisión documental no consulta DICAA automáticamente ni certifica autenticidad. Equivalencia física/digital y formularios pendientes.

## D-08 · páginas 7

Contenido y presentación de la convocatoria y recepción de solicitudes

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Catálogo original 49/17 relaciones, modalidades y calendarios de ambas fuentes; no inventa cupos de asignaturas.

CU: CU2, CU3. RF: RF-02, RF-07, RF-09.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_import_preserves_unknown_seats_and_inserts_49_subjects_and_66_relations

Dependencia/límite: Plazas, horas, autorizaciones y vigencia oficial por confirmar.

## D-09 · páginas 7

Aprobación de convocatoria y comunicaciones institucionales

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Registro versionado de actos y plazos como evidencia interna; aprobación externa requiere respaldo. No emite nombramientos, no automatiza reemplazos ni reabre llamadas históricas.

CU: CU2, CU8. RF: RF-02, RF-29.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_external_approval_cannot_be_claimed_without_an_original_source

Dependencia/límite: Competencias, umbral final, actos originales, plazos operativos y autorización institucional pendientes. Registrar un acto no implementa el nombramiento.

## D-10 · páginas 7

Publicación previa al inicio de actividades académicas

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Catálogo original 49/17 relaciones, modalidades y calendarios de ambas fuentes; no inventa cupos de asignaturas.

CU: CU2. RF: RF-02.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_import_preserves_unknown_seats_and_inserts_49_subjects_and_66_relations

Dependencia/límite: Plazas, horas, autorizaciones y vigencia oficial por confirmar.

## D-11 · páginas 8

Solicitud, carta, formulario CPD y cédula de identidad

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Requisitos y documentos por modalidad configurables y revisables individualmente; condición de graduación permite no aplica con motivo.

CU: CU3, CU4, CU5. RF: RF-09, RF-10, RF-15.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_only_documented_conditional_requirement_can_be_not_applicable_with_a_reason

Dependencia/límite: La revisión documental no consulta DICAA automáticamente ni certifica autenticidad. Equivalencia física/digital y formularios pendientes.

## D-12 · páginas 8, 9

Formulario CPD oficial y rúbricas de méritos hasta 40 puntos

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Registro CPD versionado asociado al documento vigente; componentes y diferencias fundamentados, sin sustituir el mérito ni interpolar rúbricas contradictorias.

CU: CU5. RF: RF-15, RF-16.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_cpd_components_are_preserved_without_replacing_the_existing_merit

Dependencia/límite: CPD auténtico, decimales, franjas y contradicciones requieren aclaración de la unidad emisora.

## D-13 · páginas 9

Habilitación mediante formulario oficial legible, completo y control de requisitos

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Requisitos y documentos por modalidad configurables y revisables individualmente; condición de graduación permite no aplica con motivo.

CU: CU4. RF: RF-10, RF-12, RF-13.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_only_documented_conditional_requirement_can_be_not_applicable_with_a_reason

Dependencia/límite: La revisión documental no consulta DICAA automáticamente ni certifica autenticidad. Equivalencia física/digital y formularios pendientes.

## D-14 · páginas 9

Designación y composición de comisiones y acreditación de veedores 48 horas antes

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Registro de integrantes, responsabilidades, oferta, vigencia, veedores y respaldo con versiones. No concede RBAC ni constituye formalmente una comisión.

CU: CU5. RF: RF-19.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_commission_offer_must_belong_to_its_call

Dependencia/límite: Competencias y designación auténtica pendientes. L13 menciona terna pero enumera solo dos cargos; no inventar el tercero. No se automatizan 48h sin decisión validada.

## D-15 · páginas 9, 10

Exámenes escrito y práctico, contenidos, lugar y exposición máxima de 30 minutos

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Plan y criterios de examen registrables; perfiles documentales distinguen 36 escrito/24 práctico en docencia y 36 práctico/24 teórico en laboratorio.

CU: CU5. RF: RF-17, RF-20.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_examination_regimes_remain_distinct_and_lab_terna_ambiguity_is_visible

Dependencia/límite: Composición entre áreas y componentes por confirmar; el modelo DEMO no equivale a una fórmula oficial homologada.

## D-16 · páginas 10

Examen escrito 60 por ciento, 36 puntos; práctico 40 por ciento, 24 puntos

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Plan y criterios de examen registrables; perfiles documentales distinguen 36 escrito/24 práctico en docencia y 36 práctico/24 teórico en laboratorio.

CU: CU5. RF: RF-18, RF-21.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_examination_regimes_remain_distinct_and_lab_terna_ambiguity_is_visible

Dependencia/límite: Composición entre áreas y componentes por confirmar; el modelo DEMO no equivale a una fórmula oficial homologada.

## D-17 · páginas 10

Méritos 40 y examen 60; comunicación y publicación dentro de 48 horas

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Registro versionado de actos y plazos como evidencia interna; aprobación externa requiere respaldo. No emite nombramientos, no automatiza reemplazos ni reabre llamadas históricas.

CU: CU5, CU8. RF: RF-23, RF-28.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_external_approval_cannot_be_claimed_without_an_original_source

Dependencia/límite: Competencias, umbral final, actos originales, plazos operativos y autorización institucional pendientes. Registrar un acto no implementa el nombramiento.

## D-18 · páginas 10

Nuevo examen exclusivo de empatados por últimas plazas, condiciones de la comisión

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Se conservan méritos/examen y rondas DEMO de desempate; fuentes distinguen los artículos de nuevo examen para las últimas plazas.

CU: CU6. RF: RF-27.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_admission_thresholds_are_not_final_approval_thresholds

Dependencia/límite: Nota aprobatoria final y condiciones de comisión sin confirmar. PPA 51/55 no se usa como aprobación final.

## D-19 · páginas 10, 11

Informe y formularios dentro de 24 horas, resolución HCF y orden descendente

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Registro versionado de actos y plazos como evidencia interna; aprobación externa requiere respaldo. No emite nombramientos, no automatiza reemplazos ni reabre llamadas históricas.

CU: CU6, CU8. RF: RF-25, RF-28, RF-29.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_external_approval_cannot_be_claimed_without_an_original_source

Dependencia/límite: Competencias, umbral final, actos originales, plazos operativos y autorización institucional pendientes. Registrar un acto no implementa el nombramiento.

## D-20 · páginas 11, 12

Desierta y vacante; nuevas convocatorias y vías excepcionales documentadas

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Registro versionado de actos y plazos como evidencia interna; aprobación externa requiere respaldo. No emite nombramientos, no automatiza reemplazos ni reabre llamadas históricas.

CU: CU6. RF: RF-26.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_external_approval_cannot_be_claimed_without_an_original_source

Dependencia/límite: Competencias, umbral final, actos originales, plazos operativos y autorización institucional pendientes. Registrar un acto no implementa el nombramiento.

## D-21 · páginas 12

Nombramiento de una gestión sin ratificación automática

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Registro versionado de actos y plazos como evidencia interna; aprobación externa requiere respaldo. No emite nombramientos, no automatiza reemplazos ni reabre llamadas históricas.

CU: CU8. RF: RF-29.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_external_approval_cannot_be_claimed_without_an_original_source

Dependencia/límite: Competencias, umbral final, actos originales, plazos operativos y autorización institucional pendientes. Registrar un acto no implementa el nombramiento. El sistema solo permite registrar el acto como evidencia interna (InstitutionalRegister::record); no calcula, controla ni alerta este plazo o período.

## D-22 · páginas 12

Duración de dos semestres o un año y calendario de nombramiento

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Registro versionado de actos y plazos como evidencia interna; aprobación externa requiere respaldo. No emite nombramientos, no automatiza reemplazos ni reabre llamadas históricas.

CU: CU8. RF: RF-29.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_external_approval_cannot_be_claimed_without_an_original_source

Dependencia/límite: Competencias, umbral final, actos originales, plazos operativos y autorización institucional pendientes. Registrar un acto no implementa el nombramiento. El sistema solo permite registrar el acto como evidencia interna (InstitutionalRegister::record); no calcula, controla ni alerta este plazo o período.

## D-23 · páginas 13

Seguimiento institucional del desempeño

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: FUERA_ALCANCE.

Fuera del motor de selección: no se añaden asistencia, planillas, becas o seguimiento de personal. Pagos INF513 siguen simulados.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: No se implementa seguimiento posterior al nombramiento.

## D-24 · páginas 13

Suspensión, controles iniciales y declaratoria de vacancia

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: FUERA_ALCANCE.

Fuera del motor de selección: no se añaden asistencia, planillas, becas o seguimiento de personal. Pagos INF513 siguen simulados.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: No trasladar estas sanciones laborales al retiro de una postulación DEMO.

## D-25 · páginas 14

Vacancia posterior por abandono, renuncia, licencia o expulsión

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Registro versionado de actos y plazos como evidencia interna; aprobación externa requiere respaldo. No emite nombramientos, no automatiza reemplazos ni reabre llamadas históricas.

CU: CU6, CU7. RF: RF-26, RF-30.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_external_approval_cannot_be_claimed_without_an_original_source

Dependencia/límite: Competencias, umbral final, actos originales, plazos operativos y autorización institucional pendientes. Registrar un acto no implementa el nombramiento.

## D-26 · páginas 14

Derechos del auxiliar

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: FUERA_ALCANCE.

Fuera del motor de selección: no se añaden asistencia, planillas, becas o seguimiento de personal. Pagos INF513 siguen simulados.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: No se gestiona la relación posterior al nombramiento.

## D-27 · páginas 14

Obligaciones del auxiliar

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: FUERA_ALCANCE.

Fuera del motor de selección: no se añaden asistencia, planillas, becas o seguimiento de personal. Pagos INF513 siguen simulados.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: No se gestiona cumplimiento laboral o académico del cargo.

## D-28 · páginas 15

Dependencias académicas y administrativas

Clasificación de cierre: Representado documentalmente. Estado técnico anterior: CONTEXTO.

Sin nueva implementación específica en esta fase; se conserva el análisis y el límite anterior.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: Los permisos técnicos no certifican estas dependencias formales.

## D-29 · páginas 15

Registro diario de asistencia

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: FUERA_ALCANCE.

Fuera del motor de selección: no se añaden asistencia, planillas, becas o seguimiento de personal. Pagos INF513 siguen simulados.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: Asistencia no forma parte de la selección implementada.

## D-30 · páginas 15

Control de asistencia y registros habilitados

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: FUERA_ALCANCE.

Fuera del motor de selección: no se añaden asistencia, planillas, becas o seguimiento de personal. Pagos INF513 siguen simulados.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: Sin integración biométrica ni registros laborales.

## D-31 · páginas 15

Justificación de ausencias por salud

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: FUERA_ALCANCE.

Fuera del motor de selección: no se añaden asistencia, planillas, becas o seguimiento de personal. Pagos INF513 siguen simulados.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: No se implementa procedimiento médico o laboral.

## D-32 · páginas 15

Informes mensuales

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: FUERA_ALCANCE.

Fuera del motor de selección: no se añaden asistencia, planillas, becas o seguimiento de personal. Pagos INF513 siguen simulados.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: Los reportes de selección no sustituyen informes de trabajo.

## D-33 · páginas 15

Consolidación de informes para pago

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: FUERA_ALCANCE.

Fuera del motor de selección: no se añaden asistencia, planillas, becas o seguimiento de personal. Pagos INF513 siguen simulados.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: No hay nómina o autorización de pagos.

## D-34 · páginas 16

Beca económica

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: FUERA_ALCANCE.

Fuera del motor de selección: no se añaden asistencia, planillas, becas o seguimiento de personal. Pagos INF513 siguen simulados.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: No hay cálculo o pago de beca.

## D-35 · páginas 16, 17

Carga horaria, máximo mensual, salas y ausencia de cruce con materias inscritas

Clasificación de cierre: Pendiente de aclaración. Estado técnico anterior: NO_IMPLEMENTADO.

Sin nueva implementación específica en esta fase; se conserva el análisis y el límite anterior.

CU: CU3, CU6. RF: RF-08, RF-26.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: Múltiples adjudicaciones no se prohíben globalmente; falta comprobar horarios y límites aplicables. No extrapolar estos límites al otro régimen.

## D-36 · páginas 17

Registro CPD y documentación institucional del auxiliar designado

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: NO_IMPLEMENTADO.

Sin nueva implementación específica en esta fase; se conserva el análisis y el límite anterior.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: Cargar formulario CPD del postulante no equivale a registrar un auxiliar designado.

## D-37 · páginas 17

Condiciones y documentación para pago mensual

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: FUERA_ALCANCE.

Fuera del motor de selección: no se añaden asistencia, planillas, becas o seguimiento de personal. Pagos INF513 siguen simulados.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: No hay gestión de planillas o pago.

## D-38 · páginas 18

Prohibición de auxiliares ad honorem o con carga cero

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: FUERA_ALCANCE.

Fuera del motor de selección: no se añaden asistencia, planillas, becas o seguimiento de personal. Pagos INF513 siguen simulados.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: No se crean nombramientos ni cargas laborales.

## D-39 · páginas 18

Auxiliar no puede evaluar estudiantes de la asignatura por el docente

Clasificación de cierre: Representado documentalmente. Estado técnico anterior: CONTEXTO.

Sin nueva implementación específica en esta fase; se conserva el análisis y el límite anterior.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: Distinguir estudiantes de la asignatura y postulantes al concurso; no implica impedir que un evaluador autorizado califique el concurso.

## D-40 · páginas 18

Sanciones por incumplimiento de autoridades

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: FUERA_ALCANCE.

Fuera del motor de selección: no se añaden asistencia, planillas, becas o seguimiento de personal. Pagos INF513 siguen simulados.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: La auditoría informática no impone sanciones institucionales.

## L-01 · páginas 19

Objeto del reglamento de auxiliares de laboratorio

Clasificación de cierre: Representado documentalmente. Estado técnico anterior: CONTEXTO.

Sin nueva implementación específica en esta fase; se conserva el análisis y el límite anterior.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: Identificar régimen de laboratorio de servicio académico o social por oferta.

## L-02 · páginas 19

Definición de auxiliar de laboratorio

Clasificación de cierre: Representado documentalmente. Estado técnico anterior: CONTEXTO.

Sin nueva implementación específica en esta fase; se conserva el análisis y el límite anterior.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: No equivale automáticamente a auxiliar de sala bajo docencia.

## L-03 · páginas 19

Funciones del auxiliar de laboratorio

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: FUERA_ALCANCE.

Fuera del motor de selección: no se añaden asistencia, planillas, becas o seguimiento de personal. Pagos INF513 siguen simulados.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: El sistema no gestiona el trabajo posterior del auxiliar.

## L-04 · páginas 19

Modalidades de servicio social y académico

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Catálogo original 49/17 relaciones, modalidades y calendarios de ambas fuentes; no inventa cupos de asignaturas.

CU: CU2, CU3. RF: RF-03, RF-08.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_import_preserves_unknown_seats_and_inserts_49_subjects_and_66_relations

Dependencia/límite: Plazas, horas, autorizaciones y vigencia oficial por confirmar.

## L-05 · páginas 19, 20

Laboratorios y requisitos para creación, equipamiento, presupuesto e inspección

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Catálogo original 49/17 relaciones, modalidades y calendarios de ambas fuentes; no inventa cupos de asignaturas.

CU: CU2. RF: RF-03.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_import_preserves_unknown_seats_and_inserts_49_subjects_and_66_relations

Dependencia/límite: Plazas, horas, autorizaciones y vigencia oficial por confirmar.

## L-06 · páginas 20, 21

Requisitos generales y específicos, PPA mínimo 55 y no haber sido auxiliar de laboratorio

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Requisitos y documentos por modalidad configurables y revisables individualmente; condición de graduación permite no aplica con motivo.

CU: CU2, CU4. RF: RF-05, RF-06, RF-12.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_only_documented_conditional_requirement_can_be_not_applicable_with_a_reason

Dependencia/límite: La revisión documental no consulta DICAA automáticamente ni certifica autenticidad. Equivalencia física/digital y formularios pendientes.

## L-07 · páginas 21

Convocatoria por Decano y comunicación previa a DICAA

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Registro versionado de actos y plazos como evidencia interna; aprobación externa requiere respaldo. No emite nombramientos, no automatiza reemplazos ni reabre llamadas históricas.

CU: CU2. RF: RF-02.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_external_approval_cannot_be_claimed_without_an_original_source

Dependencia/límite: Competencias, umbral final, actos originales, plazos operativos y autorización institucional pendientes. Registrar un acto no implementa el nombramiento.

## L-08 · páginas 21

Publicación por Decano para laboratorios autorizados

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Registro versionado de actos y plazos como evidencia interna; aprobación externa requiere respaldo. No emite nombramientos, no automatiza reemplazos ni reabre llamadas históricas.

CU: CU2. RF: RF-02.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_external_approval_cannot_be_claimed_without_an_original_source

Dependencia/límite: Competencias, umbral final, actos originales, plazos operativos y autorización institucional pendientes. Registrar un acto no implementa el nombramiento.

## L-09 · páginas 21

Publicación previa al inicio de actividades académicas

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Catálogo original 49/17 relaciones, modalidades y calendarios de ambas fuentes; no inventa cupos de asignaturas.

CU: CU2. RF: RF-02.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_import_preserves_unknown_seats_and_inserts_49_subjects_and_66_relations

Dependencia/límite: Plazas, horas, autorizaciones y vigencia oficial por confirmar.

## L-10 · páginas 21

Solicitud al Decano, formulario CPD, identidad y currículum

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Requisitos y documentos por modalidad configurables y revisables individualmente; condición de graduación permite no aplica con motivo.

CU: CU3, CU4, CU5. RF: RF-09, RF-10, RF-15.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_only_documented_conditional_requirement_can_be_not_applicable_with_a_reason

Dependencia/límite: La revisión documental no consulta DICAA automáticamente ni certifica autenticidad. Equivalencia física/digital y formularios pendientes.

## L-11 · páginas 22

Méritos hasta 40: permanencia 20, titulación 5, PPA 15

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Registro CPD versionado asociado al documento vigente; componentes y diferencias fundamentados, sin sustituir el mérito ni interpolar rúbricas contradictorias.

CU: CU5. RF: RF-15, RF-16.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_cpd_components_are_preserved_without_replacing_the_existing_merit

Dependencia/límite: CPD auténtico, decimales, franjas y contradicciones requieren aclaración de la unidad emisora.

## L-12 · páginas 22

Habilitación por formulario oficial completo y control de requisitos

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Requisitos y documentos por modalidad configurables y revisables individualmente; condición de graduación permite no aplica con motivo.

CU: CU4. RF: RF-10, RF-12, RF-13.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_only_documented_conditional_requirement_can_be_not_applicable_with_a_reason

Dependencia/límite: La revisión documental no consulta DICAA automáticamente ni certifica autenticidad. Equivalencia física/digital y formularios pendientes.

## L-13 · páginas 23

Comisión designada por Decano y acreditación de veedor 48 horas antes

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Registro de integrantes, responsabilidades, oferta, vigencia, veedores y respaldo con versiones. No concede RBAC ni constituye formalmente una comisión.

CU: CU5. RF: RF-19.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_commission_offer_must_belong_to_its_call

Dependencia/límite: Competencias y designación auténtica pendientes. L13 menciona terna pero enumera solo dos cargos; no inventar el tercero. No se automatizan 48h sin decisión validada.

## L-14 · páginas 23

Examen teórico y práctico en predios universitarios y exposición de 30 minutos

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Plan y criterios de examen registrables; perfiles documentales distinguen 36 escrito/24 práctico en docencia y 36 práctico/24 teórico en laboratorio.

CU: CU5. RF: RF-17, RF-20.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_examination_regimes_remain_distinct_and_lab_terna_ambiguity_is_visible

Dependencia/límite: Composición entre áreas y componentes por confirmar; el modelo DEMO no equivale a una fórmula oficial homologada.

## L-15 · páginas 23

Práctico 60 por ciento, 36 puntos; teórico 40 por ciento, 24 puntos

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Plan y criterios de examen registrables; perfiles documentales distinguen 36 escrito/24 práctico en docencia y 36 práctico/24 teórico en laboratorio.

CU: CU5. RF: RF-18, RF-21.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_examination_regimes_remain_distinct_and_lab_terna_ambiguity_is_visible

Dependencia/límite: Composición entre áreas y componentes por confirmar; el modelo DEMO no equivale a una fórmula oficial homologada.

## L-16 · páginas 23

Méritos 40 y examen de competencia 60

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Se conservan méritos/examen y rondas DEMO de desempate; fuentes distinguen los artículos de nuevo examen para las últimas plazas.

CU: CU5. RF: RF-23.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_admission_thresholds_are_not_final_approval_thresholds

Dependencia/límite: Nota aprobatoria final y condiciones de comisión sin confirmar. PPA 51/55 no se usa como aprobación final.

## L-17 · páginas 24

Nuevo examen exclusivo por empate en últimas plazas, condiciones de comisión

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Se conservan méritos/examen y rondas DEMO de desempate; fuentes distinguen los artículos de nuevo examen para las últimas plazas.

CU: CU6. RF: RF-27.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_admission_thresholds_are_not_final_approval_thresholds

Dependencia/límite: Nota aprobatoria final y condiciones de comisión sin confirmar. PPA 51/55 no se usa como aprobación final.

## L-18 · páginas 24

Informe en 24 horas, aprobación por resolución del Decano y orden descendente

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Registro versionado de actos y plazos como evidencia interna; aprobación externa requiere respaldo. No emite nombramientos, no automatiza reemplazos ni reabre llamadas históricas.

CU: CU6, CU8. RF: RF-25, RF-28, RF-29.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_external_approval_cannot_be_claimed_without_an_original_source

Dependencia/límite: Competencias, umbral final, actos originales, plazos operativos y autorización institucional pendientes. Registrar un acto no implementa el nombramiento.

## L-19 · páginas 24, 25

Desierta y vacante, nuevas convocatorias y excepciones documentadas

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Registro versionado de actos y plazos como evidencia interna; aprobación externa requiere respaldo. No emite nombramientos, no automatiza reemplazos ni reabre llamadas históricas.

CU: CU6. RF: RF-26.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_external_approval_cannot_be_claimed_without_an_original_source

Dependencia/límite: Competencias, umbral final, actos originales, plazos operativos y autorización institucional pendientes. Registrar un acto no implementa el nombramiento.

## L-20 · páginas 25

Requisitos para nombramiento

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Registro versionado de actos y plazos como evidencia interna; aprobación externa requiere respaldo. No emite nombramientos, no automatiza reemplazos ni reabre llamadas históricas.

CU: CU8. RF: RF-29.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_external_approval_cannot_be_claimed_without_an_original_source

Dependencia/límite: Competencias, umbral final, actos originales, plazos operativos y autorización institucional pendientes. Registrar un acto no implementa el nombramiento. El sistema solo permite registrar el acto como evidencia interna (InstitutionalRegister::record); no calcula, controla ni alerta este plazo o período.

## L-21 · páginas 25

Nombramiento por Decano en plazo no mayor a 15 días

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Registro versionado de actos y plazos como evidencia interna; aprobación externa requiere respaldo. No emite nombramientos, no automatiza reemplazos ni reabre llamadas históricas.

CU: CU8. RF: RF-29.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_external_approval_cannot_be_claimed_without_an_original_source

Dependencia/límite: Competencias, umbral final, actos originales, plazos operativos y autorización institucional pendientes. Registrar un acto no implementa el nombramiento. El sistema solo permite registrar el acto como evidencia interna (InstitutionalRegister::record); no calcula, controla ni alerta este plazo o período.

## L-22 · páginas 25

Nombramiento de una gestión y restricción de haber ejercido antes laboratorio

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Registro versionado de actos y plazos como evidencia interna; aprobación externa requiere respaldo. No emite nombramientos, no automatiza reemplazos ni reabre llamadas históricas.

CU: CU2, CU8. RF: RF-06, RF-29.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_external_approval_cannot_be_claimed_without_an_original_source

Dependencia/límite: Competencias, umbral final, actos originales, plazos operativos y autorización institucional pendientes. Registrar un acto no implementa el nombramiento. El sistema solo permite registrar el acto como evidencia interna (InstitutionalRegister::record); no calcula, controla ni alerta este plazo o período.

## L-23 · páginas 25, 26

Duración y calendario según servicio social o académico

Clasificación de cierre: Implementado parcialmente. Estado técnico anterior: PARCIAL_TECNICO_Y_DOCUMENTAL.

Registro versionado de actos y plazos como evidencia interna; aprobación externa requiere respaldo. No emite nombramientos, no automatiza reemplazos ni reabre llamadas históricas.

CU: CU8. RF: RF-29.

Verificación: tests/Feature/InstitutionalCatalogueTest.php::test_external_approval_cannot_be_claimed_without_an_original_source

Dependencia/límite: Competencias, umbral final, actos originales, plazos operativos y autorización institucional pendientes. Registrar un acto no implementa el nombramiento. El sistema solo permite registrar el acto como evidencia interna (InstitutionalRegister::record); no calcula, controla ni alerta este plazo o período.

## L-24 · páginas 26

Seguimiento de funciones del auxiliar

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: FUERA_ALCANCE.

Fuera del motor de selección: no se añaden asistencia, planillas, becas o seguimiento de personal. Pagos INF513 siguen simulados.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: No hay control de desempeño posterior.

## L-25 · páginas 26

Suspensión y vacancia durante ejercicio del cargo

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: FUERA_ALCANCE.

Fuera del motor de selección: no se añaden asistencia, planillas, becas o seguimiento de personal. Pagos INF513 siguen simulados.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: No convertir sanciones del cargo en exclusiones automáticas de postulantes.

## L-26 · páginas 27

Derechos del auxiliar de laboratorio

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: FUERA_ALCANCE.

Fuera del motor de selección: no se añaden asistencia, planillas, becas o seguimiento de personal. Pagos INF513 siguen simulados.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: Relación posterior al nombramiento fuera del alcance.

## L-27 · páginas 27

Obligaciones del auxiliar de laboratorio

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: FUERA_ALCANCE.

Fuera del motor de selección: no se añaden asistencia, planillas, becas o seguimiento de personal. Pagos INF513 siguen simulados.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: No se gestiona cumplimiento del cargo.

## L-28 · páginas 27

Dependencias académicas y administrativas

Clasificación de cierre: Representado documentalmente. Estado técnico anterior: CONTEXTO.

Sin nueva implementación específica en esta fase; se conserva el análisis y el límite anterior.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: Roles técnicos requieren homologación institucional, no equivalencia automática.

## L-29 · páginas 27

Registro diario de asistencia

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: FUERA_ALCANCE.

Fuera del motor de selección: no se añaden asistencia, planillas, becas o seguimiento de personal. Pagos INF513 siguen simulados.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: Sin gestión de asistencia laboral.

## L-30 · páginas 28

Control de asistencia y registro biométrico

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: FUERA_ALCANCE.

Fuera del motor de selección: no se añaden asistencia, planillas, becas o seguimiento de personal. Pagos INF513 siguen simulados.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: Sin integración biométrica.

## L-31 · páginas 28

Justificación por enfermedad

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: FUERA_ALCANCE.

Fuera del motor de selección: no se añaden asistencia, planillas, becas o seguimiento de personal. Pagos INF513 siguen simulados.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: Sin trámite médico o laboral.

## L-32 · páginas 28

Informes de actividades

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: FUERA_ALCANCE.

Fuera del motor de selección: no se añaden asistencia, planillas, becas o seguimiento de personal. Pagos INF513 siguen simulados.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: Reportes de selección no son informes mensuales del cargo.

## L-33 · páginas 28

Beca económica

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: FUERA_ALCANCE.

Fuera del motor de selección: no se añaden asistencia, planillas, becas o seguimiento de personal. Pagos INF513 siguen simulados.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: Sin cálculo de becas.

## L-34 · páginas 28

Designaciones, presupuesto y carga de ocho horas diarias o cuarenta semanales

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: NO_IMPLEMENTADO.

Sin nueva implementación específica en esta fase; se conserva el análisis y el límite anterior.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: No se validan cargas ni presupuesto; no aplicar como límite global la carga mensual del régimen de docencia.

## L-35 · páginas 29

Condiciones y documentación para pago

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: FUERA_ALCANCE.

Fuera del motor de selección: no se añaden asistencia, planillas, becas o seguimiento de personal. Pagos INF513 siguen simulados.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: No hay nómina ni comprobación para desembolsos.

## L-36 · páginas 29

Prohibición de auxiliares ad honorem

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: FUERA_ALCANCE.

Fuera del motor de selección: no se añaden asistencia, planillas, becas o seguimiento de personal. Pagos INF513 siguen simulados.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: No se emiten nombramientos.

## L-37 · páginas 29

El auxiliar no debe evaluar estudiantes de asignaturas por el profesor

Clasificación de cierre: Representado documentalmente. Estado técnico anterior: CONTEXTO.

Sin nueva implementación específica en esta fase; se conserva el análisis y el límite anterior.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: No confundir con la calificación del concurso por sus evaluadores autorizados.

## L-38 · páginas 29

Sanciones por incumplimiento de autoridades

Clasificación de cierre: Fuera del alcance de selección. Estado técnico anterior: FUERA_ALCANCE.

Fuera del motor de selección: no se añaden asistencia, planillas, becas o seguimiento de personal. Pagos INF513 siguen simulados.

CU: Sin requisito operativo adicional. RF: Fuera del contrato funcional.

Verificación: No se añade una aceptación de cumplimiento para este artículo.

Dependencia/límite: La aplicación no impone sanciones institucionales.

## Relectura de los 40 artículos parciales · auditoría local 25/11/2026

Se conserva la clasificación de los 82 artículos. La tabla distingue soporte informático, límites técnicos y actuaciones institucionales. No es un porcentaje de cumplimiento ni cierre de la auditoría de todo el código. Los PDF originales mantienen sus hashes.

| Artículo | Soporte existente | Límite real | Tipo de límite | Solicitudes existentes |
| --- | --- | --- | --- | --- |
| D-04 | Categorías, régimen y modalidad por oferta; perfil documental por convocatoria. | La denominación de sala de docencia no equivale al régimen de laboratorio de servicio. Confirmación de la unidad convocante. | MODELO_MAS_HOMOLOGACION | SOL-07 |
| D-05 | Catálogo de 49 asignaturas y 66 relaciones históricas conservado con número impreso y página. | El catálogo de materias ofertadas no acredita por sí solo su clasificación en el plan de estudios vigente. | CATALOGO_MAS_FUENTE_EXTERNA | SOL-09 |
| D-06 | Tipos de sala y laboratorio, plazas y pesos por convocatoria. | Identificación física y autorización de recintos/equipos no certificadas por un catálogo informático. | CATALOGO_MAS_ACTUACION_EXTERNA | SOL-09 |
| D-07 | Criterios documentales individuales; decisión del revisor; No aplica solo para condición expresamente condicional. | NUEVO: art. 7(e), p.5–6, incluye salas; convocatoria 01 p.4 y 02 p.3 omiten esa condición. El perfil sala tampoco la contiene. Aclarar la aplicación sin modificar la referencia histórica ni dar por derogada la resolución. | DISCREPANCIA_DE_FUENTES_Y_PERFIL | SOL-20 |
| D-08 | Convocatoria/ofertas, fechas, gestión/número; calendario y plantillas versionados en registro institucional. | No hay control estructurado de carga horaria ht/hp en la oferta. Se puede consignar en el respaldo y notas; su automatización no está implementada. Recepción física y aprobación requieren fuente institucional. | LIMITE_TECNICO_Y_EXTERNO | SOL-09, SOL-15 |
| D-09 | Registro versionado de resolución/acta, respaldo y autoridad emisora, sin activación oficial. | El acto del HCF y los oficios no los emite ni aprueba automáticamente este sistema. | APROBACION_EXTERNA | SOL-11 |
| D-10 | Fechas originales y calendario por modalidad; fechas ausentes permanecen sin inventar. | Falta el calendario académico aprobado y acreditar publicación válida antes del inicio de actividades. | CALENDARIO_Y_ACTO_EXTERNOS | SOL-10 |
| D-11 | Carga, versión, SHA-256, constancia interna PDF y consulta por propietario/revisor. | La constancia interna no reemplaza recepción física, firma, foliación ni autorización del canal digital. | SOPORTE_DIGITAL_MAS_EQUIVALENCIA | SOL-01, SOL-15 |
| D-12 | Registro CPD con componentes máximos 24+12+4, total certificado, discrepancia motivada y documento íntegro. | No automatiza las franjas ni resuelve porcentajes redondeados, límites decimales o progresión de semestres. Falta el formulario auténtico y aclaración CPD. | RUBRICA_PENDIENTE | SOL-01, SOL-02, SOL-05 |
| D-13 | Habilitación exige todos los documentos/requisitos y análisis técnico vigente; observación/subsanación versionada. | La legibilidad, integridad de contenido y emisión auténtica del CPD requieren cotejo humano autorizado; antivirus no certifica requisitos. Bandejas de subsanación corregidas en esta intervención. | REVISION_HUMANA_MAS_VALIDACION_EXTERNA | SOL-01, SOL-20 |
| D-14 | Registro versionado de integrantes, responsabilidades, autoridad, observadores y acreditación. | No acredita identidad/competencia de la comisión ni exige automáticamente la composición y 48 horas. El registro es descriptivo; no declara válida una comisión incompleta. | REPRESENTACION_NO_ACREDITACION | SOL-08 |
| D-15 | Definición documental de examen y criterios; plan de examen versionado con inicio/fin y condiciones. | No es un sistema de examen presencial ni cronometra exposición 20+10. Preparación, sorteo y acta dependen de la comisión. | REGISTRO_MAS_ACTUACION_EXTERNA | SOL-07, SOL-13 |
| D-16 | Definición escrito36/práctico24; cálculo decimal en esquemas DEMO explícitos. | Combinación de componentes con áreas de salas pendiente; el esquema documental no autoriza una fórmula institucional. | COMPOSICION_PENDIENTE | SOL-07 |
| D-17 | Mérito40+examen60, calificaciones confirmadas, publicación versionada y registro de fechas. | No hay alerta automática de 48 horas desde una conclusión oficial acreditada. La fecha de cálculo DEMO no se sustituye por ese evento institucional. | LIMITE_TECNICO_Y_EVENTO_EXTERNO | SOL-10, SOL-11 |
| D-18 | Empate en corte, nuevo examen exclusivo, condiciones, resultados, decisión de comisión y continuidad si persiste. | Faltan acta y competencia institucional homologadas. No se decide por orden de ID ni fórmula inventada. | IMPLEMENTACION_DEMO_MAS_HOMOLOGACION | SOL-13 |
| D-19 | Consolidado, PDF/XLSX, notas, clasificación, documentos, historial y registro de actas. | No emite resolución HCF ni códigos DDHH, no programa horarios CPD ni automatiza el plazo institucional de 24 horas. | LIMITE_TECNICO_Y_ACTOS_EXTERNOS | SOL-11, SOL-16 |
| D-20 | Relación entre convocatorias, vacante retenida por renuncia y acto documental desierta/vacancia. | No determina desierta por mínimo aprobatorio desconocido ni adjudica excepciones tras dos convocatorias sin acta/DICAA. | UMBRAL_Y_EXCEPCION_EXTERNOS | SOL-06, SOL-17 |
| D-21 | Historial de convocatoria y publicaciones sin renovación automática. | Duración del nombramiento y ejecución del cargo son posteriores a selección; no existe módulo de personal. | PARTE_POSTERIOR_A_SELECCION | SOL-16 |
| D-22 | Calendarios documentales conservados. | Designación, inicio/fin de funciones y prórrogas corresponden a nombramiento/ejercicio; no se simula autorización de personal. | PARTE_POSTERIOR_A_SELECCION | SOL-16 |
| D-25 | Renuncia de adjudicación, evidencia, decisión de otra autoridad y plaza retenida; publicaciones históricas preservadas. | No gestiona permiso/expulsión/abandono de un auxiliar ya nombrado ni cubre la vacancia automáticamente. | PARTE_POSTERIOR_A_SELECCION | SOL-17, SOL-19 |
| L-04 | Modalidades de servicio académico/social separadas de docencia de sala. | La clasificación y autorización del recinto las acredita la unidad competente. | MODELO_MAS_HOMOLOGACION | SOL-09 |
| L-05 | Tipos y referencias documentales de laboratorio. | Creación, equipamiento, presupuesto, inspección y aprobación de un nuevo laboratorio son actuaciones externas. | ACTUACION_EXTERNA | SOL-09 |
| L-06 | Perfil laboratorio con PPA55, plan completado salvo graduación, no ejercicio previo y demás criterios manuales. | Resolución distingue permanencia licenciatura8/técnico5 años; convocatorias imprimen8 y se refieren a licenciatura. No se consulta registro académico privado ni se declara autenticidad automáticamente. | CRITERIOS_MAS_FUENTE_EXTERNA | SOL-03, SOL-20 |
| L-07 | Referencia de convocatoria y registro de actos/autoridad. | Comunicación DICAA y emisión por Decano no son acciones que una cuenta genérica pueda acreditar por sí sola. | ACTO_EXTERNO | SOL-12 |
| L-08 | Plazas y tipos documentales por convocatoria. | No se acredita con el registro interno la publicación válida de laboratorios autorizados. | ACTO_EXTERNO | SOL-09, SOL-12 |
| L-09 | Calendario versionado y conservación de fechas originales. | Calendario aprobado e inicio de actividades del laboratorio aún deben acreditarse. | CALENDARIO_EXTERNO | SOL-10 |
| L-10 | Carta al Decano, CPD, identidad, avance, autonomía y CV en perfil documental; carga y consulta de originales. | El original CPD, firma/recepción y equivalencia digital requieren formatos y autorización. La convocatoria indica recepción en Vicedecanato: se conserva el texto, sin cambiarlo a Decanato. | DOCUMENTACION_MAS_EQUIVALENCIA | SOL-03, SOL-15 |
| L-11 | Registro de componentes máximos20+5+15 y puntaje CPD, con diferencia fundamentada y versiones. | 25% de15 no equivale a10. Tabla técnico tiene intervalos superpuestos. No calcular automáticamente una interpretación sin respuesta de CPD. | DISCREPANCIA_ARITMETICA_Y_RUBRICA | SOL-03, SOL-04, SOL-05 |
| L-12 | Cotejo por criterio, análisis vigente y bloqueo de habilitación sin cumplimiento. | El control académico computarizado mencionado por la resolución no se suplanta con un análisis antivirus ni una base universitaria ficticia. | REVISION_MAS_FUENTE_EXTERNA | SOL-03, SOL-20 |
| L-13 | Registro de comisión, autoridad, miembros, observadores y acreditación. | La página23 llama terna a una lista de dos cargos. No se inventa un tercer integrante; requiere aclaración y acreditación formal. | COMPOSICION_Y_ACREDITACION_EXTERNAS | SOL-14 |
| L-14 | Plan de examen, criterios documentados y fechas. | No automatiza preparación/sorteo de tema ni sesión presencial20+10 minutos; corresponde a la comisión. | ACTUACION_EXTERNA_Y_LIMITE_DE_ALCANCE | SOL-07 |
| L-15 | Definición práctico36/teórico24 y esquema DEMO de componentes sobre100. | Debe homologarse la composición final; no aplicar por error escrito36/práctico24 de docencia. | COMPOSICION_PENDIENTE | SOL-07 |
| L-16 | Suma de méritos40 y examen60 con decimales y versiones. | Faltan regla CPD de redondeo y umbral. Este artículo no contiene el plazo48h de docencia; no extenderlo a laboratorio sin fuente. | REGLA_EXTERNA | SOL-05, SOL-06 |
| L-17 | Procedimiento técnico de nuevos exámenes exclusivamente para empatados en corte. | Condiciones, decisiones y acta requieren comisión competente; no hay desempate automático. | IMPLEMENTACION_DEMO_MAS_HOMOLOGACION | SOL-13 |
| L-18 | Consolidado y registro de actos y publicaciones históricas. | Informe24h y resolución de Decano no se ejecutan ni validan como actos oficiales desde el portal. No hay temporizador de conclusión oficial. | LIMITE_TECNICO_Y_APROBACION_EXTERNA | SOL-12, SOL-16 |
| L-19 | Relación de convocatorias y conservación de vacantes por renuncia. | Declaratoria por puntaje necesita umbral; selección por excepción necesita actas, Decanato y DICAA. No reemplaza automáticamente. | UMBRAL_Y_EXCEPCION_EXTERNOS | SOL-06, SOL-17 |
| L-20 | Registro documental de acto de designación, sin ejecutarlo. | Nombramiento posterior a selección requiere requisitos y acto de autoridad. | PARTE_POSTERIOR_A_SELECCION | SOL-12 |
| L-21 | Fechas y actos documentales registrables. | No emite nombramiento ni automatiza el plazo15 días: corresponde a Decanato y al proceso posterior a selección. | PARTE_POSTERIOR_A_SELECCION | SOL-12, SOL-16 |
| L-22 | Perfil no ejercicio previo y ausencia de renovación automática. | Duración de la beca/nombramiento no equivale a vigencia de cuenta o permiso; no se administra personal. | PARTE_POSTERIOR_A_SELECCION | SOL-16 |
| L-23 | Calendario descriptivo por modalidad. | Inicio/fin de servicio social/académico y fechas de resolución son posteriores a selección; no se ejecuta contratación. | PARTE_POSTERIOR_A_SELECCION | SOL-16 |
