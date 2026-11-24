# Seguridad y accesibilidad ampliadas — DEMO 0.3

Fecha: 11/11/2026. Corrección incremental sobre el cierre de accesibilidad anterior. Se mantienen los ocho CU y treinta RF canónicos; no se inicia DEMO 0.4 ni se deciden reglas institucionales.

## Defectos reproducidos y corregidos

| Código | Antes | Cambio y resultado |
|---|---|---|
| SEC-01 | La redirección de sesión revocada y respuestas de error carecían de cabeceras de protección. | Se centraliza su aplicación al responder y al renderizar excepciones; las sesiones revocadas siguen cerrándose y las operaciones prohibidas siguen denegadas. |
| SEC-02 | Identificadores no válidos podían provocar 500 por TypeError. | Binding de identificadores enteros positivos representables por PHP/PostgreSQL; valores inválidos o recursos inexistentes devuelven 404. No se modifica autorización ni se restringe una categoría de postulantes. |
| OBS-SEC-01 | CSP no declaraba explícitamente bloqueo de objetos. | Se añadió `object-src 'none'`. La observación anterior era endurecimiento; no se declara una explotación previa. |
| A11Y-03 | Los paneles tenían año y texto de métricas con contraste insuficiente. | Se usa el color secundario ya corregido, conservando contenido y tamaños. |
| A11Y-04 | Enlaces dentro de texto se distinguían solo por color. | Se subrayan los enlaces de párrafos y listas del contenido. |

Ocho pruebas nuevas reprodujeron los fallos antes del cambio: 8 fallos, 0 errores de la batería. Los casos cubren sesión revocada, IDs inválidos y desbordados, CSP normal, validación 422, acceso 403, invitado redirigido, sesión inactiva y 404. Tras corregir, pasan las ocho; además pasa la suite completa: **118 pruebas, 487 aserciones**, incluidas las 110 identidades anteriores. Las reproducciones fallidas se conservan separadas del XML final aprobado.

## Verificación ejecutada ahora

- PHPUnit completo en PostgreSQL aislado: 118/118 y 487 aserciones; cero fallos o errores.
- Recorrido HTTP nuevo: 21 comprobaciones y 104 solicitudes, todas con estado esperado.
- Navegador: 29 combinaciones perfil/ruta con los `details` abiertos, capturas a 1280×900 y 320×900. Se detectaron 15 ocurrencias nuevas en el muestreo ampliado; tras corregir, cero avisos axe en esas 29 combinaciones y cero desbordamientos globales a 320 píxeles.
- Catorce sondeos adicionales verificaron expedientes ajenos, archivos privados, borradores, reportes y administración según perfil, incluyendo las cabeceras de respuestas denegadas. Los 14 pasan. Esto no equivale a probar exhaustivamente todas las rutas y métodos.
- Consulta actual de avisos de Composer desde lock: sin avisos ni paquetes abandonados reportados. Sintaxis de los cuatro PHP afectados y Pint: PASS.
- Código de dominio, modelos, controladores, migraciones, formularios Blade y pruebas preexistentes: hashes preservados contra el ZIP anterior. Se modifican middleware, integración de excepciones, binding de rutas, CSS y documentación; se agregan ocho pruebas.

Las 29 combinaciones incluyen registro, login, paneles, administración con desplegables, edición de convocatoria/oferta, publicación, resultados, auditoría, colas y formularios de rectificación, expediente observado y subsanación abierta. Los datos se prepararon mediante servicios de dominio en una base del recorrido sintético. Son fixtures de prueba, no reglas ni decisiones institucionales.

| Perfil | Ruta | Avisos axe finales | Ancho de documento con viewport 320 |
|---|---|---|---|
| invitado | `/login` | 0 | 320 |
| invitado | `/registro` | 0 | 320 |
| admin | `/` | 0 | 320 |
| admin | `/usuarios` | 0 | 320 |
| autoridad | `/` | 0 | 320 |
| autoridad | `/convocatorias/1` | 0 | 320 |
| autoridad | `/convocatorias/2?editar_oferta=3` | 0 | 320 |
| autoridad | `/convocatorias/3` | 0 | 320 |
| autoridad | `/ofertas/1/resultados` | 0 | 320 |
| autoridad | `/convocatorias/1/auditoria` | 0 | 320 |
| autoridad | `/rectificaciones` | 0 | 320 |
| autoridad | `/postulaciones/1/rectificaciones` | 0 | 320 |
| autoridad | `/postulaciones/5/procesos` | 0 | 320 |
| revisor | `/` | 0 | 320 |
| revisor | `/convocatorias/1` | 0 | 320 |
| revisor | `/postulaciones/5` | 0 | 320 |
| revisor | `/postulaciones/5/procesos` | 0 | 320 |
| revisor | `/postulaciones/1/rectificaciones` | 0 | 320 |
| revisor | `/rectificaciones` | 0 | 320 |
| evaluador | `/` | 0 | 320 |
| evaluador | `/convocatorias/1` | 0 | 320 |
| evaluador | `/postulaciones/1` | 0 | 320 |
| evaluador | `/postulaciones/1/rectificaciones` | 0 | 320 |
| evaluador | `/rectificaciones` | 0 | 320 |
| postulante | `/` | 0 | 320 |
| postulante | `/convocatorias/3` | 0 | 320 |
| postulante | `/postulaciones/1` | 0 | 320 |
| postulante | `/postulaciones/5/procesos` | 0 | 320 |
| postulante | `/adjudicaciones` | 0 | 320 |

## Evidencia y reproducción

Carpeta `../evidence/seguridad-extendida-03/`: `security-before.xml` conserva el fallo previo; `regresion/phpunit.xml` contiene las 118 pruebas nuevas ejecutadas; `security-final.xml` repite los ocho casos tras el formato final. `http/recorrido-http.json` registra las solicitudes del nuevo recorrido. `navegador-antes.json` y `pantallas-antes/` conservan la ampliación previa a la corrección de estilos; `navegador-ampliado.json` y `pantallas/` contienen el resultado final. Los scripts de navegador y fixtures están incluidos como referencia de ensayo y requieren la sesión privada generada por el recorrido; no se distribuyen credenciales.

Se reutilizaron dependencias y cluster privados de la validación aislada previa, puerto 55442; la DEMO original permaneció detenida. No se reinstaló Composer ni se repitió esta vez el simulacro de restauración o las once carreras, porque el dominio y sus controles transaccionales no cambiaron. Las evidencias anteriores permanecen intactas y no se presentan como nuevas.

Usar `python scripts/verify_security_release.py <ZIP> --output <recibo.json>` para comprobar el paquete actual. `verify_accessibility_release.py` corresponde al ZIP histórico de cierre de accesibilidad; no debe usarse para exigir que esta entrega conserve el middleware anterior que justamente se corrigió.

## Límites

Las pruebas automatizadas no certifican WCAG integral, todas las combinaciones de estado ni tareas completas con lector de pantalla. Falta una evaluación de accesibilidad asistiva y un pentest externo, además de carga y operación sobre infraestructura institucional. No se modificaron configuraciones de producción, TLS, cuentas reales ni servicios originales. El servidor y cluster de ensayo se detuvieron al cerrar esta etapa.

Siguen pendientes la confirmación institucional de umbral aprobatorio, rúbricas, composición del examen, plazos y autoridades, así como autorización de uso. La consulta institucional continúa sin enviar. Estas correcciones técnicas no sustituyen esas decisiones.
