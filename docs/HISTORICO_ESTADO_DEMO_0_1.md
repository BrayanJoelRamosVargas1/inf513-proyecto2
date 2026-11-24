# Estado y continuidad — versión ejecutable DEMO 0.1

Fecha: 10/11/2026. Encargo: auditar fuentes y comenzar una primera fase ejecutable del sistema. **Ya existe aplicación real; el cierre integral del contrato y el uso institucional continúan pendientes.**

## Hecho

- Auditoría del entorno y de cuatro PDF/57 páginas y tres fotografías, con hashes, referencias y conflictos específicos. Contrato íntegro de ocho CU y 30 RF preservado.
- Aplicación Laravel/PHP, PostgreSQL real aislado, migraciones, seed sintético, sesiones, roles y permisos por convocatoria, interfaz responsive en español.
- Convocatorias y ofertas editables en borrador, versiones y congelación al publicar; históricos 01/02 separados; calendario incoherente no se autocorrige.
- Postulación múltiple con límite/duplicados, expediente privado versionado, envío con documentos, revisión por criterio y habilitación; CPD manual con respaldo.
- Evaluadores por área, borrador/confirmación de notas, rectificación autorizada y auditada antes de publicación, cálculo decimal por postulación.
- Ranking, cupos, nuevo examen exclusivo de empatados con comisión/condiciones/resultados/acta, aprobación DEMO y snapshot; auditoría y exportaciones PDF/XLSX reales.
- Recorrido sintético ejecutado hasta selección tras un nuevo examen, conservado separado de una convocatoria todavía abierta.

## Comprobado

| Verificación | Resultado | Evidencia |
|---|---|---|
| PHPUnit sobre PostgreSQL separado | 39 PASS, 139 aserciones, 0 FAIL | evidence/tests.txt y phpunit.xml |
| Tres carreras con dos procesos/conexiones independientes | PASS: límite de postulaciones, nota y aprobación de última plaza | evidence/concurrency.json |
| Siete controles HTTP contra servidor real | PASS: acceso, cabeceras, CSRF, sesión, permisos y .env no expuesto | evidence/security-http.json |
| Restauración en otra base + archivos privados | PASS: conteos iguales y 12 documentos con SHA-256 | evidence/restore.json |
| PDF/XLSX descargados desde navegador | PASS: contenido, dos filas, valores, celdas de texto y render PDF inspeccionado | evidence/exports.json, reporte-demo.pdf/.xlsx |
| Navegación y diseño | Acceso y pantallas reales; escritorio y viewport móvil 390px sin desbordamiento global | evidence/screenshots/ |
| Dependencias | composer validate válido; audit sin avisos publicados en la ejecución | evidence/composer-audit.json |
| Estilo PHP | Pint --test pasó | evidence/calidad.json |
| Instalación en copia limpia | PASS: dependencias nuevas, otra BD, migraciones, seed, recorrido y vistas | evidence/clean-install.json y clean-install.txt |

La instalación limpia se ejecutó en este equipo con los binarios PostgreSQL existentes, nuevas dependencias y otra base independiente. No se afirma una prueba en una máquina sin herramientas instaladas. Las evidencias documentales del paquete anterior no se contabilizan como pruebas de esta aplicación.

## Pendiente técnico (no presentar como bloqueado por normativa)

1. Administración completa de perfiles y revocación/temporalidad de permisos desde UI; actualmente existen altas y asignaciones, además de control de cuenta inactiva en backend.
2. Reasignación de docentes con autorización e historia, rectificación de méritos y recálculo/versionado de publicaciones posteriores a aprobar. Actualmente esos cambios se bloquean para preservar evidencia.
3. Subsanación tras envío, retiros/renuncias, gestión de reclamos y de varias ofertas ganadas; configurar procedimientos DEMO explícitos sin hacerlos pasar por reglas institucionales.
4. Nuevas rondas cuando persista el empate, actas estructuradas/adjuntas y circuito de firmas; la primera ronda y su resolución documentada funcionan.
5. Formularios y reportes institucionales exactos, filtros/estadísticas más extensos, plantillas exhaustivas de requisitos por régimen y clonación asistida de una convocatoria.
6. Ampliar pruebas de navegador por todos los perfiles y pantallas, accesibilidad formal, volúmenes/carga y revisión de seguridad independiente. Las pruebas actuales son iniciales y significativas, no exhaustivas.

## Pendiente institucional (bloqueos localizados)

Vigencia/autenticidad/rectificaciones; reglamento FICCT adicional; formularios y validación CPD; composición áreas↔escrito/práctico, escala/redondeo y agregación multievaluador; condiciones de cada nuevo examen; firmas/autoridades definitivas; reclamos/cotejo físico; datos/retención e infraestructura. El stack docente no está confirmado.

OFICIAL y la operación sobre históricos permanecen bloqueados. No se inventa normativa, no se usa CPD real, no se anuncia un nombramiento y no se incorporan datos reales a la DEMO.

## Continuar sin rehacer

Leer este estado, `01-AUDITORIA-FUENTES.md`, `02-TRAZABILIDAD.md` y el contrato canónico. Conservar repositorio, BD y archivos privados existentes. Iniciar con `INICIAR_DEMO.ps1`; credenciales en `.local/ACCESOS_DEMO.txt`. Pruebas solo con `scripts/test.py` y base `ficct_test`. No ejecutar migrate:fresh sobre la DEMO. La siguiente implementación prioritaria es administración/revocación de permisos y rectificaciones versionadas con sus pruebas, antes de ampliar reportes.

Dictamen: **primera fase ejecutable demostrada; cobertura base de ocho CU/30 RF; sistema completo aún no declarado terminado; uso institucional no autorizado.**
