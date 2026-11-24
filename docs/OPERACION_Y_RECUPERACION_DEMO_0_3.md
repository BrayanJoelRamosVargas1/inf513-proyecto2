# DEMO 0.3 — operación y recuperación local

Fecha: 12 de noviembre de 2026 (Bolivia). Estado: DEMO técnica local, sin autorización institucional.

## Resultado ejecutado

- Instalación nueva desde el ZIP de seguridad extendida: 385 archivos; dependencias instaladas desde Composer lock, sin copiar vendor. Nueva base con datos sintéticos.
- PHPUnit ejecutado: **119 pruebas y 489 aserciones**, sin errores ni fallos. Segunda ejecución con configuración y rutas de la aplicación ya cacheadas; las cachés activas permanecieron idénticas. Se conservan los identificadores de las pruebas anteriores.
- Repetidos 11 escenarios de concurrencia con procesos independientes. Registros separados en esta entrega.
- Carga autenticada: **300 consultas, cinco sesiones concurrentes, cero errores HTTP**. Duración 159.514 s; mediana 2549 ms; percentil 95 3518 ms; máximo 3915 ms. Las 30 tablas distintas de auditoría permanecieron iguales. Las lecturas y accesos pueden generar auditoría.
- Recuperación: respaldo y restauración en otra base, almacenamiento y cookie; comparación idéntica de 31 tablas antes de abrir la aplicación, nueve triggers y rechazo efectivo de modificación histórica. Clave de aplicación recuperada del respaldo privado.
- Aplicación restaurada: cinco accesos reales, 15 consultas de perfiles y 22 descargas privadas verificadas por SHA-256. La auditoría confirma que utilizó la base recuperada, sin escribir en la base de origen. Servidor de recuperación detenido al terminar.
- Cinco perfiles comprobados en el servidor principal con capturas. Identificadores inválidos y desbordados devuelven 404 con cabeceras de seguridad también con rutas cacheadas.

## Corrección concreta

El registro de validación del parámetro id estaba en routes/web.php. Laravel omite ese archivo al cargar rutas cacheadas y se perdía la validación. Se trasladó el mismo registro a AppServiceProvider::boot. S09 reproduce el fallo en un proceso nuevo con rutas cacheadas: falla antes y pasa después. No se cambiaron reglas de selección ni permisos.

Los ejecutores de pruebas ahora usan cachés privadas distintas de las de la aplicación activa y mantienen ficct_test como base de pruebas. revalidate_03 permite identificar la base DEMO a preservar en su configuración local, manteniendo el valor anterior por defecto. No se borran las cachés activas para probar.

## DEMO disponible

Dirección local: http://127.0.0.1:8116. Solo accesible desde esta computadora. Administrador, autoridad, revisor, evaluador y postulante. Las credenciales están en un archivo privado local y no se incluyen en el ZIP.

Casos sintéticos preparados: expediente observado con ventana de subsanación; renuncia aprobada con vacante retenida sin reemplazante; varias adjudicaciones y decisión DEMO. Se incluyen también convocatoria abierta y recorrido de evaluación/publicación. Las decisiones de demostración no equivalen a aceptación institucional.

La instalación activa está separada de sistema y de su base original. El iniciador local ABRIR_DEMO_FICCT_VALIDADA.ps1 permite reabrirla después de reiniciar esta computadora. El paquete portable contiene código e instrucciones, sin dependencias ni bases locales.

## Conservación y límites

Verificados nuevamente 9.037 archivos del clúster original y 18 archivos privados originales, los ocho ZIP previos y 248 evidencias históricas de la entrega anterior. Dominio, controladores, modelos, migraciones, vistas, ocho CU y treinta RF conservados por hash. El manifiesto de esta entrega cubre su propio contenido.

Los registros históricos conservan sus cifras originales de cada fase; el verificador vigente para este paquete es scripts/verify_operation_release.py. Los verificadores de entregas anteriores siguen describiendo aquellas entregas.

Esta es una ejecución en la misma computadora con PHP de desarrollo sobre Windows. Cinco clientes y 300 consultas no certifican capacidad institucional, un SLA ni seguridad completa. El simulacro utiliza otra base y almacenamiento en el mismo equipo; no acredita recuperación tras pérdida física ni en infraestructura distinta. Las comprobaciones son locales y no una auditoría externa. Las capturas de esta fase comprueban acceso y presentación; la auditoría de accesibilidad de 29 pantallas pertenece a la fase previa, conservada sin cambios de vistas ni estilos en esta fase.

Siguen pendientes la definición institucional del puntaje aprobatorio, aclaración de la rúbrica de méritos, decisiones sobre autoridades/plazos y aceptación, confirmación de vigencia normativa e infraestructura de producción. No se automatizó ningún umbral supuesto ni se inició DEMO 0.4. No se envió la consulta institucional.

## Evidencias

evidence/operacion-03 contiene instalación, XML de las dos ejecuciones, S09 antes/después, concurrencia, muestra de carga con cada solicitud, recuperación y conservación. Los respaldos, claves, cookies y contraseñas permanecen fuera del paquete.

La ejecución de carga anterior al corte de sesión tenía un registro completo, pero la recuperación no había terminado: se repitió íntegramente el recorrido en esta fecha. Un intento inicial del comprobador falló por codificación de una consulta antes de comenzar la carga; se corrigió la consulta del comprobador. Ninguno de esos intentos se presenta como validación completa.

La primera ejecución del nuevo aislamiento de cachés detectó que Laravel anteponía la carpeta del proyecto a una ruta de Windows con letra de unidad. Se corrigió el comprobador para usar rutas relativas al proyecto; el intento con error se conserva en regresion-cacheada-intento-windows y la ejecución definitiva se identifica como regresion-cacheada. La corrección no altera la configuración de la aplicación activa.
