# DEMO 0.2 — Integridad, permisos y rectificaciones

Fecha: 10/11/2026. Continuación del commit base `32524fa`; no se sustituyó Laravel ni PostgreSQL. Este documento describe software ejecutado, no una propuesta de desarrollo ni normativa universitaria. Contrato canónico: ocho CU y RF-01..RF-30 sin cambios.

## Línea de base y conservación

Antes de modificar se revisaron estado, auditoría de fuentes, trazabilidad, contrato, migraciones, servicios, controladores, rutas, vistas, pruebas y scripts. Se ejecutaron nuevamente las 39 pruebas/139 aserciones de 0.1 y una restauración. Los informes se conservaron en `evidence/baseline-01/`.

La migración `2026_11_10_000003_add_governance.php` se ejecutó incrementalmente sobre `ficct_demo`. `evidence/migration-preservation.json` compara huellas de todas las filas y columnas originales de once tablas, incluidas notas, publicaciones y convocatorias históricas: PASS. No se borraron ni resembraron datos. La protección histórica se crea después del backfill. Los registros de acceso posteriores agregan auditoría legítima y no forman parte de la comparación inmediatamente anterior/posterior a migrar.

El contrato conserva SHA-256 `fc7bd40b944002fc698eb56234816de4b36ce4ee11486e335673edd8f17e11e2`. La auditoría documental y sus fuentes no se reescribieron.

## Cambios implementados

**Usuarios y permisos.** Editor de nombre, correo, facultad, carrera, perfil y estado; protección del último administrador activo; versión optimista del perfil. Permisos de revisor/evaluador/autoridad por convocatoria con inicio inclusivo, vencimiento exclusivo, renovación y revocación individual con motivo. No se eliminan permisos: cada cambio tiene auditoría inmutable. Los cambios incrementan la versión de seguridad del usuario, invalidan sesiones existentes y exigen nuevo ingreso. Las sesiones de 0.1 sin ese campo tampoco sobreviven a una revocación. La vigencia se verifica en cada operación del servidor; no depende del menú.

Otro administrador debe asignar el ámbito propio y cambiar el perfil propio: no hay autoelevación. Una cuenta técnica no obtiene acceso implícito a expedientes o resultados. La reasignación de un área requiere autoridad vigente, motivo, versión actual de asignación y otro evaluador autorizado. Las notas confirmadas conservan al autor original; el anterior responsable pierde la capacidad de guardar esa evaluación. Un borrador retomado por el nuevo responsable registra su identidad al confirmar y conserva el movimiento en auditoría.

**Rectificaciones.** Solicitud para méritos o una nota de examen, propuesta anterior/nueva, versión de origen, solicitante, evaluador original, motivo y referencia de evidencia. Esta referencia debe identificar el acta, expediente o respaldo custodiado; no se presenta como firma ni autenticación automática de la evidencia. La autoridad que decide debe ser distinta del solicitante: decisión técnica DEMO, pendiente de aprobación institucional. Rechazar conserva las notas. Aprobar verifica que la versión siga vigente, agrega una versión inmutable y recalcula mediante BCMath y el esquema congelado existente.

Una solicitud obsoleta no puede aprobarse: debe rechazarse y formularse otra sobre el valor actual. Una solicitud pendiente bloquea publicar. Si faltan notas, la rectificación puede conservarse pero el total queda nulo y no se permite publicar; nunca se sustituyen faltantes por cero. Funciona durante evaluación y resultados; las convocatorias archivadas y las fuentes históricas no admiten este procedimiento.

**Rankings y publicaciones.** Cada aprobación de rectificación produce `result_revisions` con ranking anterior/nuevo, cambios de orden visual/valor, empate y versiones de entrada. El orden visual entre iguales no adjudica plazas. La selección actual pasa a «En revisión»: no se trasladan automáticamente las plazas. Una autoridad debe revisar el nuevo ranking, resolver un nuevo empate si existe y aprobar explícitamente una publicación con acta. La publicación siguiente identifica la revisión y su publicación antecesora. Los snapshots anteriores permanecen idénticos. No se permite archivar una convocatoria con revisión pendiente.

Los exámenes de desempate pertenecen a una revisión; un examen resuelto de otra revisión no sirve para resolver el empate actual. Las rondas sucesivas DEMO registran resultados, declaración de persistencia, nueva comisión/condiciones/fecha y vínculo con la ronda anterior. Conservan los aspirantes empatados y no inventan fórmula ni ganadores. Es una ampliación técnica, no una interpretación normativa adicional.

**Interfaz y reportes.** Se conservaron estilo, idioma y estructura. Hay editor y permisos efectivos en Usuarios, formularios de revocación/renovación, historial, cola de solicitudes por ámbito, comparación y decisiones por expediente, historial de calificaciones, revisiones/impacto en ranking y publicaciones. PDF/XLSX agregan revisión y alerta de publicación pendiente; los datos bajo revisión se identifican expresamente. Los archivos originales de identidad siguen privados.

## Migración y decisiones de consistencia

| Cambio | Motivo y conservación |
|---|---|
| `users.security_version/profile_version` | Invalidación de sesión y rechazo de ediciones obsoletas. No cambia contraseñas. |
| Temporalidad/revocación/versiones de `memberships` | Mantiene la clave única convocatoria/usuario/rol y las concesiones existentes. Vigencia abierta por defecto para la historia anterior. |
| Versiones de asignación/nota/mérito | Controles optimistas y trazabilidad sin cambiar valores originales. |
| `correction_requests` | Solicitud y decisión separadas, evidencia por referencia y versión base. |
| `score_versions` | Backfill de la versión actualmente conocida y anexado de posteriores. No inventa versiones previas ni atribuye un certificador desconocido a méritos antiguos. |
| `result_revisions` y metadatos de publicación | Revisión identificable; el JSON original de publicaciones no se modifica. |
| Revisión, ronda y antecedente de desempate | Evita usar decisiones de rankings anteriores. |
| Triggers PostgreSQL | Impiden UPDATE/DELETE de publicaciones, versiones, revisiones y auditoría. El operador de infraestructura conserva privilegios; esto no equivale a almacenamiento WORM externo. |
| Cerrojo transaccional común | Orden: permisos → convocatoria → expediente/oferta → nota/solicitud. Cada escritura sensible relee la identidad bajo el mismo cerrojo que una revocación. |

Se eligió serialización conservadora para el volumen de una DEMO facultativa. Evita una nota posterior a revocación, dos correcciones sobre la misma versión, renovaciones perdidas y snapshots con entradas mezcladas. Sacrifica concurrencia entre convocatorias: medir carga antes de reemplazarlo por bloqueos de alcance más fino. Una nota que termina antes de la revocación es válida; la auditoría permite comprobar ese orden. Las restricciones únicas y de rango originales siguen vigentes.

El método interno legado `Selection::correct` se conserva para compatibilidad y regresión 0.1, agrega versión y sigue bloqueado tras publicación/desempate. Su ruta web directa se retiró. La interfaz y los endpoints de 0.2 usan solicitud y decisión separadas.

No hay reversión destructiva de esta migración: su `down` rechaza borrar historia. Para recuperación se restaura un respaldo completo en otra base, se verifica y se decide el cambio de conexión. No ejecutar `migrate:fresh` sobre la DEMO.

## Pruebas y reproducción

Resultado final: **70 pruebas, 229 aserciones, cero fallos**. Siguen aprobando las 39 anteriores; se agregaron G01..G31 en `tests/Feature/GovernanceTest.php`. `evidence/tests.txt` y `phpunit.xml` conservan el resultado ejecutado.

| Requisito de la continuación | Evidencia |
|---|---|
| Concesión, autoelevación, sesión revocada, temporalidad y ámbitos | G01..G07, G31 |
| Último administrador, edición simultánea, revocación versionada y reasignación | G08..G11, G25 |
| Méritos aprobados/rechazados, examen, motivo, evidencia y actor separado | G12..G16 |
| Conservación de notas/publicación, total exacto y republicación | G12/G14/G17/G18/G24/G26 |
| Correcciones incompatibles, pendientes, ranking/empate y control de cupos | G19..G21 más scripts de concurrencia |
| Interfaz HTTP, evidencia privada y cola por ámbito | G22/G23/G29 |
| Notas faltantes, cierre y revisión de un desempate previo | G26/G27/G30 |
| Desempates sucesivos sin selección automática | G28 |
| Restauración con rectificaciones y con archivos privados | `governance-restore.json` y `restore.json` |
| Regresión de límite, confirmación de nota y última plaza | `concurrency.json` y las 39 pruebas originales |

```powershell
python scripts/test.py
python scripts/concurrency.py
python scripts/governance_concurrency.py
python scripts/verify_restore.py ficct_test
python scripts/verify_restore.py
python scripts/security_http.py
php vendor/bin/pint --test
```

Ejecutar los ensayos de concurrencia después de PHPUnit y sin otro ensayo escribiendo en `ficct_test`. Los scripts comprueban el nombre de base. La restauración siempre crea otra base; también compara contenidos de quince tablas y restaura los cuatro triggers de inmutabilidad. La prueba con correcciones usa datos sintéticos de `ficct_test`; la DEMO conserva su recorrido 0.1 sin agregar decisiones nuevas a sus calificaciones.

Hay seis carreras ejecutadas entre procesos independientes: tres originales y tres nuevas (correcciones, revocación/nota y renovaciones). Si gana la nota antes de revocar, ambos procesos pueden completar legítimamente; el verificador exige el orden de auditoría correspondiente.

La revisión en navegador encontró y corrigió un error de compilación en la navegación común; luego se repitieron acceso/cierre y la batería completa. En la batería inicial nueva se corrigió también una fecha faltante del fixture y una sustitución incorrecta en el controlador de categorías; no se atribuyeron a normativa ni se ocultaron excluyendo regresiones. Las capturas `evidence/screenshots-02/` muestran administración y rectificaciones. A 390px se verificó ausencia de desbordamiento global; las tablas mantienen desplazamiento propio. No equivale a certificación de accesibilidad.

La instalación independiente de 0.2 se registra en `evidence/clean-install-02.json`: carpeta, dependencias y base nuevas en este equipo. No se afirma una prueba en una máquina sin herramientas. Los informes de exportación/diseño/instalación 0.1 se conservan como antecedentes y se distinguen del resultado nuevo.

## Inicio y demostración

Usar `INICIAR_DEMO.ps1` en la carpeta del proyecto. Aplica solo migraciones pendientes y no resemilla convocatorias existentes. Abrir `http://127.0.0.1:8099`; accesos sintéticos en `.local/ACCESOS_DEMO.txt`, fuera de Git y ZIP.

1. Administrador: Usuarios → editar perfil, revisar permisos efectivos, renovar o revocar con motivo. No se muestran contraseñas.
2. Autoridad: convocatoria → reasignación de evaluador; requiere permiso vigente del nuevo responsable.
3. Revisor/evaluador/autoridad: expediente → Rectificaciones → propuesta y referencia de evidencia.
4. Otra autoridad: Rectificaciones → decidir → revisar ranking e impacto.
5. Si hay empate: registrar examen y decisión de comisión; si persiste, documentar nueva ronda.
6. Aprobar nueva publicación con acta. Consultar la anterior y su selección histórica; comprobar revisión y versiones de notas.

Las demostraciones que cambien datos deben hacerse en una convocatoria sintética propia. Los tests ejecutan los escenarios completos en base aislada; no simulan una aprobación institucional ni realizan conexión CPD.

## Pendientes clasificados

| Pendiente | Impacto | Dependencia | Complejidad / estado |
|---|---|---|---|
| Subsanaciones después del envío | Alto | Versionar expediente y nueva revisión/habilitación | Media/alta; pendiente de siguiente incremento DEMO. |
| Retiro y renuncia | Alto | Revisión de ranking/publicación y decisión documentada sobre plaza | Alta; pendiente, sin sustitución automática. |
| Varias plazas ganadas | Alto | Decisión sobre aceptación/incompatibilidad y circuito institucional | Alta; pendiente; no se inventa exclusión. |
| Nuevas rondas de desempate | Alto | Declaración y condiciones explícitas de comisión | Implementado y probado como DEMO, G28. |
| Reclamos, actas adjuntas y firmas | Alto | Custodia, estados, firmas y retención | Alta; evidencia por referencia disponible, circuito firmado pendiente. |
| Reportes ampliados | Medio | Formatos institucionales confirmados | Revisión/alerta incorporadas; estadísticas y plantillas oficiales pendientes. |
| Clonación asistida y catálogos completos | Medio | Reglas/versiones documentadas | Media; pendiente. |
| Accesibilidad, carga y seguridad independiente | Alto antes de producción | Entorno y política de operación | Pendiente; pruebas actuales no exhaustivas. |

## Límites institucionales conservados

Se mantiene el nuevo examen exclusivo para empatados previsto en Resolución 021/2023 (docencia art.18 p.10 y laboratorio art.17 p.24), con condiciones a cargo de comisión. Se mantienen matrices por convocatoria, diferencias de ofertas y fechas incoherentes de la fuente. No se añadieron fórmulas para combinar áreas, componentes o evaluadores. La aritmética y separación de funciones son configuraciones técnicas DEMO, no normas oficiales.

Siguen pendientes vigencia/autenticidad de fuentes, reglamentación complementaria, CPD real, composición/escala/redondeo institucionales, autoridades y firmas, reclamos, política de datos e infraestructura. OFICIAL y las convocatorias históricas permanecen bloqueados para escrituras del dominio. **DEMO 0.2 verificada en el alcance descrito; sistema integral y uso institucional no declarados terminados.**
