# Corrección de postulación y espacios por perfil — DEMO 0.3

Fecha: 18/11/2026. Esta entrega continúa la base existente; no modifica las migraciones, los ocho CU ni las definiciones de los treinta RF.

## Problemas corregidos

- El registro creaba la cuenta pero la primera redirección cerraba su sesión: faltaba cargar la versión de permisos generada por la base antes de guardarla en sesión. Se corrige refrescando la cuenta antes de autenticarla; R12 y el recorrido real verifican que el nuevo estudiante puede entrar y postular.

- La oferta mostraba «Iniciar postulación» aunque el estudiante ya tenía expediente. Ahora enlaza a continuar el borrador o consultar lo enviado.
- El estado «Recepción publicada» se confundía con un plazo efectivamente abierto. Los botones comprueban también fecha de apertura, cierre inclusivo y modo DEMO; el servidor utiliza la misma comprobación de calendario. Se informa de un ámbito incompatible o del límite de asignaturas ya alcanzado, sin cambiar esas reglas.
- El borrador conserva su historial cuando vence el plazo. No ofrece cargar ni enviar fuera del plazo. El envío queda deshabilitado mientras falten documentos obligatorios; el servidor mantiene su validación independiente.
- El inicio genérico se reemplaza por instrucciones de postulación, administración técnica, gestión de convocatorias o trabajo asignado. Se conservan responsabilidades combinadas y expedientes propios.
- Revisor y evaluador tienen una bandeja de expedientes enviados de sus ámbitos. El evaluador ve las ofertas que tiene asignadas; el revisor no recibe expedientes propios para decidir sobre ellos. La bandeja muestra hasta cincuenta expedientes y permite abrir las convocatorias para consultar el resto.
- Se retiran enlaces de adjudicaciones del administrador técnico, revisor o evaluador sin participación propia ni responsabilidad de autoridad. La creación de convocatoria se presenta en el espacio de autoridad. Esto no elimina las capacidades administrativas preexistentes del servidor ni sustituye permisos institucionales.
- El evaluador no recibe paneles de documentos ni de revisión ni el motivo privado de habilitación. Se mantiene el estado de habilitación y su evaluación asignada.
- Se impide en el servidor que un postulante con permiso de revisor revise, habilite o registre los méritos iniciales de su propia postulación. Se conservan otras responsabilidades válidas de la cuenta.
- Se ocultan formularios de evaluación, cálculo, asignación y rectificación cuando faltan las condiciones necesarias. Las publicaciones y los exámenes de desempate existentes siguen bloqueando la edición inicial de notas. El cálculo espera todas las notas confirmadas y una composición DEMO definida.

## Para qué sirve cada perfil

| Perfil o responsabilidad | Trabajo principal |
|---|---|
| Postulante | Registrarse, elegir una oferta, adjuntar documentos, confirmar envío, consultar revisión, subsanar cuando exista autorización y consultar sus resultados. |
| Revisor de convocatoria | Revisar evidencia y requisitos, decidir habilitación, registrar respaldo y mérito CPD, gestionar subsanaciones según el procedimiento DEMO. |
| Evaluador de convocatoria | Registrar y confirmar las notas de las áreas que le fueron asignadas, sobre postulantes habilitados durante evaluación. |
| Autoridad de convocatoria | Configurar y conducir el proceso, asignar evaluadores, consultar consolidados, gestionar desempates y aprobar publicaciones DEMO. |
| Administrador técnico | Mantener cuentas, perfiles, permisos y tipos de auxiliatura. Las tareas académicas se muestran según responsabilidades expresamente asignadas. |

«Personal/docente» es el perfil base de las cuentas de revisor y evaluador; la responsabilidad efectiva depende del permiso vigente por convocatoria. La aplicación no identifica automáticamente «autoridad» con un cargo institucional específico. Un mismo usuario puede tener más de una responsabilidad legítima.

## Recorrido del estudiante

1. Abrir `/registro` si todavía no tiene cuenta; completar sus datos sin elegir permisos académicos.
2. Entrar a su panel y consultar una convocatoria que indique «Recepción abierta ahora».
3. Revisar los requisitos de la oferta y pulsar «Iniciar postulación».
4. Guardar cada documento obligatorio. El expediente tiene comprobante propio; puede salir y volver mediante «Continuar mi postulación».
5. Cuando estén todos los documentos, pulsar «Confirmar envío definitivo». Guardar archivos por sí solo no envía el expediente.
6. Consultar el expediente desde «Mis postulaciones». Las correcciones posteriores utilizan el proceso de subsanación autorizado, conservando el envío original.

## Alcance de la comprobación

La regresión incluye doce pruebas nuevas `RoleWorkflowTest::test_r01_*` a `test_r12_*`, además de las 136 identidades anteriores. V01 se adapta para comprobar la ausencia completa del panel de revisión para el evaluador, conservando las comprobaciones de acceso a archivos y preservación de revisiones.

Los resultados ejecutados y las comprobaciones con navegador se entregan en `evidence/roles-postulacion-03/`. Las pruebas usan datos sintéticos y una base separada. No constituyen autorización institucional ni una auditoría externa. Las decisiones normativas pendientes de la adenda permanecen pendientes.

Resultado ejecutado: **148 pruebas / 685 aserciones**, 136 identidades previas conservadas. Chromium: **13 comprobaciones / 97 respuestas HTTP** (76 de estado 200 y 21 redirecciones 302), ocho pantallas comprobadas con axe A/AA y ancho móvil de 390 px. Sin cambios en las 31 tablas ni en los 23 archivos privados de la instalación operativa. Se conserva el intento fallido del navegador que permitió detectar el problema del registro.
