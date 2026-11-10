# Revisión técnica de la versión DEMO 0.1

La evidencia corresponde a los archivos de este paquete. Los scripts y salidas se incluyen para reproducción. Esta revisión no constituye pentest externo ni certificación de ausencia de vulnerabilidades.

## Controles comprobados

| Riesgo | Control y prueba | Resultado |
|---|---|---|
| Autoelevación de rol o acceso ajeno | Autorregistro solo student, membresías por convocatoria, admin sin acceso implícito; T01, T11, T19, T20, T28, TX04, TX07 | PASS |
| Examen sin habilitación | Estado validado en dominio, no solo UI; T13/T14 | PASS |
| Nota ajena, inválida o sobrescrita | Asignación por identidad, decimales/rangos, confirmación/rectificación; T16/T20/T22/T30/TX05 | PASS |
| Cálculo incompleto y fórmulas inferidas | NULL diferente de cero; esquema/escala/redondeo identificados; T18/T21/T23/T24/TX03 | PASS en fixture DEMO |
| Exceso de límite/cupos por carrera concurrente | Bloqueos transaccionales con orden consistente, unicidad y dos procesos reales | PASS |
| Ganador arbitrario en empate | Bloqueo de aprobación hasta nuevo examen exclusivo, resultados y acta; T27 | PASS |
| Alteración de publicaciones | Snapshot conservado y rectificaciones posteriores bloqueadas; TX03 | PASS |
| Archivos públicos/ejecutables | Almacenamiento privado, descarga autorizada, MIME/extensión/tamaño; T09/T11 | PASS para casos ensayados |
| XSS y fórmula XLSX | Escape Blade, CSP, escritura explícita como texto; TX08 | PASS |
| CSRF y exposición de configuración | Middleware real activo y servidor con raíz public; security_http.py | PASS |
| Pérdida de BD o archivos | pg_dump/pg_restore en base nueva y copia con checksum; verify_restore.py | PASS |

## Hallazgos corregidos durante implementación

| Hallazgo | Impacto | Corrección | Evidencia |
|---|---|---|---|
| Defaults de cuenta no reflejados inmediatamente en el objeto recién creado | Impedía preparar la DEMO | Defaults explícitos y active boolean | Seed y acceso real ejecutados |
| Sintaxis compacta no aceptada en bucles Blade | Pantallas no compilaban | Plantillas corregidas y compiladas | view:cache y pruebas HTTP |
| La vista marcaba empate pendiente aunque ya existiera acta resuelta | Información incorrecta a autoridad | Distinguir empate original de nuevo examen resuelto | Pantalla real y captura de consolidado |
| Mutaciones de cálculo y selección con orden de bloqueo inconsistente | Riesgo de snapshot incoherente en concurrencia | Bloqueo de convocatoria antes de postulación/oferta/nota | concurrency.json |
| Validar solo el código de salida de un worker CLI no garantizaba setup completo | Posible ensayo inválido ante fallo de entorno | Confirmar señal READY antes de iniciar procesos | Script de concurrencia actualizado |

## Riesgos y limitaciones vigentes

El usuario de la BD de desarrollo es dueño del cluster aislado; no usarlo en producción. El servidor PHP no proporciona la disponibilidad/HTTPS/aislamiento exigibles a un servicio institucional. Antimalware, retención, monitoreo, firma de actas y protección ante administradores de infraestructura no están certificados. No hay endpoint de borrado ordinario de auditoría, pero un administrador de BD puede alterar registros: no se promete inmutabilidad absoluta.

La carga inicial y las pruebas pasan en este equipo; no se infiere compatibilidad con cualquier sistema operativo o versión. Un test por RF demuestra el escenario indicado y sus rechazos, no todas las interpretaciones posibles del requisito. Los pendientes técnicos de `ESTADO_PROYECTO.md` son trabajo real pendiente y no se ocultan detrás de validaciones normativas.
