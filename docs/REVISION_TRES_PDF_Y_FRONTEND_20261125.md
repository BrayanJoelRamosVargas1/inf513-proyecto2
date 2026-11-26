# Revisión de tres fuentes originales y mejora del frontend

Fecha: 25/11/2026 · Sistema existente FICCT/UAGRM, Grupo 09.

## Resultado principal

Las fuentes son documentos institucionales reales. El sistema contiene una referencia histórica parcial de sus datos y procesos de demostración distintos de esas convocatorias. No está implementado todo el contenido de los tres PDF. Las cifras anteriores de 7/8 CU y 29/30 RF describen el contrato funcional y sus pruebas técnicas, no un porcentaje de cumplimiento integral de los reglamentos.

Se leyeron las 42 páginas físicas: resolución escaneada de 29 páginas, primera convocatoria de 7 y segunda de 6. Se extrajo el texto de ambas convocatorias; para la resolución se contrastó OCR anterior contra el binario idéntico y se revisaron las páginas renderizadas, especialmente las tablas de méritos, las ponderaciones y los desempates. Las páginas 5 y 6 del escaneo repiten contenido: no se cuentan como nuevos artículos. Los tres originales permanecen intactos.

La comprobación de datos se realizó en la base local operativa del proyecto, mediante transacciones de solo lectura. No constituye una consulta nueva a PostgreSQL de Tecnoweb. El rediseño se implementó en la misma carpeta sistema y se sincronizó al runtime local existente; no se sustituyó la aplicación por otro proyecto.

## 1. Resolución Vicerrectoral 021/2023

**Estructura:** 4 artículos dispositivos, reglamento de docencia de 40 artículos y reglamento de laboratorio de 38 artículos. Son 82 artículos distintos, no 82 requisitos de software. La resolución incluye selección y actividades posteriores al nombramiento; estas últimas exceden el contrato original de selección.

### Reglas identificadas y contraste actual

| Tema y página física | Lo que establece la fuente | Estado en el sistema |
|---|---|---|
| Regímenes: pp. 4 y 19 | Docencia por asignatura/sala y laboratorio de servicio académico/social son modalidades diferentes. | Categorías con régimen docencia/laboratorio. Sala de cómputo está correctamente dentro de docencia; el laboratorio de servicio se representa de forma genérica, sin todo el detalle institucional. |
| Admisión docencia, art. 7, pp. 5–7 | Matrícula activa, permanencia hasta 8 años, límites de reprobación, ausencia de otros beneficios y CPD actualizado; asignatura con antigüedad mínima, aprobación regular, 80 % de su nivel y niveles inferiores aprobados. | Revisión y habilitación por criterios configurables. Los datos históricos contienen un criterio genérico, no cada requisito desglosado ni integración automática con el registro académico. |
| Sala, art. 7, p. 7 | Dos años/cuatro semestres vencidos y máximo dos años de postulación. | Puede configurarse un criterio manual; no hay comprobación institucional de toda la trayectoria. |
| Contenido/publicación, arts. 8–10, p. 7 | Sigla, plazas, carga horaria, periodo, requisitos, recepción y examen; aprobación/publicación por autoridades correspondientes. | Convocatorias, ofertas, plazas y etapas existen. Faltan campos y controles oficiales completos para carga horaria, periodos, resolución y circuito HCF/DICAA. |
| CPD docencia, arts. 11–13, pp. 8–9 | Formulario oficial como respaldo de méritos, máximo 40 puntos y rúbricas diferenciadas para materia/sala. | Archivo, referencia y puntaje manual con límite. No emisión/verificación contra CPD ni cálculo institucional íntegro de rúbricas. |
| Comisión docencia, art. 14, p. 9 | Composición según asignatura/sala y autoridades responsables; veedores acreditados. | Permisos por convocatoria y evaluadores por área. No validación de la composición formal, acreditaciones ni documentos de nombramiento de toda la comisión. |
| Examen docencia, arts. 15–17, pp. 9–10 | Escrito/práctico, exposición máxima de 30 minutos (20+10), examen 60 puntos y méritos 40; escrito 36 y práctico 24. | Notas, componentes y resultado global soportados. El esquema DEMO de asignatura refleja 60/40 entre componentes; no registra todo el sorteo/exposición ni certifica composición institucional de áreas en salas. |
| Empate docencia, art. 18, p. 10 | Nuevo examen exclusivo para empatados por las últimas plazas, condiciones de comisión. | Detección en corte, participantes, condiciones, fecha, resultados, decisión y acta; no selección automática por fórmula inventada. Procedimiento técnico DEMO, pendiente aceptación oficial. |
| Informe/publicación, arts. 17–19, pp. 10–11 | Publicación dentro de 48 h, informe dentro de 24 h y resolución del HCF. | Publicaciones versionadas, aprobación y reportes técnicos. No seguimiento íntegro de plazos, firma oficial ni circuito HCF/CPD/DICAA/Desarrollo Humano. |
| Vacantes/desiertas, arts. 20 y 25, pp. 11–14 | Causas específicas y procedimiento tras convocatorias consecutivas, actas y decisiones de autoridades. | Renuncias y vacantes retenidas, sin reemplazo automático. No expediente administrativo completo de desiertas, excepciones, invitaciones y nombramiento. |
| Admisión laboratorio, art. 6, pp. 20–21 | Plan aprobado salvo titulación, PPA ≥55, no ejercicio anterior del cargo, límites de permanencia y otras condiciones. | Revisión manual genérica configurable. No verificación automática de histórico académico, titulación y ejercicio previo. |
| CPD laboratorio, arts. 10–12, pp. 21–22 | Méritos máximo 40: permanencia 20, tiempo habilitado a tesis 5 y PPA 15. | Puntaje manual respaldado; rúbrica estructurada/integración oficial pendiente. |
| Examen laboratorio, arts. 13–16, p. 23 | Comisión designada por Decano; práctico 36/60 y teórico 24/60; total méritos+examen=100. | Configuración DEMO de laboratorio conserva práctico 60 %/teórico 40 % y total 40+60; no acredita la comisión y rúbrica institucional completas. |
| Empate laboratorio, art. 17, p. 24 | Nuevo examen exclusivo para empatados, bajo condiciones de la comisión. | Mismo motor de desempate soporta el procedimiento técnico; no sustituye su autorización. |
| Nombramiento laboratorio, arts. 18–23, pp. 24–25 | Informe, resolución de Decanato, designación y duración, con plazo de nombramiento de hasta 15 días. | Adjudicación/publicación DEMO no equivalen a nombramiento oficial. Circuito documental y control de plazos pendientes. |
| Vida del cargo: docencia arts. 21–40 y laboratorio 22–38 | Asistencia, licencias/suspensión, informes mensuales, horarios, planillas, beca, programación y obligaciones. | No es un sistema de gestión laboral/becas. Gran parte está fuera de los 8 CU aprobados. El módulo de pagos INF513 es simulado y no paga becas universitarias. |

### Problemas de interpretación que no deben automatizarse

- **Puntaje aprobatorio final:** la resolución menciona declaratorias desiertas por no alcanzar aprobación, pero no basta tomar PPA 51 o 55 como nota mínima final del concurso. `Selection::ranking()` ordena habilitados con nota; no aplica un umbral final institucional confirmado.
- **Rúbrica de laboratorio, p. 22:** la tabla imprime PPA 51–69: 25 % y 10 puntos; 70–79: 50 % y 11; 80–89: 75 % y 13, sobre un componente máximo de 15. Los tres porcentajes no equivalen aritméticamente a esos puntos. También hay extremos compartidos en los tramos semestrales de Técnico Superior. Se requiere criterio del CPD/autoridad; no se corrigió la tabla ni se inventó una fórmula.
- **Áreas y componentes:** las convocatorias ponderan áreas (Soporte/Redes/Software/Electrónica) y el reglamento distingue escrito/práctico. No establecen por sí solos cómo combinar ambos ejes en cada sala. Las ofertas históricas usan `PENDING_COMPOSITION`, precisamente para no presentar como homologada una composición DEMO.
- **Competencia institucional:** un rol técnico denominado Autoridad no demuestra por sí solo facultades de Decano, Vicedecano o HCF. Deben acompañarse las asignaciones, competencias y respaldos institucionales.

## 2. Primera convocatoria 01/2026 — 7 páginas

### Datos reales identificados

| Bloque | Datos del PDF | Representación actual |
|---|---|---|
| Docencia por asignatura, pp. 1–3 | 49 asignaturas, aunque la numeración impresa llega a 53 con saltos. Modalidades presencial/virtual; máximo 4 asignaturas. | **En el corte de este documento, 0/49 ofertas históricas por asignatura cargadas (superado el 25/11/2026: la importación documental carga 49 asignaturas de la convocatoria 01 y 17 de la 02, con 66 relaciones y plazas sin especificar, porque el PDF no las da).** Las materias ficticias DEMO no se cuentan como ofertas de este documento. El límite configurable de cuatro asignaturas sí existe en el motor. |
| Admisión por materia, p. 1 | Antigüedad de un año/dos semestres, materia aprobada ≥51 en periodo regular, 80 % del nivel y niveles inferiores, reprobación específica, PPA ≥51, además de generales. | Motor de revisión disponible; catálogo de criterios históricos incompleto y sin conexión automática a datos académicos institucionales. |
| Documentos, pp. 1–2 | Carta a Vicedecano con sigla/nombre, CPD, CI, histórico y certificado de autonomía; presentación física foliada en folder amarillo. | Tipos básicos de documentos disponibles, originales digitales/versiones y revisión privada. El envío digital no se declara sustituto autorizado del trámite físico. |
| Fechas docencia, p. 2 | Cierre 30/01/2026 15:30; examen 2–3/02; resultados 5/02; asignación de horarios 9/02. | Cierre histórico correcto; no calendario independiente íntegro por modalidad ni plazos de publicación/horarios estructurados. |
| Salas, pp. 4–5 | 32 plazas: sala 20, redes 3, software 6, electrónica 3. | **4/4 filas y plazas coinciden** en datos históricos. |
| Laboratorio de servicio, pp. 6–7 | 2 plazas; PPA ≥55, plan concluido salvo titulación y no ejercicio anterior. | Plazas correctas; criterio histórico genérico, faltan validaciones específicas y detalle del laboratorio/cargo cuando corresponda. |

Matriz de salas transcrita y contrastada:

| Oferta | Soporte | Redes | Software | Electrónica | Plazas |
|---|---:|---:|---:|---:|---:|
| Sala de cómputo | 50 % | 25 % | 25 % | — | 20 |
| Redes | 25 % | 75 % | — | — | 3 |
| Software | — | — | 100 % | — | 6 |
| Electrónica | 25 % | — | — | 75 % | 3 |

**Conflicto real de fechas:** pp. 4 y 7 indican exámenes de salas/laboratorio del **3 al 5 de enero**, antes del cierre del **30 de enero**. No se cambió enero por febrero. Además, un único `calls.exam_at` para todo el documento no representa el examen de materias del 2–3 de febrero y el de otros bloques. El conflicto está señalado, pero falta un calendario por bloque/modalidad y la aclaración oficial para operar.

Las fechas de apertura 01/01 cargadas por el seeder no están expresamente fijadas como apertura en el PDF. Son valores de referencia técnica, no fechas oficiales confirmadas.

## 3. Segunda convocatoria 02/2026 — 6 páginas

| Bloque | Datos del PDF | Representación actual |
|---|---|---|
| Docencia por asignatura, pp. 1–2 | 17 asignaturas; requisitos generales/específicos y máximo de cuatro. | **0/17 ofertas históricas por asignatura cargadas.** No se consideran las ofertas DEMO como sustituto. |
| Cronograma, pp. 2–6 | Cierre 12/02/2026 18:00; exámenes 13–14/02; resultados 18/02. | Cierre y fecha inicial de examen representados; falta fin del rango y publicación como hitos estructurados con control de plazo. |
| Salas, pp. 3–4 | 16 plazas: sala 13 y software 3. No ofrece Redes/Electrónica como plazas de sala. | **2/2 filas y plazas coinciden**, sin incorporar ofertas de Redes/Electrónica de la primera convocatoria. |
| Laboratorio de servicio, pp. 5–6 | 1 plaza y requisitos específicos, entre ellos PPA ≥55 y no ejercicio anterior. | Plaza histórica correcta, criterios específicos incompletos. |
| Documentos | Carta, CPD, CI, avance/histórico, certificado y CV según bloque; recepción física en Vicedecanato. | Tipos disponibles y revisión técnica; no acredita recepción física/folio ni trámite oficial equivalente. |

| Oferta de sala | Soporte | Redes | Software | Electrónica | Plazas |
|---|---:|---:|---:|---:|---:|
| Sala de cómputo | 50 % | 25 % | 25 % | — | 13 |
| Software | — | — | 100 % | — | 3 |

Las 17 asignaturas pertenecen al catálogo de la primera convocatoria; son una selección distinta que debe conservar su propia versión. No deben sumarse como 66 asignaturas únicas ni reutilizarse automáticamente las plazas de la primera convocatoria. El PDF no indica cantidad de plazas por cada asignatura de las listas: no se inventaron.

## 4. Cuánto falta y en qué orden

No se calcula un porcentaje global de los PDF: un artículo de contexto, una obligación de asistencia y un control de selección no son unidades equivalentes.

1. **Datos históricos incompletos:** cargar de forma versionada las 49 y 17 ofertas de asignatura, con siglas, nombres, agrupación y referencias; conservar las plazas por asignatura como no especificadas hasta confirmación. En esta revisión se entrega el catálogo documental JSON; no se insertaron ofertas ni se reabrieron convocatorias vencidas.
2. **Criterios y calendario:** desglosar todos los requisitos por modalidad y cronogramas por bloque, sin tomar valores técnicos de apertura como oficiales; resolver el conflicto de enero.
3. **CPD y datos académicos:** homologar formulario/campos y criterios, resolver tablas contradictorias y establecer mecanismos autorizados de verificación de matrícula, notas, permanencia y antecedentes de auxiliatura.
4. **Examen y aprobación:** confirmar composición de áreas/componentes, rúbricas, umbral final, comisión y acreditaciones; distinguir PPA mínimo de nota aprobatoria del concurso.
5. **Actos administrativos:** resoluciones, firmas, plazos de publicación/informe, declaratorias desiertas y excepciones; preservar el historial y no adjudicar reemplazos automáticamente.
6. **Después del nombramiento:** asistencia, horarios, becas y planillas requieren ampliación de alcance institucional. No se consideran implementados por existir reportes de selección o pagos simulados.

Los seis registros de matrices de salas y las dos cantidades de laboratorio están representados correctamente. Eso no convierte los ocho registros en una representación completa de las tres fuentes. Los mecanismos técnicos de postulación, custodia documental, revisión, notas, ranking y desempate existen y conservan sus pruebas.

## 5. Frontend modificado en el mismo sistema

- `resources/js/portal/main.jsx`: ingreso con pasos claros, aviso que distingue fuentes reales y operaciones DEMO, breadcrumb y panel compacto de apariencia.
- `resources/js/portal/workspaces.jsx`: accesos según responsabilidad, tarjetas de convocatorias con fechas separadas, filtros Todas/Vigentes/Históricas y estados vacíos útiles.
- `resources/js/portal/portal.css`: nueva paleta verde sobria y fondos claros, navegación lateral ligera, jerarquía tipográfica, tarjetas, formularios/tablas coherentes, menú móvil y panel de accesibilidad desplazable; conserva tres temas, noche, contraste y 100–200 % de letra.
- `resources/js/portal/calls-navigation.test.jsx`: regresiones de filtrado, separación histórico/DEMO y recuperación del catálogo.
- `resources/js/portal/auth-redesign.test.jsx` y contador de `main.jsx`: recuperación de sesión CSRF después de salir, reutilizando el mismo recibo para no duplicar visitas. El fallo se reprodujo y la corrección se comprobó en el navegador.

El servidor sigue gobernando los permisos; los accesos rápidos no conceden privilegios. Se mantuvieron los 8 CU y 30 RF. No se modificaron los 70 archivos de backend/configuración/migraciones/rutas contrastados con el candidato anterior. No se importaron alumnos, documentos, notas o decisiones reales a modo de prueba.

## 6. Verificación y límites de entrega

La compilación actual y el runtime local se sincronizan mediante el mecanismo existente con respaldo de archivos y huellas. Se conservaron el tarball previo y el commit GitHub autorizado como antecedentes; el frontend nuevo no se presenta como publicado en Tecnoweb ni cubierto automáticamente por la aceptación de aquel tarball.

Resultados de esta intervención (cifras de ese corte; las vigentes están en el README): 306 pruebas PHP / 1.973 aserciones aprobadas en `ficct_test`, separado de la base operativa; 65 React aprobadas; compilación aprobada. La revisión visual y preservación final se registran en el recibo `frontend-review.json` de la carpeta privada de evidencia de esta intervención.

La autorización institucional y la vigencia de todas las disposiciones no se certifican mediante este contraste del corpus. Se conserva NO_GO_INSTITUCIONAL. No se alteraron servidor remoto, GitHub ni la base remota.

## Identidad de los originales

- C:\Users\ElCremosasWee\Downloads\RES. VICERRECTORAL 021-2023 aux labs.pdf: 29 páginas; SHA-256 `02ea462eec51f81725f9081162045cdc8c1c4c6242f3af7a76ec1942a571171e`.
- C:\Users\ElCremosasWee\Downloads\2026 Convocatoria auxiliares FICCT.pdf: 7 páginas; SHA-256 `1840f406a749201ce8a915eb6ff85f61b7a85b3d37f08b491800abb1f5a157bd`.
- C:\Users\ElCremosasWee\Downloads\2026 Convocatoria auxiliares FICCT segunda convocatoria.pdf: 6 páginas; SHA-256 `cd2a738f9ab790a39bcd2b02966c48bd9e5bcdd2f7f11a80f229b35af5ae24e4`.
