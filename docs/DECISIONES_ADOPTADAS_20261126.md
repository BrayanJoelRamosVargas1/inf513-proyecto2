# Decisiones adoptadas para la operación real (26/11/2026)

Este documento sustituye a la lista de «decisiones pendientes» del 25/11. Cada punto tiene ahora una decisión, su fundamento en los documentos de la Dirección de Sistemas (Convocatorias 01/2026 y 02/2026, Resolución Vicerrectoral 021/2023) y lo que el sistema hace o no hace como consecuencia.

Criterio general: **el sistema registra, calcula y avisa; no sustituye a la autoridad.** Donde la norma fija una cifra, se usa. Donde no la fija, se adopta un valor operativo editable y se deja constancia aquí; la facultad puede cambiarlo sin tocar código.

## 1. Qué se entrega

Es el **Proyecto 2** (Laravel + React + PostgreSQL). El Proyecto #1 (sistema por correo) es otra entrega y no forma parte de este repositorio.

## 2. Modo de operación: ya no es una demostración

La instalación real usa `FICCT_EXPERIENCE=institutional`:

- Las convocatorias se crean y operan en modo **OFICIAL** (antes todo lo operativo era DEMO y la instalación institucional ni siquiera podía crear una convocatoria).
- Sin cuentas ni datos de ejemplo: la base se prepara con `php artisan db:seed` (solo catálogos y fuentes históricas públicas) y la primera cuenta se crea con `php artisan ficct:crear-administrador`.
- Solo existe la interfaz React (`/p2`). Las vistas Blade, el material de entrenamiento y la simulación de pagos quedan fuera de la instalación real (las rutas Blade redirigen a `/p2`).
- Los reportes ya no llevan la leyenda «DEMO · sin validez»; dicen que los resultados rigen cuando la autoridad competente los aprueba por resolución.
- La instalación institucional se niega a arrancar sobre una base con datos de entrenamiento (convocatorias DEMO o correos `.test`).
- El modo entrenamiento (`FICCT_EXPERIENCE=training`) sigue existiendo para practicar, en otra base y otro servidor.

`php artisan ficct:verificar-instalacion` comprueba todo lo anterior antes de abrir el sistema (ver `DESPLIEGUE.md`).

## 3. Reglas que fija la institución

| Tema | Decisión | Fundamento | Cómo queda en el sistema |
|---|---|---|---|
| Nota mínima de aprobación | **51 puntos sobre 100, provisional**: es un valor del sistema, no de la resolución | La resolución habla de «puntaje de aprobación» (Arts. 20 y 19) sin dar cifra. 51 es el mínimo de aprobación que la propia resolución usa en sus requisitos y rúbricas (materia aprobada con 51; las franjas del CPD empiezan en 51). El 55 del laboratorio es un requisito de admisión, no una nota del concurso | Campo `approval_threshold` por oferta; al crear una oferta nueva se propone 51 (`FICCT_NOTA_MINIMA`; vacío = sin propuesta). Quien no la alcanza queda fuera del ranking y de las plazas. **El HCF o el Decano debe confirmar el valor antes de la primera convocatoria**; la autoridad lo puede ajustar antes de publicar |
| Plazo de 48 h para publicar resultados | **Avisa, no bloquea** | Un retraso administrativo no debe impedir que los postulantes conozcan su resultado; la resolución obliga a la autoridad, no al postulante | Al aprobar se registra en la auditoría cuántas horas pasaron desde la última nota y si se cumplieron las 48 h |
| Plazos de 24 h (informe), 48 h (veedores) y 15 días (nombramiento) | **Solo se registran** | No hay un evento de origen fiable dentro del sistema (el informe, la acreditación y el nombramiento ocurren fuera) | Se anotan como actos en el registro institucional con su fecha; no se calculan alertas |
| Contenido de la convocatoria (Art. 8) | Período, lugar de recepción, lugar del examen, horas teóricas y prácticas: **campos disponibles, no obligatorios** | El Art. 8 los enumera, pero las convocatorias 01/2026 y 02/2026 no traen período ni lugar del examen; hacerlos obligatorios impediría publicar convocatorias como las vigentes. La autoridad los llena cuando los conoce | Campos opcionales visibles en la convocatoria y la oferta |
| Reparto del examen | **Fijo según la resolución para asignaturas y laboratorio; por áreas en sala de cómputo** | Arts. 16 y 15: el examen (60 puntos) se compone de escrito 60 % y práctico 40 % en asignaturas, y práctico 60 % y teórico 40 % en laboratorio. Las convocatorias reparten además las áreas de la sala de cómputo (soporte 50 / redes 25 / software 25) y de cada laboratorio | Con perfil `asignatura` o `laboratorio` el sistema rechaza una oferta cuyos pesos no sean esos; en sala los pesos por área son configurables y deben sumar 100 |
| Convocatoria desierta o vacante | **Se registra a mano; el sistema no cuenta convocatorias** | Art. 20: solo dos convocatorias consecutivas antes de declarar desierta; luego el Vicedecano (o el Decano en laboratorio) levanta un acta y puede elegir entre los aprobados, entre los presentados o invitar con formulario por excepción de la DICAA. La Convocatoria 02/2026 es el caso de la primera repetición | El acta y la opción elegida se guardan en el registro institucional; la nueva convocatoria se crea vinculada a la anterior. **No hay contador ni estado «desierta»**: quien convoca debe llevar la cuenta de las dos convocatorias (limitación conocida) |
| Aprobación del HCF o del Decano | **Se registra como acto, no bloquea** | Es un documento externo | Registro institucional con referencia y versión |
| Incompatibilidades (otro beneficio remunerado, 80 h/mes, cruce de horarios) | **La verifica el revisor con el expediente; el sistema no cruza horarios** | El requisito «no gozar de otros beneficios remunerados por la U.A.G.R.M.» se acredita documentalmente; el sistema no dispone de los horarios de clases | Requisito revisable individualmente; límite de 4 asignaturas por convocatoria aplicado |
| Retirados y renunciados frente al límite de asignaturas | **No cuentan** para el límite de 4 | Quien se retira ya no compite por esa plaza | Aplicado en postulación y vistas |
| Titulación en mora | **Condicional en los tres perfiles** | Art. 7(e) del reglamento de docencia (que rige también la sala de cómputo) y Art. 6(c) del de laboratorio la permiten: es una facultad, nunca una exigencia. Quien no está en mora marca «no aplica» con motivo | El criterio `graduacion_mora` es condicional en asignatura, sala y laboratorio. El perfil de sala de la convocatoria no lo lista como criterio; el revisor aplica el reglamento si el caso se presenta |
| Comisión y veedores | **Se registran integrantes, cargos y veedores; el sistema no valida la composición** | Art. 14 (docencia): Director de Carrera, docente titular y docente invitado, más el Vicedecano en ciclo común. Para sala o laboratorio: el encargado, el Director o el Vicedecano y dos invitados como mínimo. Laboratorio (Art. 13): el Director de Carrera y el Jefe del Laboratorio. Veedores: un representante de la Federación y un delegado del Vicerrectorado, acreditados 48 h antes, sin voto | Registro institucional de comisión con versiones; al abrir la evaluación y al aprobar, la auditoría deja constancia de si había comisión y acto registrados. **Los veedores no operan en el sistema** (no tienen cuenta ni permisos) |
| Catálogo de laboratorios | **Sala de cómputo, redes, software y electrónica** con sus plazas | Convocatoria 01/2026: 20, 3, 6 y 3 plazas; 02/2026: 13 y 3 | Ofertas por tipo de auxiliar, editables por la autoridad |
| Nombramiento (duración, inicio, no ratificación) | **Fuera del sistema** | Lo emite el Decano en un plazo de 15 días; el sistema termina en los resultados aprobados | El sistema entrega resultados aprobados, acta y reportes PDF/XLSX para el nombramiento |
| Rol de director de carrera | **No se crea** | Las cartas de solicitud van dirigidas al Vicedecano (docencia y sala) o al Decano (laboratorio): ambos los cubre la autoridad de la convocatoria | Rol «autoridad» por convocatoria |
| Formulario CPD oficial | **Se adjunta el formulario emitido por el CPD y el revisor registra los méritos (máximo 40)** | El CPD es una dependencia externa, sin integración | Documento CPD versionado + méritos manuales |
| Subsanación y recursos | **Se mantienen las ventanas con plazo fijado por la autoridad** | La resolución no regula subsanaciones; una ventana acotada y auditada es más justa que el rechazo inmediato | Ventanas con vencimiento, versiones y decisión separada |

## 4. Qué puede y qué no puede hacer cada rol

Principio: **nadie decide sobre su propio expediente, y nadie ve más de lo que necesita para su función.**

| Rol | Puede | No puede ni ve |
|---|---|---|
| Postulante | Postular (hasta 4 asignaturas por convocatoria), cargar documentos, enviar, subsanar, retirarse, ver su estado y los resultados publicados | Ver expedientes ajenos, revisar, calificar o decidir sobre resultados de una convocatoria donde postula |
| Revisor | Revisar documentos y criterios de los expedientes **ya enviados**, habilitar o inhabilitar, registrar méritos del CPD, abrir y vencer subsanaciones | Ver archivos de un borrador, calificar exámenes, publicar resultados, revisar su propio expediente |
| Evaluador | Calificar las áreas que se le asignaron en las ofertas asignadas | Documentos privados de los postulantes, habilitación, notas de otros evaluadores, publicación |
| Autoridad (por convocatoria) | Configurar, publicar y cerrar la convocatoria, asignar evaluadores, resolver empates, aprobar y publicar resultados, descargar reportes | Ser también revisora o evaluadora de la misma convocatoria, asignarse a sí misma como evaluadora, abrir subsanaciones, ver archivos privados, decidir donde postula |
| Administración técnica | Crear cuentas, asignar y revocar permisos por convocatoria, tipos de auxiliar; consultar el historial de sus propios actos | Crear convocatorias, revisar, calificar o aprobar; recibir funciones académicas en su misma cuenta; conservar el acceso a una cuenta que creó (la persona cambia la clave en su primer ingreso) |

Medidas generales: contraseñas de 12 caracteres como mínimo; **cambio obligatorio de la clave inicial en el primer ingreso** de toda cuenta creada por otra persona (administración o consola); el postulante no ve sus notas por área ni su nota final hasta que la autoridad aprueba la publicación, y nunca sabe qué evaluador las puso; un cambio de perfil retira los permisos por convocatoria de la cuenta; una persona con cualquier permiso no revocado en una convocatoria no puede postular en ella; límite de intentos de ingreso por cuenta y origen; cierre de sesiones al cambiar permisos o clave; auditoría inmutable de cada acto; documentos con análisis antivirus (ClamAV) y validez de 24 h; el registro de usuarios solo crea postulantes (nunca revisores ni autoridades).

Recuperación de acceso sin correo saliente: `php artisan ficct:restablecer-clave correo@…` genera una clave nueva que la administración entrega en persona y cierra las sesiones abiertas.

## 5. Decisiones técnicas del equipo

| Tema | Decisión |
|---|---|
| Blade y React en paralelo (F-12) | React es la única interfaz de la instalación real. Blade queda solo para entrenamiento y pruebas históricas |
| Doble trigger de inmutabilidad en `documents` (B-17) | Se deja: es redundante pero inofensivo; retirarlo exige una migración sobre una tabla con datos |
| Mensaje de correo ya registrado (B-24) | Se mantiene el aviso: el registro es abierto y la validación ya lo revela; el límite de intentos mitiga el abuso |
| Desempate con fecha pasada (B-31) y memoización de permisos (B-33) | Sin cambio |
| Seeders | El `DemoSeeder` solo corre en entrenamiento; la instalación real usa `InstitutionalReferenceSeeder` |

## 6. Lo que solo se puede comprobar en el servidor

Estado de Tecnoweb, el repositorio remoto y ClamAV real. `ficct:verificar-instalacion` comprueba lo que se puede comprobar desde el servidor (antivirus, base, migraciones, HTTPS, cuentas, compilación) y falla si algo obligatorio falta.

## 7. Limitaciones conocidas tras la auditoría del 26/11/2026

Dos revisiones independientes (normativa y roles, contra la transcripción de la Resolución 021/2023) encontraron lo siguiente. Lo corregido consta en las secciones anteriores; esto es lo que **no** se resolvió y por qué.

| Tema | Situación | Por qué no se cambió |
|---|---|---|
| Identidad única de la persona | No hay CI ni registro universitario único; facultad y carrera son texto libre. Quien abre dos cuentas podría esquivar el límite de 4 asignaturas | Exige capturar y validar un dato nuevo en el registro. Mientras tanto el revisor contrasta la cédula del expediente |
| Comisión vinculada a cuentas | La comisión es un registro de texto; los evaluadores se asignan por área aparte | Unificarlos exige un modelo nuevo (comisión, cargos y vigencia) y la institución aún no valida la composición |
| Veedor como rol | No tiene cuenta ni acceso | La resolución les da solo la acreditación 48 h antes y presencia sin voto; no necesitan operar el sistema |
| Aprobación del HCF o Decano | Se registra como acto; no se exige para aprobar | Es un documento externo. La auditoría deja constancia de si estaba registrado |
| Mérito del CPD frente a su registro | El mérito que digita el revisor no se cruza con las rúbricas del formulario CPD | El CPD emite una puntuación única y su rúbrica tiene contradicciones que la institución debe aclarar |
| Plaza múltiple | Una persona puede ser seleccionada en varias ofertas sin alerta | El cruce de horarios y el máximo mensual no están en el sistema |
| Convocatoria desierta | Sin contador ni estado (ver arriba) | Lo lleva quien convoca |
| Corrección de notas (`Evaluation::correct`) | Código sin ruta HTTP | Las correcciones pasan por rectificaciones con segunda autoridad; la función no es alcanzable |
