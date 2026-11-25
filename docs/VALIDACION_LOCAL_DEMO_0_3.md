# Validación local de DEMO 0.3 y recorrido por perfiles

Fecha: 10/11/2026. Se verifica el paquete documental corregido y la aplicación existente; no se inicia DEMO 0.4. El código de dominio, controladores, vistas, migraciones y contrato canónico permanecen intactos. Se añaden comprobadores reproducibles y evidencias nuevas. El empaquetador admite un destino opcional para conservar el ZIP anterior.

## Resultados ejecutados en esta continuación

| Comprobación | Resultado | Evidencia en `evidence/validacion-local-03/` |
|---|---|---|
| ZIP corregido recibido como base | 180 archivos, CRC válido, 179 hashes verificados directamente | `documental.json` |
| CSV y Markdown | 30 RF únicos; coinciden nombres, CU, módulos, rutas, tablas, reglas, estados y referencias | `documental.json` |
| Referencias de pruebas | 89 métodos distintos existen en PHP y en el XML registrado | `documental.json` |
| Contrato canónico | SHA-256 intacto: `fc7bd40b944002fc698eb56234816de4b36ce4ee11486e335673edd8f17e11e2` | `documental.json` |
| Nueva ejecución local de PHPUnit | **102 pruebas, 311 aserciones, cero errores/fallos**, con las mismas identidades de pruebas anteriores | `phpunit.xml`, `tests.txt`, `regresion.json` |
| Conservación durante PHPUnit | 31 tablas y 18 archivos privados de la DEMO idénticos; 54 archivos de evidencia histórica intactos | `regresion.json` |
| Recorrido funcional por HTTP real | **17 comprobaciones y 101 solicitudes**, con sesiones y CSRF activos | `recorrido-http.json` |
| Revisión de pantallas | Cinco perfiles observados; dos hallazgos de presentación pendientes | `visual.json`, `pantallas/` |
| Fuentes originales | NOT_VERIFIED; las tres rutas expresamente proporcionadas siguen sin archivos | `fuentes-pendientes.json` |

La nueva ejecución se hizo en este mismo equipo. Supera la mera lectura de un XML anterior, pero no constituye una auditoría independiente externa. Las pruebas de concurrencia, restauración, instalación separada y carga no se repitieron en esta continuación; sus evidencias anteriores conservan su carácter histórico. El XML nuevo se guarda aparte y no reemplaza `evidence/phpunit.xml`.

## Recorrido funcional ejecutado

Se creó una base nueva `ficct_walkthrough_20261110153640_ee96` y una carpeta privada propia en `.local/`, con ocho cuentas sintéticas que representan los cinco perfiles y candidatos adicionales. La única cuenta de arranque es un administrador sintético; las demás cuentas se crean desde la aplicación por HTTP. Una convocatoria y dos ofertas técnicas sintéticas no se presentan como matrices institucionales.

1. El administrador crea cuentas y otorga los ámbitos de revisión y evaluación. La autoridad configura, publica y asigna al evaluador.
2. El postulante envía documentos; el revisor observa uno y abre una ventana con plazo explícito. El propietario carga una versión nueva y la envía. El revisor repite la revisión completa y habilita la versión 2. Los documentos y el snapshot original se comparan sin cambios.
3. Otro postulante solicita retiro. Un revisor no puede decidirlo; la autoridad lo aprueba y la postulación permanece en el historial.
4. El evaluador asignado confirma notas; los intentos del postulante de calificarse son rechazados. La autoridad calcula y aprueba resultados.
5. Un candidato obtiene dos adjudicaciones visibles. Sus manifestaciones aceptar/diferir quedan versionadas y no alteran las publicaciones ni certifican aceptación institucional.
6. Solicita renuncia a una plaza adjudicada. La solicitud por sí sola no cambia su estado. La autoridad decide y se retiene la vacante; la nueva publicación no selecciona al siguiente candidato. Las notas y publicaciones anteriores permanecen idénticas.
7. La autoridad consulta la auditoría y descarga respuestas PDF/XLSX. Al finalizar se vuelven a comparar las tablas y los archivos de la DEMO existente y las evidencias históricas.

El registro de 101 solicitudes incluye rechazos esperados y un límite de acceso HTTP 429 respetado mediante `Retry-After`; no son 101 casos de prueba PHPUnit. Las acciones completas se ejercitaron por HTTP y se comprobaron contra la base. En navegador se revisaron los estados finales por perfil, no se repitió manualmente cada formulario.

## Hallazgos visuales pendientes

**VIS-01 — Criterios restringidos mostrados como pendientes.** En la vista del evaluador, el expediente #3 figura habilitado y su nota está confirmada, pero los criterios de revisión aparecen «Pendiente». El controlador no entrega el detalle de revisiones a este perfil y la vista usa ese texto como valor por defecto. Conviene indicar «Detalle de revisión restringido» u ocultar el listado para ese perfil. No corresponde ampliar permisos ni cambiar la habilitación. Evidencia: `pantallas/evaluador.png`; ubicación: `resources/views/application.blade.php`, apartado Revisión y habilitación, y `PortalController::application`.

**VIS-02 — Texto genérico de vacantes.** La autoridad ve correctamente el aviso de vacante retenida tras renuncia, pero el encabezado de clasificación dice «1 plazas sin candidato suficiente» aun existiendo un candidato fuera de cupo. Conviene distinguir el motivo de la vacante en ese texto. La publicación y la plaza retenida funcionan según las comprobaciones. Evidencia: `pantallas/autoridad.png`; ubicación: `resources/views/results.blade.php`, encabezado de clasificación.

Ambos hallazgos quedan abiertos en este informe de verificación. No se modificaron las vistas ni se declara una revisión visual sin observaciones. No se realizó una nueva certificación de accesibilidad ni una comprobación exhaustiva de todos los tamaños de pantalla.

## Incidencias del comprobador y correcciones

Los primeros intentos detectaron problemas de preparación del recorrido: asignación masiva de un rol protegido, sesiones iniciadas antes de asignar ámbitos, límite de intentos de acceso y directorio de archivos del servidor incorporado de PHP. Se corrigieron los comprobadores, manteniendo los controles de la aplicación.

El problema de directorio generó nueve PDF sintéticos nuevos en la carpeta habitual. Se identificaron por sus registros en las bases de recorrido, contenido y SHA-256; se comprobó que sus rutas no pertenecían a los doce documentos registrados en `ficct_demo`. Se trasladaron a sus carpetas privadas, sin sobrescribir originales. La carpeta habitual volvió a sus 18 archivos. El router privado ahora fija `useStoragePath` antes del arranque, y el recorrido final comprueba el aislamiento tras la primera carga y al terminar. Véanse `aislamiento-corregido.json` e `incidencias-comprobador.json`.

## Reproducir sin reemplazar evidencia histórica

Con las dependencias y PostgreSQL local disponibles, desde `sistema/`:

```powershell
python scripts/verify_traceability.py
python scripts/revalidate_03.py
python scripts/walkthrough_03.py --keep-server
```

El primer comando comprueba por defecto el ZIP corregido de 180 archivos en la carpeta superior. Acepta `--package` y `--output`. El segundo usa exclusivamente `ficct_test` y conserva los registros nuevos en `evidence/validacion-local-03/`. El tercero crea una base nueva en cada ejecución, usa un puerto local libre (por defecto 8103) y conserva sus credenciales, sesiones y archivos únicamente en `.local/`. Sin `--keep-server`, detiene su propio servidor al concluir. No ejecuta borrados ni reinicializaciones sobre `ficct_demo`.

Para inspección visual del recorrido final se conserva el servidor local del puerto 8103. Las credenciales sintéticas y su PID están en el `session.json` de la carpeta privada indicada; no se distribuyen en el ZIP. Las capturas contienen únicamente datos sintéticos.

## Límites de cierre

DEMO 0.3 conserva los ocho CU y treinta RF, y ahora tiene una nueva ejecución local y un recorrido HTTP reproducible. Persisten los dos hallazgos visuales, la recuperación y el contraste directo de los cuatro PDF y tres fotografías, las decisiones institucionales y las validaciones externas de seguridad, carga e infraestructura. No se autoriza uso institucional ni se inventan reglas para subsanaciones, renuncias o compatibilidad de plazas.
