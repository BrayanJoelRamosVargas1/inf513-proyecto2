# Adecuación institucional del sistema existente · 25/11/2026

Se mantiene la misma aplicación Laravel/React/PostgreSQL. El contrato canónico de 8 CU y 30 RF conserva su SHA-256 fc7bd40b944002fc698eb56234816de4b36ce4ee11486e335673edd8f17e11e2. No se publicaron cambios remotos en esta fase.

## Cambios comprobables

- Catálogo documental: 49 asignaturas distintas, 49 relaciones en 01/2026 y 17 en 02/2026. Las plazas individuales no publicadas permanecen NULL. Las convocatorias históricas están archivadas; no se reabren ni se infieren ganadores o vacantes por ausencia de resultados importados.
- Tres PDF originales con SHA-256 del inventario. No se sustituyen por plantillas. Se conserva el conflicto de fechas impreso de salas en la primera convocatoria.
- Requisitos por modalidad integrados en configuración y revisión individual. Una excepción «no aplica» necesita criterio condicional y fundamento. La aplicación no consulta automáticamente DICAA ni verifica autenticidad institucional.
- Registro append-only de comisiones con oferta, integrantes y vigencia; calendarios; actos; plantillas; planes de examen y respaldos CPD. Las referencias reciben versiones; la concurrencia obsoleta se rechaza. Registrar una comisión no concede permisos académicos.
- CPD: documento vigente, huella, versión, componentes y diferencias fundamentadas. No sustituye el mérito registrado ni calcula rúbricas contradictorias. Los campos exactos de los formularios auténticos siguen pendientes de recepción.
- Constancia interna A4 tras enviar el expediente: identidad, convocatoria, oferta y huella del snapshot. Sin firmas, sellos ni apariencia de habilitación o nombramiento.
- Muestras retiradas de la ayuda general; disponibles solo en entrenamiento para postulantes. El servidor bloquea muestras y simulación en modo institucional. El modo institucional rechaza una base mezclada con convocatorias DEMO o cuentas .test. El seeder institucional exige una base nueva y no crea usuarios ficticios.
- CSV del registro y reportes PDF/XLSX incluyen evidencia documental y calendarios. El CSV no exporta componentes CPD privados. Los reportes heredados distinguen referencias históricas de datos sintéticos e identifican la convocatoria.

## Verificación y límites

Evidencias actuales en evidence/adecuacion-institucional-20261125. El conteo definitivo de PHPUnit y React está en evidence/current-validation.json. No sumar las ejecuciones históricas como si fueran pruebas nuevas.

Recorrido real en navegador sobre base clonada y almacenamiento separados: cinco perfiles, comisión, calendario, formato pendiente, acto, plan, carga/envío, descarga idéntica por postulante y revisor, CPD estructurado y recarga. Evaluador sin documentos ni CPD; administrador sin atribuciones académicas. Catálogos de ambas convocatorias y búsqueda móvil a 390 px comprobados. No es una certificación exhaustiva de accesibilidad ni una repetición visual de cada acción histórica.

ClamAV real: el ensayo detectó firmas vencidas y conservó el bloqueo. Freshclam actualizó las firmas oficiales; después el archivo limpio fue aceptado. Se conserva firma inocua adicional del laboratorio. No se amplió la tolerancia de antigüedad. Esta comprobación no certifica el antivirus remoto.

La auditoría HTTP de descargas comprueba fuentes originales, muestras separadas, documentos privados y permisos, constancias, respaldos institucionales, CSV y reportes actuales/heredados. Los documentos generados se contrastaron con PostgreSQL; la constancia y los reportes se renderizaron. Las rutas equivalentes de vista/descarga conservan sus controladores compartidos.

Preservación: valores originales de las 50 tablas previas y 35 archivos privados conservados; catálogo añadido de forma aditiva. Restauración final de la copia de ensayo: 54 tablas, 18 triggers y 74 archivos privados idénticos, incluidos archivos temporales de trabajo del laboratorio. Ese número no representa 74 documentos institucionales originales.

Las cuatro tablas nuevas son institutional_subjects, institutional_call_profiles, institutional_subject_offers e institutional_register. Claves foráneas y unicidad conservadas en restauracion-final.json. Los dos triggers añadidos impiden cupos desconocidos fuera del archivo documental e impiden actualizar/eliminar el registro versionado. No se prueba que un administrador de infraestructura no pueda alterar su propio servidor.

## Contratos y solicitudes

02-TRAZABILIDAD.csv y Markdown: 30 RF sincronizados y métodos presentes en el XML actual. MATRIZ_NORMATIVA_ACTUAL.json/MD conserva los 82 artículos, evaluación anterior, adenda técnica y dependencias por artículo. No declara 82 funcionalidades implementadas. Las matrices actuales de CU/RF/INF513 mantienen la evidencia histórica separada. El inventario cuenta 137 acciones: 123 anteriores y 14 de adecuación documental.

SOLICITUDES_INSTITUCIONALES.csv/MD reúne 20 solicitudes propuestas, sin enviar: formularios CPD por modalidad, rúbricas y decimales, umbral final, composición del examen, cupos y horas, fechas, comisión, modelos de actos, recepción digital, subsanación, renuncias y múltiples adjudicaciones, entre otras. Solicitar modelos vacíos o anonimizados; no credenciales ni bases privadas.

## Instalación y uso

FICCT_EXPERIENCE=training conserva la DEMO y sus datos. FICCT_EXPERIENCE=institutional exige otra base, cuentas reales autorizadas y almacenamiento privado independiente. No cambie el valor sobre la base DEMO para aparentar una instalación institucional. En base nueva, DatabaseSeeder selecciona referencias históricas sin usuarios de prueba. Las cuentas iniciales requieren aprovisionamiento administrativo autorizado; no se incluyen contraseñas universales.

El backend mantiene bloqueadas las operaciones oficiales: la instalación institucional es preparación documental, no una convocatoria oficial ejecutable. Las decisiones pendientes no se completan con valores inventados. NO_GO_INSTITUCIONAL se conserva hasta aceptación competente.

Sincronización local reproducible: scripts/sync_runtime.py verifica hashes y compilación, guarda respaldo y no escribe .env, almacenamiento o base. No confundir el runtime privado con una segunda aplicación entregable.

El candidato se genera con scripts/package_academic_release.py en ENTREGA_INF513_FUENTES_20261125, conservando el tarball anterior. La instalación desde cero del hash exacto queda en la evidencia externa installation.json para evitar circularidad del hash. Su resultado debe leerse directamente: este documento no presume que haya pasado antes de ejecutarlo.

## Pendientes reales

Formularios auténticos, decisiones normativas y autorización institucional; infraestructura y antivirus remotos; aceptación sostenida de carga y seguridad; publicación autorizada de este candidato en GitHub/Tecnoweb. No se implementan planillas, asistencia, becas o recursos humanos fuera del alcance. Los pagos de INF513 siguen siendo una simulación académica.
