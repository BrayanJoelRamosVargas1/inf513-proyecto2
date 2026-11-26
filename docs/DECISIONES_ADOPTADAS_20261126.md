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
| Nota mínima de aprobación | **51 puntos** sobre 100 como valor propuesto en cada oferta nueva | La convocatoria exige 51 puntos para aprobar la materia a la que se postula y para el PPA; la resolución habla de «puntaje de aprobación» sin cifra. El 55 del laboratorio es un requisito de admisión (promedio ponderado), no una nota del concurso | Campo `approval_threshold` por oferta, con 51 por defecto (`FICCT_NOTA_MINIMA`). Quien no la alcanza queda fuera del ranking y de las plazas. Vacío = sin mínimo. La autoridad puede ajustarla antes de publicar |
| Plazo de 48 h para publicar resultados | **Avisa, no bloquea** | Un retraso administrativo no debe impedir que los postulantes conozcan su resultado; la resolución obliga a la autoridad, no al postulante | Al aprobar se registra en la auditoría cuántas horas pasaron desde la última nota y si se cumplieron las 48 h |
| Plazos de 24 h (informe), 48 h (veedores) y 15 días (nombramiento) | **Solo se registran** | No hay un evento de origen fiable dentro del sistema (el informe, la acreditación y el nombramiento ocurren fuera) | Se anotan como actos en el registro institucional con su fecha; no se calculan alertas |
| Contenido de la convocatoria (Art. 8) | Período, lugar de recepción, lugar del examen, horas teóricas y prácticas: **opcionales** | Las convocatorias 01 y 02 reales no traen período ni lugar del examen; exigirlos impediría publicar convocatorias como las vigentes | Campos opcionales visibles en la convocatoria y la oferta |
| Reparto del examen | **Pesos configurables por oferta; deben sumar 100** | Las convocatorias reparten el examen distinto según el tipo (sala de cómputo: soporte 50 / redes 25 / software 25; redes: 25 / 75; software: 100; electrónica: 25 / 75). El 60/40 de la resolución (escrito/práctico y práctico/teórico) se carga como pesos de la oferta | Esquemas `AREAS_100` y `COMPONENTS_100` (los `DEMO_*` se aceptan por compatibilidad) |
| Convocatoria desierta o vacante | **La autoridad registra el acta y abre otra convocatoria**; no hay contador automático | La Convocatoria 02/2026 es exactamente ese caso (menos asignaturas y plazas: 32→16 en sala, 2→1 en laboratorio), resuelto con una convocatoria nueva, no con un flujo automático | El acta se guarda en el registro institucional; las plazas no cubiertas se ofertan de nuevo creando la convocatoria siguiente |
| Aprobación del HCF o del Decano | **Se registra como acto, no bloquea** | Es un documento externo | Registro institucional con referencia y versión |
| Incompatibilidades (otro beneficio remunerado, 80 h/mes, cruce de horarios) | **La verifica el revisor con el expediente; el sistema no cruza horarios** | El requisito «no gozar de otros beneficios remunerados por la U.A.G.R.M.» se acredita documentalmente; el sistema no dispone de los horarios de clases | Requisito revisable individualmente; límite de 4 asignaturas por convocatoria aplicado |
| Retirados y renunciados frente al límite de asignaturas | **No cuentan** para el límite de 4 | Quien se retira ya no compite por esa plaza | Aplicado en postulación y vistas |
| Titulación en mora | **Se aplica como la convocatoria**: sí en docencia y laboratorio, no en sala de cómputo | La Convocatoria 01/2026 lo incluye en docencia (hasta 2 periodos) y en laboratorio (mientras no se consigne nota final) y no lo incluye en sala de cómputo | Perfiles de requisitos por modalidad |
| Comisión y veedores | **Se registran los integrantes y sus cargos; el sistema no valida la composición** | El texto de la resolución habla de «terna» y enumera dos cargos; el sistema no inventa el tercero | Registro institucional de comisión con versiones |
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
| Revisor | Revisar documentos y criterios, habilitar o inhabilitar, registrar méritos del CPD, abrir subsanaciones | Calificar exámenes, publicar resultados, revisar su propio expediente |
| Evaluador | Calificar las áreas que se le asignaron en las ofertas asignadas | Documentos privados de los postulantes, habilitación, notas de otros evaluadores, publicación |
| Autoridad (por convocatoria) | Configurar, publicar y cerrar la convocatoria, asignar evaluadores, resolver empates, aprobar y publicar resultados, descargar reportes | Ingresar notas en nombre de un evaluador; ver los archivos privados sin permiso de revisión; decidir donde postula |
| Administración técnica | Crear cuentas, asignar y revocar permisos por convocatoria, tipos de auxiliar | Crear convocatorias, revisar, calificar o aprobar con solo ese perfil |

Medidas generales: contraseñas de 12 caracteres como mínimo y cambio de clave por la propia persona; límite de intentos de ingreso por cuenta y origen; cierre de sesiones al cambiar permisos o clave; auditoría inmutable de cada acto; documentos con análisis antivirus (ClamAV) y validez de 24 h; el registro de usuarios solo crea postulantes (nunca revisores ni autoridades).

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
