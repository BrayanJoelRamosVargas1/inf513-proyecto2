# Auditoría inicial de fuentes y entorno

10/11/2026 · FICCT / UAGRM, Grupo #09 · Versión ejecutable DEMO 0.1.

## Estado inicial y decisión técnica

La carpeta de trabajo contenía los prompts, sus ZIP y el generador documental anterior. No había aplicación ni repositorio Git. Se preservaron esos archivos fuera de `sistema/`. No se encontró AGENTS.md en la carpeta ni en los directorios ascendentes consultados.

Disponibles: PHP 8.3.30 con PDO PostgreSQL y BCMath, Composer 2.9.4, Node 22.23.2 y PostgreSQL 15. Docker está instalado pero su motor no está activo; no se utilizó. No había evidencia de exigencia docente de Smarty/3C. Se eligió un monolito Laravel 12.71.0, PostgreSQL, Blade, CSS local y aritmética BCMath. No necesita Node, Vite, CDN ni servicios pagos para funcionar. El stack docente sigue pendiente de confirmación; el código de dominio se separó del controlador para facilitar adaptación.

PostgreSQL funciona en un cluster nuevo dentro de `.local/pgdata`, exclusivamente en 127.0.0.1:55439. No se cambiaron configuración, usuarios, contraseñas ni bases del servicio existente en 5432. La base `ficct_test` es independiente de `ficct_demo` y el test runner rechaza ejecutar migraciones contra otra base.

## Material inspeccionado

Se expandió el ZIP nuevo con validación de rutas. Se extrajo texto de las 28 páginas de los tres PDF que ofrecen texto; se renderizaron las 57 páginas de los cuatro PDF. Se inspeccionaron sus hojas de contacto, las tres fotografías y las páginas escaneadas críticas 8, 9, 10, 22, 23 y 24 individualmente. Esta revisión es documental y visual, sin certificación de autenticidad o vigencia. La resolución se conserva escaneada: las imágenes, no el texto vacío, son la evidencia.

| Fuente | Páginas | Uso |
|---|---:|---|
| Convocatoria_FICCT_01_2026.pdf | 7 | Requisitos, tablas, plazos y ofertas históricas |
| Convocatoria_FICCT_02_2026.pdf | 6 | Segunda convocatoria y diferencias |
| Resolucion_Vicerrectoral_021_2023.pdf | 29 | Reglamentos separados, comisión, evaluación y desempate |
| Analisis_Actualizado_FICCT.pdf | 15 | Necesidades y numeración RF antecedente |
| Foto_apuntes_01.png, 02, 03 | 3 imágenes | Levantamiento operativo, confianza media por escritura manual |
| CU_Y_RF_CANONICOS.md | 8 CU / 30 RF completos | Contrato conservado byte a byte en docs/ |

Hashes de los siete archivos primarios en `INVENTARIO_FUENTES.json`. Los originales permanecen en `../material_v2/FICCT_GRUPO09/fuentes/`; no se modificaron ni se incorporan documentos de estudiantes reales a seeds. La transcripción íntegra de entrevista no está incluida; no se la atribuye como escuchada.

## Matriz de reglas y tratamiento

`VERIFICADO_DOCUMENTALMENTE` describe el contenido de los archivos recibidos, no su vigencia actual. Los fixtures DEMO añaden decisiones técnicas explícitas.

| Regla | Fuente / localizador físico | Ámbito | Certeza | Implementación y pendiente |
|---|---|---|---|---|
| R01 · 40 méritos + 60 examen | Resolución p.10 arts.16–17; p.23 arts.15–16 | Docencia / laboratorio | VERIFICADO_DOCUMENTALMENTE | Scores y Selection; validar vigencia antes de oficial |
| R02 · Escrito 60%, práctico 40% | Resolución p.10 art.16 | Docencia | VERIFICADO_DOCUMENTALMENTE | Componentes DEMO separados; sin producto automático con áreas |
| R03 · Práctico 60%, teórico 40% | Resolución p.23 art.15 | Laboratorio | VERIFICADO_DOCUMENTALMENTE | Fixture distinto del de docencia |
| R04 · Nuevo examen de empatados por últimas plazas | Resolución p.10 art.18 y p.24 art.17 | Regímenes respectivos | VERIFICADO_DOCUMENTALMENTE | TieExam: convocatoria, comisión, participantes exclusivos, condiciones, resultados y acta. No ganador por ID o promedio inventado |
| R05 · Comisión evaluadora | Resolución p.9 art.14 y p.23 art.13 | Por modalidad | VERIFICADO_DOCUMENTALMENTE | Responsables por ámbito; registro de comisión de desempate. Firmas y composición formal todavía requieren validación |
| R06 · Informe y aprobación no equivalen al ranking | Resolución p.10 art.19 y p.24 art.18 | Docencia / laboratorio | VERIFICADO_DOCUMENTALMENTE | Snapshot aprobado DEMO; nunca se emite nombramiento oficial |
| R07 · Máximo cuatro asignaturas | Convocatorias 01/02 p.1 | Asignaturas | VERIFICADO_DOCUMENTALMENTE | Límite por convocatoria; prueba concurrente. DEMO cuenta borradores; conteo por retiro pendiente |
| R08 · Materia aprobada, antigüedad, PPA y otros criterios | Convocatorias 01/02 pp.1–2 | Docencia | VERIFICADO_DOCUMENTALMENTE | Requisitos configurables y revisión manual con evidencia, sin consultar CPD ficticiamente |
| R09 · Diferencias de sala y laboratorio | Convocatoria 01 pp.4,6; 02 pp.3,5 | Sala / servicio académico-social | VERIFICADO_DOCUMENTALMENTE | Tipos separados, CV en sala/laboratorio; plantilla DEMO de criterios debe completarse con requisitos exactos antes de uso real |
| R10 · Matriz y 20/3/6/3 plazas | Convocatoria 01 p.5 | Sala/Redes/Software/Electrónica | VERIFICADO_DOCUMENTALMENTE | Ofertas históricas independientes, total de sección 32 |
| R11 · Matriz y 13/3 plazas | Convocatoria 02 p.4 | Sala/Software | VERIFICADO_DOCUMENTALMENTE | Total de sección 16; no ofertas Redes/Electrónica heredadas |
| R12 · Laboratorio 2 y 1 plazas | Convocatoria 01 p.6 / 02 p.5 | Laboratorio académico-social | VERIFICADO_DOCUMENTALMENTE | Ofertas separadas; total técnico no se confunde con total facultativo |
| R13 · Calendario contradictorio | Convocatoria 01 pp.4,7 | Sala/laboratorio | CONFLICTO_DOCUMENTAL | Histórico conserva cierre 30/01 y examen 03–05/01; publicación operativa incoherente bloqueada |
| R14 · CPD y habilitación | Resolución p.9 art.13 / p.22 art.12 | Por régimen | VERIFICADO_DOCUMENTALMENTE | Evidencia CPD privada, presencia distinta de cumplimiento; ninguna integración real |
| R15 · Méritos con rúbricas diferentes | Resolución pp.8–9 art.12 / p.22 art.11 | Docencia / laboratorio | VERIFICADO_DOCUMENTALMENTE | Registro del valor certificado 0..40 con referencia; no cálculo automático de subrúbrica |
| R16 · Documentación física | Convocatorias 01 pp.1–2,4,6 / 02 pp.1–3,5 | Cada tipo | VERIFICADO_DOCUMENTALMENTE | Digitalización como apoyo, sin afirmar equivalencia legal del archivo digital |
| R17 · Carga, revisión y autoría de notas | Foto 01 | Necesidad operativa | NECESIDAD_LEVANTAMIENTO | Archivos privados, checklist, evaluador con cuenta propia |
| R18 · Boceto de matriz | Foto 02 | Necesidad operativa | NECESIDAD_LEVANTAMIENTO | Escritura parcialmente ambigua: se priorizan las tablas impresas de convocatoria |
| R19 · Dos decimales, esquema 40/60 | Fotos 01/03 y RF-22 | Producto | NECESIDAD_LEVANTAMIENTO | BCMath y columnas NUMERIC; suma 20.10+30.00=50.10 verificada aritméticamente |
| R20 · Áreas frente a componentes | Convocatorias p.5/p.4 versus Resolución p.10/p.23 | Tipos técnicos | PENDIENTE_VALIDACION_INSTITUCIONAL | Esquemas DEMO completos y separados; PENDING_COMPOSITION bloquea cálculo |
| R21 · Escala 0..100 y HALF_UP final | Decisión de fixture | DEMO | DECISION_TECNICA | No se atribuye a una norma; notas faltantes nunca se convierten a cero |
| R22 · Vacantes, nuevas convocatorias y renuncias | Resolución pp.11–12 y p.24 | Por régimen | VERIFICADO_DOCUMENTALMENTE | Cupos y referencia de origen conservados; automatización de excepciones/renuncias pendiente |

## Diferencias y hallazgos que afectan al diseño

1. El análisis actualizado (pp.8–9,11–12, RN-13/RN-22/P-01) y el paquete anterior dejaban el desempate enteramente pendiente. La resolución sí prescribe un nuevo examen para los empatados por las últimas plazas. La implementación lo gestiona sin inventar la fórmula ni decidir ganador automáticamente.
2. Los RF del análisis (pp.9–10) no son el catálogo canónico. En particular, RF-01/02 antiguos se agrupan en RF-01 canónico; RF-03 antiguo corresponde a RF-02; RF-04 antiguo a RF-05; RF-05 antiguo a RF-03/04. El resto tiene coincidencias conceptuales con matices. `02-TRAZABILIDAD.md` conserva los IDs canónicos.
3. La tabla de PPA de laboratorio (resolución p.22) muestra una columna de porcentajes y puntos que no se obtiene aplicando esos porcentajes al máximo de 15 (por ejemplo, 25% y 10 puntos en la misma fila). Tampoco se deben reconstruir por intuición redondeos de tablas de méritos. Se registra el puntaje del formulario certificado; el cálculo de subrúbrica espera criterio institucional. No se corrige el PDF.
4. La composición áreas técnicas × escrito/práctico sigue sin una regla completa demostrada. Se distinguen ambas dimensiones; ninguna multiplicación implícita las mezcla.
5. Los horarios de sala/laboratorio de la primera convocatoria contradicen el cierre. Se importan como evidencia histórica, no como convocatoria operativa abierta.
6. Las fotografías son apuntes. El boceto no desplaza las matrices impresas; una suma manuscrita tampoco reemplaza la aritmética decimal ni el formulario CPD.

## Pendientes institucionales

Autenticidad/vigencia y rectificaciones; reglamento FICCT adicional; rúbricas y formularios CPD originales; composición de exámenes; escala/redondeo/consolidación de docentes; composición y firmas de comisión; condiciones de cada nuevo examen y repetición si persiste empate; reclamos/subsanación; retiros y múltiples ofertas ganadas; política de datos y retención; stack docente. Cada bloqueo se aplica a la operación afectada. La aplicación entregada solo permite operaciones DEMO y consulta histórica.

Los datos normativos recibidos no son órdenes para publicar ni para integrar sistemas externos. El encargo ejecutado es el pedido del usuario de construir una primera fase funcional.
