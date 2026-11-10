# Trazabilidad — DEMO 0.3 (contrato canónico preservado)

Catálogo contractual intacto en `CU_Y_RF_CANONICOS.md`. Mapeo principal CU↔RF propuesto por contenido, sin renumerar. CU7 y CU8 son transversales. Rxx remite a `01-AUDITORIA-FUENTES.md`.

Se ejecutó un escenario base por RF: **PASS no implica cierre integral de todos los subflujos del RF**. Hay límites técnicos y validaciones institucionales explícitos. Los tests usan PostgreSQL separado, servicios reales y peticiones HTTP del framework.

| RF / nombre canónico | CU | Pantalla / módulo | Endpoint | Tabla | Regla | Prueba ejecutada | Alcance restante |
|---|---|---|---|---|---|---|---|
| RF-01 · Gestión de usuarios y roles | CU1 | Usuarios y permisos | GET/POST /usuarios; POST /permisos | users/memberships | R05/R17 | T-01 / test_t01_register_login_and_reject_privilege_escalation (tests/Feature/SelectionTest.php:105) · PASS | G01–G11, G25 y G31: perfiles, vigencia, revocación, sesiones, versiones y reasignación probados; validación de perfiles académicos institucionales pendiente. |
| RF-02 · Gestión de convocatorias | CU2 | Convocatorias | POST /convocatorias; POST /convocatorias/{id}/editar; POST /convocatorias/{id}/estado | calls | R13 | T-02 / test_t02_valid_transition_freezes_configuration (tests/Feature/SelectionTest.php:115) · PASS | Edición de borradores y congelación probadas; clonación asistida de configuración futura pendiente. |
| RF-03 · Gestión de tipos de auxiliares | CU2 | Tipos | POST /categorias | categories | R09 | T-03 / test_t03_category_registration_is_admin_only (tests/Feature/SelectionTest.php:123) · PASS | Alta y activación DEMO; catálogo institucional y vigencia se validan externamente. |
| RF-04 · Gestión de plazas ofertadas | CU2 | Ofertas/cupos | POST /convocatorias/{id}/ofertas | offers/applications | R10/R11/R12 | T-04 / test_t04_seats_are_nonnegative_and_scoped_by_offer (tests/Feature/SelectionTest.php:131) · PASS | Cupos y no sobreasignación probados; renuncias/cambios posteriores de cupo requieren procedimiento. |
| RF-05 · Configuración del alcance de postulantes | CU2 | Elegibilidad por ámbito | POST /convocatorias; POST /ofertas/{id}/postular | calls/users | R08 | T-05 / test_t05_scope_rejects_ineligible_faculty (tests/Feature/SelectionTest.php:138) · PASS | El perfil académico es declarado en DEMO; cotejo institucional pendiente. |
| RF-06 · Configuración de requisitos de postulación | CU2 | Documentos/requisitos configurables | POST /convocatorias/{id}/ofertas | offers | R08/R09/R16 | T-06 / test_t06_published_requirements_cannot_mutate (tests/Feature/SelectionTest.php:145) · PASS | Configurable por oferta; plantillas históricas completas y formulación institucional pendientes. |
| RF-07 · Registro de postulaciones | CU3 | Postulación | POST /ofertas/{id}/postular | applications | R07/R08 | T-07 / test_t07_application_duplicate_and_expired_deadline_rejected (tests/Feature/SelectionTest.php:156) · PASS | Base DEMO probada; vigencia, escala y autoridades para uso real pendientes según auditoría. |
| RF-08 · Postulación a diferentes tipos de auxiliatura | CU3 | Postulación múltiple | POST /ofertas/{id}/postular | applications/offers | R07 | T-08 / test_t08_multiple_categories_and_subject_limit (tests/Feature/SelectionTest.php:166) · PASS | Base DEMO probada; vigencia, escala y autoridades para uso real pendientes según auditoría. |
| RF-09 · Gestión documental de la postulación | CU3 | Expediente digital | POST /postulaciones/{id}/archivo; GET /documentos/{id} | documents | R16/R17 | T-09 / test_t09_file_versions_and_invalid_upload (tests/Feature/SelectionTest.php:184) · PASS | MIME/extensión/tamaño y privacidad probados; antivirus y política de retención institucional pendientes. |
| RF-10 · Verificación de documentación obligatoria | CU4 | Envío documental | POST /postulaciones/{id}/enviar | documents/applications | R14/R16 | T-10 / test_t10_submit_requires_all_documents (tests/Feature/SelectionTest.php:195) · PASS | Base DEMO probada; vigencia, escala y autoridades para uso real pendientes según auditoría. |
| RF-11 · Revisión digital de documentos | CU4 | Revisión documental | GET /postulaciones/{id}; POST /postulaciones/{id}/revision | reviews/documents | R14/R17 | T-11 / test_t11_private_download_requires_owner_or_scoped_reviewer (tests/Feature/SelectionTest.php:204) · PASS | Base DEMO probada; vigencia, escala y autoridades para uso real pendientes según auditoría. |
| RF-12 · Verificación de requisitos | CU4 | Revisión de criterios | POST /postulaciones/{id}/revision | reviews | R08/R14 | T-12 / test_t12_requirement_decisions_preserve_evidence_and_actor (tests/Feature/SelectionTest.php:215) · PASS | Criterios completos y subsanación DEMO versionada probados en P01..P13/P29/P30; plazos y atribuciones institucionales pendientes. |
| RF-13 · Habilitación e inhabilitación de postulantes | CU4 | Habilitación | POST /postulaciones/{id}/habilitacion | applications/audit_events | R14 | T-13 / test_t13_eligibility_requires_complete_review (tests/Feature/SelectionTest.php:225) · PASS | Base DEMO probada; vigencia, escala y autoridades para uso real pendientes según auditoría. |
| RF-14 · Control de acceso al examen de competencia | CU4 | Acceso a evaluación | POST /postulaciones/{id}/nota | applications/assignments/grades | R14 | T-14 / test_t14_non_enabled_cannot_take_exam_via_direct_request (tests/Feature/SelectionTest.php:235) · PASS | Base DEMO probada; vigencia, escala y autoridades para uso real pendientes según auditoría. |
| RF-15 · Registro del formulario de evaluación de méritos | CU5 | Formulario CPD | POST /postulaciones/{id}/meritos | applications/documents | R14/R15 | T-15 / test_t15_cpd_requires_attached_evidence (tests/Feature/SelectionTest.php:244) · PASS | Registro manual con copia de respaldo; sin integración CPD ni firmas digitales. |
| RF-16 · Registro del puntaje de méritos | CU5 | Méritos | POST /postulaciones/{id}/meritos | applications | R01/R15 | T-16 / test_t16_merit_limits_are_exact (tests/Feature/SelectionTest.php:254) · PASS | Registro inicial y rectificación versionada 0.2 probados; CPD real y validación institucional pendientes. |
| RF-17 · Gestión de áreas del examen de competencia | CU5 | Áreas/componentes | POST /convocatorias/{id}/ofertas | offers | R10/R11/R20 | T-17 / test_t17_areas_are_defined_per_offer (tests/Feature/SelectionTest.php:263) · PASS | Base DEMO probada; vigencia, escala y autoridades para uso real pendientes según auditoría. |
| RF-18 · Configuración de ponderaciones por tipo de auxiliar | CU5 | Pesos | POST /convocatorias/{id}/ofertas | offers | R10/R11/R20 | T-18 / test_t18_weights_must_sum_exactly_100 (tests/Feature/SelectionTest.php:269) · PASS | Dimensiones separadas; composición institucional de áreas/componentes pendiente. |
| RF-19 · Asignación de docentes evaluadores | CU5 | Evaluadores | POST /ofertas/{id}/asignar | assignments | R05 | T-19 / test_t19_assignment_checks_teacher_scope_and_single_responsibility (tests/Feature/SelectionTest.php:277) · PASS | Asignación y reasignación autorizada 0.2 probadas; consolidación institucional multievaluador pendiente. |
| RF-20 · Registro de calificaciones por docente | CU5 | Calificaciones | POST /postulaciones/{id}/nota | grades | R17 | T-20 / test_t20_only_assigned_teacher_can_confirm_grade_once (tests/Feature/SelectionTest.php:286) · PASS | Borrador, confirmación, rectificación y recálculo/publicación versionada 0.2 probados; las notas históricas se conservan al retirar/renunciar. |
| RF-21 · Cálculo del examen de competencia | CU5 | Examen | POST /postulaciones/{id}/calcular | applications/grades | R01/R20/R21 | T-21 / test_t21_weighted_exam_is_48_and_missing_notes_block (tests/Feature/SelectionTest.php:299) · PASS | Base DEMO probada; vigencia, escala y autoridades para uso real pendientes según auditoría. |
| RF-22 · Manejo de notas con dos decimales | CU5 | Decimales | POST /postulaciones/{id}/nota; POST /postulaciones/{id}/calcular | grades/applications | R19/R21 | T-22 / test_t22_decimal_rounding_nan_and_precision (tests/Feature/SelectionTest.php:308) · PASS | HALF_UP final solo política técnica de fixture; redondeo institucional pendiente. |
| RF-23 · Cálculo automático del resultado final | CU5 | Resultado final | POST /postulaciones/{id}/calcular | applications | R01 | T-23 / test_t23_final_sum_50_10_and_maximum_100 (tests/Feature/SelectionTest.php:317) · PASS | Base DEMO probada; vigencia, escala y autoridades para uso real pendientes según auditoría. |
| RF-24 · Cálculo por categoría de postulación | CU5 | Cálculo por oferta | POST /postulaciones/{id}/calcular | applications | R20 | T-24 / test_t24_independent_results_by_application (tests/Feature/SelectionTest.php:326) · PASS | Base DEMO probada; vigencia, escala y autoridades para uso real pendientes según auditoría. |
| RF-25 · Generación de clasificación de postulantes | CU6 | Ranking | GET /ofertas/{id}/resultados | applications/offers | R06 | T-25 / test_t25_ranking_descending_and_offer_isolation (tests/Feature/SelectionTest.php:340) · PASS | Base DEMO probada; vigencia, escala y autoridades para uso real pendientes según auditoría. |
| RF-26 · Determinación de seleccionados según plazas | CU6 | Selección | POST /ofertas/{id}/aprobar | applications/publications | R06/R22 | T-26 / test_t26_approval_cannot_exceed_seats_or_repeat (tests/Feature/SelectionTest.php:352) · PASS | Aprobación DEMO con cupos; no nombramiento formal. |
| RF-27 · Detección de empates | CU6 | Nuevo examen de desempate | POST /ofertas/{id}/desempate; POST /desempates/{id}/resolver | tie_exams | R04 | T-27 / test_t27_tie_requires_new_exam_participants_results_and_commission (tests/Feature/SelectionTest.php:366) · PASS | Nuevo examen exclusivo con condiciones, resultados y acta; rondas sucesivas DEMO probadas en G28; firmas formales pendientes. |
| RF-28 · Consulta consolidada de resultados | CU8 | Consolidado | GET /ofertas/{id}/resultados | applications/publications | R06/R17 | T-28 / test_t28_consolidated_results_require_authority (tests/Feature/SelectionTest.php:383) · PASS | Consolidado por ámbito; distribución formal de responsabilidades institucionales pendiente. |
| RF-29 · Generación de reportes del proceso | CU8 | Reportes | GET /convocatorias/{id}/reporte/{pdf/xlsx} | applications/offers | R06/R17 | T-29 / test_t29_real_pdf_and_xlsx_export_with_permissions (tests/Feature/SelectionTest.php:392) · PASS | PDF/XLSX reales verificados; formato oficial CPD y reportes avanzados pendientes. |
| RF-30 · Auditoría y trazabilidad de calificaciones | CU7 | Auditoría | GET /convocatorias/{id}/auditoria; POST /postulaciones/{id}/rectificaciones; POST /rectificaciones/{id}/decision | audit_events/score_versions/correction_requests/result_revisions | R17 | T-30 / test_t30_correction_keeps_original_actor_before_after_and_reason (tests/Feature/SelectionTest.php:402) · PASS | G12–G24/G26–G31: versiones y snapshots protegidos por triggers; administradores de infraestructura siguen siendo privilegiados. |

## Equivalencia con el análisis antecedente

PDF de análisis, pp.9–10: RF-01 y RF-02 antiguos → RF-01 canónico; RF-03 antiguo → RF-02; RF-04 antiguo → RF-05; RF-05 antiguo → RF-03 y RF-04; RF-06 antiguo → RF-06. RF-07..RF-30 mantienen afinidad conceptual con los IDs canónicos, pero su redacción se contrasta por concepto, no se reemplaza el contrato. RF-17 del PDF incluye componentes además de áreas; estos se modelan como esquemas separados.

## Pruebas transversales ejecutadas

Los métodos test_t_x01..test_t_x08 verifican flujo HTTP, modo OFICIAL/calendario/cuenta inactiva, faltantes y snapshot, administrador sin acceso implícito, nota borrador/confirmada, edición versionada, bloqueo de autoelevación, XSS escapado y fórmulas XLSX como texto. Se añaden siete pruebas HTTP contra el servidor real (incluido CSRF), tres carreras de concurrencia con dos procesos, restauración de BD+archivos y exportaciones descargadas desde navegador.

Las evidencias detalladas están en `evidence/`. No se afirma prueba exhaustiva, auditoría externa de seguridad ni autorización institucional.

## Extensión verificada en 0.2

El contrato de 8 CU y RF-01..RF-30 no cambió. SHA-256 de CU_Y_RF_CANONICOS.md: `fc7bd40b944002fc698eb56234816de4b36ce4ee11486e335673edd8f17e11e2`.

| CU y RF ampliados | Evidencia ejecutable adicional |
|---|---|
| CU1/CU7, RF-01/RF-30 | GovernanceTest G01–G11, G25, G31: permisos efectivos, sesiones activas/antiguas, perfiles, último administrador, reasignación y versiones. |
| CU5, RF-16..RF-23/RF-30 | G07/G11/G12..G20/G27/G29: autor original, méritos y examen, aprobación separada, rechazo, decimales, faltantes y versiones. |
| CU6/CU7, RF-24..RF-28/RF-30 | G17..G21/G26/G28/G30: rankings, nuevo empate, nueva publicación, conservación y rondas sucesivas. |
| CU8, RF-28/RF-29 | G22/G29 y regresiones T28/T29: consolidados, solicitudes y reportes PDF/XLSX con revisión y alerta de publicación pendiente. |
| Integridad transversal | governance_concurrency.py: correcciones incompatibles, revocación/nota y renovación simultánea en procesos separados. verify_restore.py ficct_test: restauración con correcciones y versiones. |

Resultado final: 70 pruebas/229 aserciones, de las que 39 son las anteriores; además seis carreras entre procesos (tres originales y tres nuevas), controles HTTP y restauraciones. Véase evidence/tests.txt, phpunit.xml y los informes JSON. Las evidencias visuales y exportaciones 0.1 se conservan como antecedentes; no se presentan como una revisión visual nueva de todos los módulos.

## Extensión verificada en 0.3

Sin renumerar ni reescribir los 8 CU/RF canónicos. Contraste de originales heredados NOT_VERIFIED en esta continuación; ver 03-CONTRASTE_NORMATIVO_DEMO_0_3.md. No se crean requisitos normativos para cubrir huecos.

| CU y RF ampliados | Endpoints / prueba adicional |
|---|---|
| CU3/CU4/CU7, RF-07/RF-09..RF-14/RF-30 | GET /postulaciones/{id}/procesos; POST /postulaciones/{id}/subsanaciones; POST /subsanaciones/{id}/{archivo,enviar,revisar,vencer}. P01..P13/P25/P29/P30: snapshots, plazos, revisión completa y permisos. |
| CU3/CU6/CU7, RF-07/RF-25/RF-26/RF-30 | POST /postulaciones/{id}/participacion; POST /participacion/{id}/decision. P14..P22/P27/P32: retiro frente a renuncia, vacantes retenidas y nueva publicación. |
| CU6/CU7/CU8, RF-08/RF-26/RF-28/RF-30 | GET /adjudicaciones; POST /postulaciones/{id}/aceptacion. P23/P24/P26/P28/P31: múltiples adjudicaciones, manifestaciones por publicación y sin incompatibilidad implícita. |
| CU8, RF-28/RF-29 | Reportes PDF/XLSX conservan columnas anteriores y agregan participación y vacantes retenidas; T29 y participation-http.py. |
| Integridad transversal | participation_concurrency.py: cinco carreras adicionales. participation-restore.json: 21 tablas, documentos y nueve triggers restaurados. |

Resultado de ejecución 0.3: 102 pruebas / 311 aserciones, cero fallos. Distribución: 38 de selección + 31 de gobernanza + 32 de participación + 1 adicional. Las 70 de 0.2 siguen aprobando. Concurrencia: once carreras entre procesos, separadas del conteo PHPUnit. Alcances y limitaciones completos en DEMO_0_3_CAMBIOS_Y_VALIDACION.md.
