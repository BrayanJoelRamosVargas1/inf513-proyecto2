# DEMO 0.3 — Expedientes y participación

Fecha: 10/11/2026. Continúa el commit `2211f50` de DEMO 0.2. No se reconstruyó el sistema. Ocho CU y RF-01..RF-30 intactos. Los procedimientos nuevos son **DEMO**, sujetos a las confirmaciones institucionales del informe `03-CONTRASTE_NORMATIVO_DEMO_0_3.md`.

## Contraste normativo y conservación

Se comprobó la auditoría y el inventario heredados, y la disponibilidad de las rutas de originales. Los cuatro PDF y tres fotografías no están en esas rutas ni en el ZIP de código. Se consultaron publicaciones institucionales relacionadas y se documentaron sus alcances; no completan ni sustituyen los originales. **Contraste directo de originales: NOT_VERIFIED.** No se inventaron plazos universitarios, autoridades formales, incompatibilidades ni cobertura automática de vacantes.

Antes de modificar se ejecutaron las 70 pruebas/229 aserciones de 0.2 y una restauración en otra base. Las evidencias están en `evidence/baseline-02/`. La migración 000004 se aplicó incrementalmente. `evidence/migration-03-preservation.json` acredita que las filas y columnas originales de quince tablas permanecieron idénticas inmediatamente antes/después de migrar. Los accesos y exportaciones posteriores agregan eventos legítimos de auditoría.

No se modificaron convocatorias históricas, documentos enviados, valores de notas ni publicaciones de la DEMO existente. Las pruebas nuevas usan datos sintéticos en `ficct_test`. Los snapshots iniciales creados al migrar se identifican como «Estado conocido al migrar», sin inventar una fecha de presentación o un responsable pasado.

## 1. Subsanaciones

- Solo sobre un expediente enviado, observado/no habilitado, en recepción o recepción cerrada y antes de evaluación. Abrir requiere permiso vigente de revisor o autoridad y un propietario distinto. Es un alcance técnico conservador DEMO.
- El responsable selecciona documentos requeridos, motivo, referencia de evidencia y un vencimiento futuro explícito. No se aplica un plazo universitario predeterminado. La zona se muestra como America/La_Paz; el instante de vencimiento es inclusivo.
- El propietario carga nuevos archivos privados PDF/JPG/PNG hasta 5 MB. Cada carga conserva ID, versión, autor por auditoría, fecha y SHA-256. Nunca se sobrescribe la carga anterior.
- Las cargas pendientes/vencidas no integran el expediente vigente. Al enviar, todos los documentos autorizados deben estar subsanados. Se congela un snapshot completo de la nueva versión con referencias exactas de documentos.
- Un revisor distinto del propietario repite todos los criterios de documentos y requisitos. Se conserva el resultado anterior y el nuevo, con identidad y motivo. Solo el cumplimiento completo habilita; no se convierten observaciones en cumplimiento automático.
- El envío debe ocurrir dentro de plazo; la revisión puede ocurrir después. Si no hubo entrega completa, un responsable cierra explícitamente la ventana vencida, documenta motivo e inhabilitación DEMO y conserva las cargas. Una nueva ventana puede autorizarse con otro plazo. No se reutilizan silenciosamente archivos de ventanas vencidas.
- Las rutas antiguas de revisión/habilitación no pueden saltarse el circuito versionado. Durante una subsanación pendiente se bloquea el registro inicial de méritos y el cierre hacia evaluación/resultados/archivo. La interfaz oculta acciones que no corresponden al estado.

## 2. Retiros y renuncias

El propietario solicita, con motivo y referencia de evidencia; otra autoridad vigente de la convocatoria aprueba o rechaza. Presentar la solicitud **no** retira ni renuncia automáticamente. Una petición pendiente bloquea publicar/cerrar la etapa. No se permite decidir dos veces ni tramitar dos solicitudes pendientes sobre la misma postulación.

Retiro significa dejar una postulación sin adjudicación vigente. Renuncia exige una plaza contenida en la publicación vigente, y se distingue incluso si el ranking actual está bajo revisión. La clasificación se comprueba otra vez al decidir. Un rechazo conserva la participación; una aprobación conserva el expediente, las notas y la historia, pero excluye la postulación de operaciones nuevas y del ranking activo.

Si se afectan resultados en evaluación/publicados, se genera una revisión enlazada a la solicitud, con ranking anterior/nuevo y cambios, y se exige otra publicación con acta. Las publicaciones anteriores permanecen inmutables. Las rectificaciones 0.2 siguen funcionando para candidatos activos y no reactivan una postulación retirada o renunciada.

**Renuncia y plaza vacante:** el cupo original `offers.seats` se conserva. `held_vacancies` retiene esa plaza y un evento inmutable identifica postulación, solicitud y publicación de origen. El cálculo usa el cupo disponible descontando las vacantes retenidas. Incluso al aprobar expresamente una nueva publicación, esas plazas permanecen vacantes: no se adjudican al siguiente candidato. Su eventual cobertura necesita un procedimiento confirmado y otro incremento. No se presenta esta retención técnica como interpretación de una norma de reemplazo.

No se libera automáticamente el conteo de cuatro asignaturas por retiro ni se borra la postulación para permitir otra sobre la misma oferta. Los efectos institucionales de esos casos continúan pendientes.

## 3. Múltiples adjudicaciones

La página Adjudicaciones muestra las plazas de publicaciones vigentes del propietario y, para autoridades, las de sus convocatorias autorizadas. La vista de autoridad puede ser parcial y lo declara. No expone adjudicaciones de ámbitos ajenos para completar artificialmente un cruce global.

Se detectan varias adjudicaciones visibles, sin prohibirlas ni anularlas por su cantidad. El propietario puede registrar «aceptar para esta DEMO» o «diferir». Cada manifestación tiene publicación de referencia, versión, motivo, evidencia, actor y fecha, y queda inmutable. Requiere la versión actual y no puede referirse a una publicación reemplazada. La historia anterior se conserva; una republicación necesita manifestación sobre la nueva publicación.

No hay regla implícita de incompatibilidad, certificación de compatibilidad, aceptación institucional ni nombramiento. Una autoridad no puede manifestarse como si fuera el propietario. Rechazar una plaza se tramita mediante renuncia, no mediante una aceptación que oculte la pérdida de una adjudicación. Las peticiones de retiro/renuncia pendientes deben resolverse antes de otra manifestación.

## Migración y consistencia

`2026_11_10_000004_add_participation_processes.php` agrega:

| Elemento | Tratamiento |
|---|---|
| `applications.participation/dossier_version` | Estado operativo adicional; no cambia el estado original de envío ni el snapshot de configuración. |
| `offers.held_vacancies` | Entre cero y el cupo original, mediante restricción PostgreSQL. |
| `amendments` y `documents.amendment_id` | Ventanas y cargas anexadas. Índice parcial impide dos ventanas pendientes por expediente. |
| `dossier_snapshots` | Expedientes/revisiones inmutables. Backfill del estado actualmente conocido. |
| `participation_requests/decisions` | Petición pendiente única por postulación y decisión única/inmutable por petición. |
| `vacancy_events` | Vacante declarada una sola vez por renuncia aprobada, con publicación de origen. |
| `award_choices` | Manifestaciones anexadas por publicación y versión; no modifican la selección. |
| `result_revisions.participation_request_id` | Reutiliza el circuito de revisión 0.2; el vínculo de rectificación pasa a ser opcional sin cambiar registros anteriores. |
| Cinco triggers nuevos | Inmutabilidad de documentos, snapshots de expediente, decisiones, vacantes y manifestaciones. Se conservan los cuatro anteriores. |

Se reutiliza `Access::transaction`: cerrojo de permisos/identidad → convocatoria → expediente/oferta → movimiento. Por eso una revocación, una confirmación y una decisión no ven identidades/versiones mezcladas. Índices únicos y restricciones refuerzan la validación del servidor. El orden serial permite que una carga anterior al envío se incluya y rechaza una posterior; nunca cambia un snapshot ya enviado.

La serialización global sigue siendo una decisión conservadora de DEMO, con rendimiento institucional sin medir. Los triggers protegen frente a UPDATE/DELETE normales, no frente a un operador de infraestructura que los desactive o cambie archivos en disco. No hay rollback destructivo de la migración; recuperar mediante respaldo restaurado y verificado en otra base. Nunca `migrate:fresh` sobre la DEMO.

## Pruebas ejecutadas

La regresión conserva las **70 pruebas anteriores**. `ParticipationTest` agrega **32 pruebas** sobre autorización, ventanas/plazos, documentos, revisión completa, vencimiento, inmutabilidad, retiros, renuncias, vacantes, publicaciones, aceptación y cruce de adjudicaciones. Resultado completo y aserciones finales: `evidence/tests.txt`, `phpunit.xml` y `release-03.json`.

| Escenario | Evidencia |
|---|---|
| Subsanación, nueva versión y revisión repetida | P01..P04 |
| Vencimiento, identidad, documentos autorizados y envío congelado | P05..P09/P29/P30 |
| Impedir saltos del circuito y excluir cargas vencidas | P10/P11 |
| Documentos/snapshots inmutables | P12/P13 |
| Retiro, rechazo, diferencia frente a renuncia y preservación | P14..P18 |
| Peticiones pendientes, autorización y doble decisión | P19..P22/P27 |
| Manifestaciones versionadas, publicaciones obsoletas y privacidad | P23..P26/P28/P31 |
| Rectificar otro candidato sin reactivar al renunciante ni cubrir su vacante | P32 |
| Cinco carreras nuevas entre procesos separados | `participation-concurrency.json` |
| Regresión de seis carreras anteriores | `concurrency.json`, `governance-concurrency.json` |
| Restauración con documentos subsanados, retiro/renuncia y manifestaciones | `participation-restore.json` |
| Restauración de DEMO existente, con doce documentos | `restore.json` |
| Migración sin cambiar datos anteriores | `migration-03-preservation.json` |
| Seguridad y lectura HTTP real, PDF/XLSX de trece columnas | `security-http.json`, `participation-http.json` y reportes 03 |
| Instalación en carpeta, dependencias y base nuevas de este equipo | `clean-install-03.json` |
| Vistas de expediente/adjudicaciones en navegador y móvil | `screenshots-03/` |

Las cinco carreras nuevas cubren doble envío, doble revisión, doble decisión de renuncia, manifestaciones incompatibles sobre la misma versión y carga simultánea con envío. Se exige un solo movimiento efectivo donde corresponde. Si la carga termina antes del envío, ambos pueden aceptarse y el verificador comprueba el documento exacto congelado. La restauración compara contenidos de veintiuna tablas, archivos mediante SHA-256 y nueve triggers.

Se corrigió el comprobador de texto PDF para reconocer encabezados que se ajustan en varias líneas al agregar columnas; el PDF se renderizó e inspeccionó sin recortes. No se atribuyó ese ajuste de pruebas a un error de normativa ni se descartaron regresiones. Las evidencias de versiones anteriores conservan su identidad; no se contabilizan como ejecuciones nuevas.

## Ejecutar y comprobar

Iniciar `INICIAR_DEMO.ps1` desde el proyecto; aplica solo migraciones pendientes y conserva la información existente. Portal: `http://127.0.0.1:8099`. Cuentas sintéticas privadas en `.local/ACCESOS_DEMO.txt`, fuera de Git/ZIP.

```powershell
python scripts/test.py
python scripts/concurrency.py
python scripts/governance_concurrency.py
python scripts/participation_concurrency.py
python scripts/verify_restore.py ficct_test
python scripts/verify_restore.py
python scripts/security_http.py
python scripts/participation_http.py
php vendor/bin/pint --test
```

Los ensayos de concurrencia deben ejecutarse después de PHPUnit y sin otro ensayo escribiendo simultáneamente en `ficct_test`. Las restauraciones crean bases nuevas y conservan sus respaldos privados en `.local`. `verify_clean_install.py` prueba otra instalación local con dependencias nuevas; requiere Git y las herramientas descritas en README. No acredita una máquina sin herramientas ni validación externa de seguridad.

## Recorrido de demostración

1. En una convocatoria DEMO propia, enviar expediente y registrar observación por revisor.
2. Abrir Expediente y participación; el revisor/autoridad autoriza documentos y plazo. El propietario carga y envía; el revisor realiza la revisión completa sobre esa versión.
3. El propietario puede solicitar retiro antes de adjudicación. La autoridad decide con motivo; revisar estado e historia sin borrar el envío.
4. Tras adjudicar, el propietario solicita renuncia. La autoridad decide; revisar vacante retenida, revisión de ranking y nueva publicación sin reemplazante.
5. En Adjudicaciones, el propietario ve sus plazas y registra manifestaciones por publicación; varias plazas solo generan alerta, no una exclusión automática.

Las calificaciones/adjudicaciones de la DEMO 0.2 existente no se modificaron para fabricar una muestra nueva. Los casos completos se ejecutaron en la base de pruebas; las pantallas reales de la DEMO muestran la historia preservada y los formularios disponibles según estado.

## Límites y siguiente trabajo

Faltan originales y validación normativa directa, plazos/autoridades formales, recursos, subsanaciones después de evaluación, efectos de retiro sobre límites, compatibilidades, aceptación institucional, cobertura de vacantes, firmas y retención. No se agregan sanciones ni exclusiones por analogía. También siguen pendientes carga, accesibilidad formal, seguridad independiente e infraestructura de producción.

**DEMO 0.3 técnica verificable en este alcance. No se declara oficial ni apta para despliegue institucional.**
