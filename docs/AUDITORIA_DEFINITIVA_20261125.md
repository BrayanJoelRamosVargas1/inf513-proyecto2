# Auditoría local de código, documentos y roles · 25/11/2026

Se corrigieron defectos comprobados en el mismo proyecto. Esta entrega **no cierra todavía la revisión manual de todos los archivos propios** ni acredita operación institucional. El inventario distingue los archivos realmente leídos de los que siguen pendientes. No se convierte una ejecución de pruebas en una afirmación de revisión humana completa.

## Cambios realizados y motivos

1. **Bandejas de subsanación.** El filtro del postulante solo consideraba borradores; el del revisor confundía una corrección que esperaba al estudiante con una revisión pendiente. La API ahora informa la tarea correspondiente: completar borrador, subsanar, revisar nueva versión o resolver plazo vencido. React filtra con esa información y lleva directamente a Participación cuando corresponde. No cambia habilitación, plazo ni permiso académico.
2. **Recorrido DEMO incompleto.** `DemoJourney` devolvía el identificador de una ejecución anterior aunque hubiera quedado interrumpida por un análisis fallido. Ahora exige publicación y desempate resuelto de la revisión vigente para informar que ya fue ejecutado. Conserva el trabajo parcial y devuelve un error explícito; no borra expedientes ni crea otra convocatoria para ocultar el fallo. Los dos comandos de recorridos requieren experiencia de entrenamiento y entorno local.
3. **Limpieza comprobada.** Se retiraron 15 reglas CSS del panel decorativo ya eliminado: selectores `dashboard-hero`, `hero-actions`, `hero-companion` y `hero-seal`. Se quitaron tres importaciones nombradas sin referencias en `main.jsx`: `createContext`, `Navigate` y `useSearchParams`. Se eliminó el comando de ejemplo `inspire` y su importación: no tenía referencias en la aplicación, pruebas o documentación. El ranking reutiliza la primera lectura de la oferta en lugar de consultarla dos veces seguidas.
4. **Ejecución de pruebas React.** El arranque simultáneo de numerosos entornos jsdom produjo siete tiempos de espera de 5 segundos. Las mismas 72 pruebas pasaron con dos trabajadores. Esa concurrencia quedó limitada en la configuración; no se ampliaron tiempos límite ni se eliminaron aserciones.
5. **Documentación existente.** RF-12 se actualizó de forma coincidente en CSV y Markdown. La matriz normativa conserva sus 82 artículos y las clasificaciones anteriores, con una explicación individual nueva de sus 40 artículos parciales. SOL-20 incorpora una discrepancia adicional; siguen siendo veinte solicitudes y ninguna fue enviada.

Los archivos de pruebas, migraciones, fuentes, evidencias históricas y respaldos se conservan. Las vistas Blade y sus controladores siguen teniendo rutas y adaptadores que los utilizan: no se eliminaron por parecer antiguos. Los scripts operativos y de reproducción no se confunden con archivos públicos del frontend.

## Resultados de ejecución de esta intervención

| Comprobación | Resultado y alcance |
| --- | --- |
| PHPUnit, instancia PostgreSQL de prueba independiente | 347 pruebas, 2.150 aserciones, cero fallos y errores; incluye los identificadores de las 341 pruebas del candidato anterior |
| React | 72 pruebas aprobadas; 16 archivos de pruebas; ejecución limitada a dos trabajadores |
| Compilación | Laravel/Vite/React compilados; recursos y fuentes relacionados mediante huellas |
| Navegación y presentación | Cinco perfiles; 30 combinaciones de anchura, modo y tamaño de texto; comprobación automática de accesibilidad en cinco páginas de inicio, sin equivaler a certificación WCAG de todo el sitio |
| Recorrido documental por navegador | Postulación, seis cargas PDF, cuarentena, descarga exacta por propietario y revisor, rechazo de otros perfiles, observación, plazo, nueva versión, envío y revisión completa |
| Continuación del escenario | Mérito 20,10; nota Software 83,00; examen 49,80; final 69,90; publicación DEMO y conservación de una plaza sin cubrir |
| Descargas adicionales | 16 variantes por HTTP y una vista previa; PDF, XLSX y CSV abiertos y examinados según su finalidad |
| Restauración del respaldo operativo | 54 tablas idénticas al respaldo inicial y 18 triggers recuperados en otra base; no se restauró sobre la DEMO |
| Dependencias | Las consultas npm y Composer ejecutadas no reportaron avisos de vulnerabilidad; no constituyen una prueba de penetración |
| Contrato | SHA-256 de los 8 CU / 30 RF sin cambios |

Las pruebas nuevas PHP cubren la distinción de tareas de subsanación, plazo vencido y vigencia del permiso, el falso éxito del comando incompleto, el reintento después de un fallo del analizador, la repetición de un recorrido ya completado y el rechazo del modo institucional. Las dos nuevas pruebas React cubren los enlaces y filtros de ambas bandejas. Los resultados fallidos anteriores a la corrección se conservan en la evidencia privada.

Los escenarios de navegador emplearon la misma fuente de aplicación con otra base, cachés, sesiones y almacenamiento privados. El motor ClamAV fue real; utilizó firmas de ensayo aisladas. Esto no certifica firmas, servicio permanente o capacidad antivirus del servidor docente. Los errores de etiqueta y URL encontrados en dos intentos del guion se corrigieron en el instrumento, conservando su registro; no se añadieron rutas ficticias al producto para hacerlos pasar.

## Qué corresponde a cada perfil

| Perfil | Responsabilidad y límites |
| --- | --- |
| Postulante | Registra su cuenta, consulta convocatorias, crea postulaciones propias, carga y descarga sus originales, confirma envío, atiende subsanaciones autorizadas y consulta resultados y manifestaciones sobre sus plazas. No se habilita ni se califica a sí mismo. |
| Revisor | Dentro de convocatorias con permiso vigente, consulta documentos, registra cada criterio y fundamento, habilita/inhabilita, conserva respaldo CPD y revisa las nuevas versiones. No hereda calificación de áreas ni aprobación de plazas. |
| Evaluador | Consulta sus ofertas y áreas asignadas; registra borradores y confirma sus notas; solicita rectificación mediante el circuito autorizado. No descarga expedientes privados por el solo rol de evaluador. |
| Autoridad | Configura y conduce sus convocatorias, asigna evaluadores, decide solicitudes y rectificaciones con separación de responsables, consulta clasificación, gestiona desempates y aprueba publicaciones DEMO con acta. No obtiene por ese rol descarga de documentos personales. |
| Administrador técnico | Gestiona cuentas, perfiles, catálogos y permisos con vigencia y revocación. No se convierte automáticamente en evaluador, revisor o autoridad académica. |

Una persona puede tener responsabilidades diferentes por convocatoria. Ver dos bandejas en ese caso es correcto si cada una tiene su ámbito. El perfil base y un permiso académico vigente son conceptos distintos. Los pagos de INF513 permanecen como simulación separada: no condicionan postulación, examen ni plazas.

## Contraste de los tres PDF

Se recalcularon las tres huellas y se renderizaron e inspeccionaron las 42 páginas físicas: resolución 29, primera convocatoria 7 y segunda 6. La resolución repite contenido de artículos 5, 6 y parte de 7 en las páginas físicas 5 y 6; no se añaden artículos por esa repetición. La vigencia jurídica actual no está certificada.

- Se conservan las 49 asignaturas distintas y 66 relaciones históricas: 49 en 01/2026 y 17 en 02/2026. Los saltos de numeración no crean plazas. Las plazas por asignatura no especificadas continúan sin inventarse.
- Primera convocatoria: salas 20, Redes 3, Software 6, Electrónica 3 y laboratorio 2. Segunda: salas 13, Software 3 y laboratorio 1. No se inventan ofertas de Redes o Electrónica en la segunda.
- Las matrices de áreas son 50/25/25 para sala; 25/75 para Redes; Software100; Electrónica25/75, con los ámbitos originales por convocatoria. No se usa una matriz global para todas las ofertas.
- Docencia: examen escrito36/práctico24. Laboratorio de servicio: práctico36/teórico24. Méritos40 y examen60 no autorizan a mezclar automáticamente áreas y componentes pendientes de homologación.
- Los exámenes exclusivos de desempate están respaldados por docencia18, página10, y laboratorio17, página24. Sus condiciones y decisión corresponden a la comisión; el sistema conserva resultados y acta.
- El PPA mínimo de postulación no se convierte en nota mínima final del concurso. La discrepancia PPA25%/10 puntos de laboratorio, página22, permanece sin fórmula inventada.
- La fecha de examen de sala/laboratorio 3–5 de enero de la primera convocatoria se conserva como conflicto frente al cierre del 30 de enero, sin corregirla unilateralmente a febrero.
- **Hallazgo adicional:** la condición de titulación en mora del artículo7(e) incluye salas en la resolución, pero las secciones de salas de ambas convocatorias la omiten. El perfil documental de sala conserva esa omisión. Se amplió SOL-20 para aclarar su aplicación, sin declarar derogada la resolución ni alterar referencias históricas.

La explicación de cada artículo parcial está en `MATRIZ_NORMATIVA_ACTUAL.json` y en la sección de relectura de `MATRIZ_NORMATIVA_ACTUAL.md`. No se cambiaron las clasificaciones a “cumplimiento íntegro” por existir una pantalla.

## Respuestas concretas al cierre solicitado

| Pregunta | Respuesta |
| --- | --- |
| 1. ¿Qué código innecesario apareció? | Estilos del panel retirado, tres importaciones sin uso, comando motivacional de ejemplo y una consulta repetida de oferta. |
| 2. ¿Qué se eliminó y por qué? | Solo lo enumerado arriba, respaldado y con referencias comprobadas. No se eliminaron controladores antiguos con rutas vigentes. |
| 3. ¿Qué se simplificó? | Una lectura de oferta por ranking; selección de tareas de bandeja a partir de su estado real. |
| 4. ¿Qué defectos se corrigieron? | Omisión de subsanaciones en pendientes, confusión de tareas del revisor y falso éxito del recorrido DEMO incompleto. |
| 5–6. ¿Qué estaba incompleto y se completó? | Acceso desde las bandejas al circuito de subsanación y comprobación del cierre del recorrido CLI; se conservan los procesos académicos existentes. |
| 7. ¿Para qué sirve cada rol? | La tabla anterior detalla responsabilidad, ámbito y límites; los cinco perfiles se probaron en navegador. |
| 8. ¿Qué partes de los PDF están representadas? | Catálogos, requisitos por modalidad, documentos, calendarios de referencia, pesos, componentes, registro CPD, comisión/actos, clasificación y desempate, con límites explícitos. |
| 9. ¿Qué falta? | Formatos auténticos, homologaciones, umbral/redondeo, acreditaciones, recepción digital válida y partes de operación oficial. Además queda revisión manual de archivos inventariados: no es solo un pendiente universitario. |
| 10. ¿Qué es técnico y no meramente externo? | Carga horaria estructurada ht/hp por oferta, validación automática de composición de comisión y alertas institucionales24/48h no están implementadas como controles operativos. No se presentan como resueltas por el registro descriptivo. Su alcance, evento inicial y datos oficiales deben precisarse antes de ampliar el motor. |
| 11–12. ¿Qué formatos y a quién pedir? | SOL-01/02/03: CPD por asignatura/sala/laboratorio; SOL-08/14: comisión; SOL-11/12/13/16/17: actos, resultados, desempate y designación; SOL-15: recepción; SOL-20: matrícula, historial y autonomía. La unidad competente debe confirmar emisión y validez. Son borradores, no solicitudes enviadas. |
| 13. ¿Qué reglas requieren aclaración? | Franjas y decimales CPD, porcentaje/puntos de PPA laboratorio, composición de áreas/componentes, umbral, fechas contradictorias, terna de laboratorio, titulación en mora para salas y procedimientos aún no confirmados de aceptación/subsanación. |
| 14. ¿Funcionan todas las descargas? | Pasaron las variantes y permisos del escenario descrito; se verificaron bytes originales y contenido de reportes. No es una afirmación sobre cualquier archivo futuro o toda combinación posible. |
| 15. ¿Los cálculos coinciden? | El esquema DEMO explícito y el caso69,90 se verificaron; las fórmulas institucionales ambiguas siguen bloqueadas, no homologadas. |
| 16. ¿Hay seguridad verificada? | Cuarentena, integridad, permisos y preservación cuentan con pruebas y recorrido; no se certifica ausencia de vulnerabilidades ni aceptación remota/SCM. |
| 17–18. ¿Se cumplen 8 CU / 30 RF? | Contrato intacto, treinta filas de trazabilidad y regresión ejecutada. Su cobertura técnica no equivale a cierre institucional de todos los subflujos. |
| 19. ¿Se cumple INF513? | Stack, API, roles, menú de BD, auditoría, temas, accesibilidad, visitas, métricas, búsqueda y simulación están implementados y tienen pruebas. Defensa en el servidor docente y entrega remota de esta versión siguen pendientes. |
| 20. ¿Qué queda para desplegar y defender? | Aceptación del artefacto exacto, completar la revisión pendiente, acceso seguro a Cockpit, capacidades de procesamiento, respaldo/migración remotos y recorrido público de los perfiles. Esta auditoría no autoriza publicar la nueva versión. |

## Conservación y límites de la evidencia

Se tomó un respaldo antes de las modificaciones y se restauró en otra base: 54 tablas y 18 triggers. Los 69 archivos privados del estado inicial conservaron su huella, así como los registros originales de postulaciones y documentos y el contrato canónico.

La DEMO siguió disponible. Durante la auditoría aparecieron una postulación y documentación nuevas y una preferencia visual de una cuenta distinta de los cinco perfiles usados en la revisión. También aumentó la telemetría normal. Esa actividad concurrente fue conservada; **no se afirma que las 54 tablas hayan permanecido idénticas** ni se revirtieron cambios para conseguir un resultado verde.

El inventario `evidence/adecuacion-institucional-20261125/auditoria-definitiva/inventario-revision.json` indica el alcance manual por archivo. `full_line_by_line_audit_complete=false` identifica que sigue pendiente el cierre exhaustivo, especialmente herramientas históricas, pruebas y vistas no revisadas completamente en esta intervención. Los XML y comprobaciones de sintaxis no cambian ese estado.

La evidencia de esta intervención está en `evidence/adecuacion-institucional-20261125/auditoria-definitiva/`. Las configuraciones privadas, credenciales, bases y originales del respaldo quedan en `.local/auditoria-definitiva-20261125`, fuera del paquete. El candidato previo y sus evidencias no fueron sustituidos. El tarball corregido, si supera la aceptación independiente, queda en `ENTREGA_INF513_AUDITORIA_20261125`; su recibo externo de instalación debe cotejarse con su SHA-256, evitando incluir una referencia circular a su propio hash.

`NO_GO_INSTITUCIONAL` significa que esta preparación técnica y los procesos DEMO no autorizan concursos ni nombramientos oficiales. No es un fallo del inicio de sesión ni impide demostrar las operaciones de práctica. No se han realizado cambios remotos en GitHub o TECNOWEB durante esta intervención.
