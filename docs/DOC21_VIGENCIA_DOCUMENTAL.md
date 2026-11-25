# DOC-21 — Vigencia documental y decisiones nuevas

Estado: corrección técnica local sobre Fase 2. Se mantiene `NO_GO_INSTITUCIONAL`.

## Reproducción

Se habilitó académicamente un expediente con documentos analizados. Al solicitar otro análisis, el documento regresó a pendiente. Antes de corregir, las dos regresiones D21 y D22 recibieron HTTP 200 al registrar una nota y calcular un resultado, cuando esperaban rechazo 422. La evidencia anterior a la corrección se conserva separada de la batería final aprobada.

## Comportamiento corregido

La habilitación académica registrada se conserva. Un estado técnico calculado e independiente informa `document_validation: {state: current|pending, ready: boolean}`. Una validación pendiente no constituye una decisión de inhabilitación ni una regla universitaria nueva.

Antes de una nueva operación dependiente, el dominio verifica las versiones del último expediente sellado, los documentos exigidos, el último análisis, su política y caducidad, la disponibilidad del archivo y su SHA-256. La comprobación se realiza dentro de la transacción de decisión, con bloqueo de las filas documentales hasta su cierre. Un análisis anterior limpio no sustituye un análisis posterior pendiente, bloqueado o fallido.

Se protegen el registro inicial de méritos, los borradores y confirmaciones de notas, el cálculo, la corrección directa, la aprobación de rectificaciones y las nuevas decisiones de desempate y publicación. Las decisiones sobre una oferta comprueban sus postulaciones activas, enviadas y académicamente habilitadas. Las lecturas de rankings históricos y los rechazos de propuestas permanecen disponibles; no se recalculan ni eliminan resultados por solicitar otro análisis.

Las operaciones rechazadas no modifican las notas, méritos, habilitaciones ni publicaciones. Un nuevo análisis válido puede restablecer la disponibilidad técnica sin emitir otra habilitación académica. Esto no reemplaza las autorizaciones ni los controles existentes de etapa, evaluador, comisión o autoridad.

## Interfaz y permisos

React y la vista anterior muestran «Validación documental pendiente» o «Validación documental vigente». Los formularios de méritos, notas y cálculo se ocultan cuando la comprobación no está vigente. El servidor repite siempre la comprobación; ocultar un botón no constituye la protección.

El evaluador y la autoridad reciben únicamente el estado técnico agregado, sin archivos privados, rutas, hashes, identificadores de análisis ni detalles de revisión adicionales. El evaluador solo recibe acciones de calificación cuando tiene asignaciones. La comprobación de lectura describe el instante consultado, no reserva la validez para una acción posterior.

## Verificación y alcance

- PostgreSQL: 270 pruebas y 1.675 aserciones aprobadas, tanto en el árbol de trabajo como en la copia de entrega. Se conservan los 255 casos anteriores y se agregan 15 ejecuciones de regresión D21–D29, incluidos siete escenarios D23.
- Interfaz: 31 pruebas React aprobadas y compilación completada.
- Regresiones: análisis pendiente, bloqueado, fallido, vencido, cambio de política, archivo ausente y alterado; recuperación con análisis limpio; preservación de publicaciones; rectificaciones; méritos; y análisis iniciado durante la lectura con reversión de la nota.
- Los registros de navegador, motor real, concurrencia y preservación se entregan por separado. Su alcance y resultados deben consultarse en cada evidencia; no constituyen certificación externa ni pruebas de carga.
- No se requieren migraciones. No se cambian las fórmulas de `Scores.php`, la configuración académica ni el contrato canónico de 8 CU y 30 RF.

## Repetición

Usar una base PostgreSQL de pruebas separada denominada `ficct_test`, nunca la base de demostración. Instalar dependencias con Composer y npm según el README. Configurar las variables de prueba mediante el entorno aislado descrito en `scripts/test_environment.py`; ejecutar PHPUnit y `npm test`, y compilar con `npm run build`. D21–D29 están en `tests/Feature/ApplicantDocumentSafetyTest.php`; sus analizadores sintéticos prueban el comportamiento del dominio, no certifican ClamAV.

La prueba de navegador utiliza una copia de la base y archivos, datos sintéticos y el adaptador real de ClamAV. Las credenciales, copias de base, firmas y procesos locales no forman parte del paquete público.

## Pendientes conservados

Servicio permanente al reiniciar, supervisión externa, aceptación EICAR, capacidad y carga, validación institucional de G01–G10 y autorización oficial. La demostración local no resuelve esos pendientes ni autoriza concursos reales. No se publica esta fase en el servidor docente.

## Evidencias ejecutadas en esta intervención

El recorrido de navegador completó 14 comprobaciones y 68 solicitudes HTTP explícitas. ClamAV analizó tres versiones limpias (incluido el nuevo análisis) y bloqueó una firma adicional inocua. Se confirmó bloqueo 422 de nota y cálculo pendientes, recuperación al analizar y resultado 68/100. PostgreSQL observó un análisis concurrente esperando al cierre de Selection::calculate. La copia aislada se restauró con 49 tablas, 16 triggers y 42 archivos privados. La actualización operativa preservó idénticas sus 49 tablas y 35 archivos privados.

El supervisor local se confirmó activo mediante identidad de proceso y latido reciente. Sigue pendiente su instalación como servicio permanente. Las capturas del evaluador pendiente y móvil fueron inspeccionadas visualmente. No hubo despliegue remoto.
