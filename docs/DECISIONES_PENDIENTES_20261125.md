# Decisiones pendientes tras la auditoría del 25/11/2026

Cada punto de esta lista **no se implementó porque falta un dato o una decisión que el sistema no debe inventar**. Para cada uno se indica quién decide y qué hace falta. Estado general de las correcciones: `CORRECCIONES_AUDITORIA_20261125.md`.

## A. Decide la cátedra (INF513)

| Tema | Pregunta | Efecto si cambia |
|---|---|---|
| Qué se entrega | El enunciado de `documentos_tecno` es el Proyecto #1 (sistema por correo con comandos `LISPER`/`INSPER`, cierre 20/11/2026 15:00). Este repositorio es el P2 (React + Laravel + PostgreSQL). ¿Cuál es la entrega vigente y hasta cuándo? | Si hay que cumplir el P1, es un sistema nuevo (lector de buzón, analizador del asunto, respuestas por correo). No se empezó sin confirmación. |
| Consigna del P2 | La consigna del P2 que citan los documentos no está en `documentos_tecno`. ¿Se puede aportar? | Sin ella no se puede auditar el contrato del P2. |
| Plazo | 15:00 del 20/11/2026 (enunciado P1) frente a 20:00 del último día de clases (consigna P2 citada). | Solo documental. |
| Nombres de entrega | Archivo `…P1_grupoXXsa` y repositorio `inf513-proyecto1` frente a `…P2…` y `inf513-proyecto2`. | Cambia `scripts/package_academic_release.py` y el nombre del repositorio. |
| Paquete de entrega | El tar del 24/11 ya no representa el árbol. ¿Se regenera y se acepta de nuevo? | Requiere nueva instalación limpia y nuevo hash. |

## B. Decide la institución (Resolución 021/2023, FICCT/DICAA)

| Tema | Pregunta | Referencia |
|---|---|---|
| Nota mínima final | La resolución habla de "puntaje de aprobación" sin cifra. ¿Cuál es? Hoy todo habilitado con nota entra al ranking. | R3-47, SOL-06 |
| Plazos | Control y alerta de 48 h (publicar el promedio), 24 h (informe de la comisión), 48 h (acreditar veedores) y 15 días (nombramiento en laboratorio): ¿bloquean o solo avisan? | R3-18, R3-22, R3-24, R3-29 |
| Contenido de la convocatoria | Carga horaria (ht/hp), período académico y lugar de recepción y examen: ¿campos obligatorios? ¿en qué formato? | R3-10, R3-40 |
| Convocatoria desierta o vacante | Contador de dos convocatorias consecutivas, acta y opciones (elegir entre aprobados, entre presentados o invitar con excepción ante DICAA). La opción "diferir" actual no tiene base en la resolución. | R3-26 a R3-28 |
| Aprobación del HCF o Decano | ¿Hay que registrar el número de resolución u oficio para publicar y para designar? | R3-11, R3-24 |
| Reparto del examen | ¿Se fuerza escrito 60 / práctico 40 en docencia y práctico 60 / teórico 40 en laboratorio, o se mantiene editable con aviso? Las áreas de laboratorio de la convocatoria (soporte, redes, software) no coinciden con la composición de la resolución. | R3-20, R3-43 |
| Incompatibilidades | ¿Una segunda plaza remunerada impide adjudicar? ¿Se controla el límite de 80 h/mes y el cruce de horarios? | R3-33, R3-49, SOL-18 |
| Retirados y límite | ¿Las postulaciones retiradas o renunciadas cuentan para el límite de 4 asignaturas? | B-28 |
| Titulación en mora | El perfil "sala" omite la condición de titulación en mora del Art. 7(e). ¿Se agrega? | R3-06, SOL-20 |
| Comisión y veedores | Composición mínima validada (terna, dos docentes invitados) y plazo de acreditación. La "terna" de laboratorio es ambigua en el texto. | R3-17, R3-18, R3-42 |
| Catálogo de laboratorios | Laboratorios autorizados por unidad y modalidad (servicio social o académico). | R3-37, R3-38 |
| Nombramiento | Duración, inicio y fin, y la regla de no ratificar. Hoy queda fuera de la selección. | R3-29 |
| Rol director de carrera | RF-01 lo menciona y no existe. ¿Se crea o lo cubre la autoridad por convocatoria? | RF-01, CU1 |
| CPD oficial y reporte resumen | Formato oficial del formulario CPD y del "formulario de consolidación" para RF-15 y RF-29. | RF-15, RF-29 |
| Subsanación y recursos | La resolución no regula subsanaciones ni reclamos. Las ventanas actuales son técnicas de la DEMO. ¿Se mantienen? ¿El CPD puede subsanarse (Art. 13 pide formulario sin enmiendas)? | R3-45, R3-46 |

## C. Decide el equipo (decisiones técnicas)

| Tema | Opciones |
|---|---|
| Blade y React en paralelo (F-12) | Mantener ambos, o retirar Blade cuando la SPA cubra todo. |
| Doble trigger de inmutabilidad en `documents` (B-17) | Retirar uno con una migración revisada. |
| Mensaje de correo ya registrado (B-24) | Mantener el aviso o responder igual en todos los casos. |
| Desempate en el pasado (B-31) | Definir con qué hito se compara la fecha programada. |
| Seeders (D §3.3) | Unificar la fecha de examen de la convocatoria 01 y los requisitos de sala y laboratorio entre `DemoSeeder` e `InstitutionalReferenceSeeder`. |
| Accesibilidad | Añadir `caption` a las tablas Blade y una prueba axe a `npm test`. |
| Desempate con fecha pasada (B-31) y memoización de permisos (B-33) | Se dejaron como están; ver `CORRECCIONES_AUDITORIA_20261125.md`. |

## D. Cosas que solo se pueden comprobar fuera de este equipo

Estado de Tecnoweb, del repositorio en GitHub (el repositorio local no tiene remoto), de la entrega en la plataforma Moodle y de ClamAV real en el servidor.
