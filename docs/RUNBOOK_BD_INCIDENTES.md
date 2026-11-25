# Respaldo, restauración e incidentes · Proyecto 2

Actualización local de auditoría — 24/11/2026: las cifras de 41 tablas y nueve triggers de este documento describen el respaldo docente histórico. La aplicación local actual tiene 50 tablas y 16 triggers según sus evidencias de restauración. Comprobar siempre el inventario de la instalación que se está respaldando; no usar las cifras históricas como criterio de aceptación de una actualización. Esta auditoría no ha validado ni actualizado el servidor remoto.

## Aislamiento y preparación

La instalación docente usa el esquema exclusivo ficct_p2 de la base asignada al grupo. Desde el servidor PHP, la conexión PostgreSQL es local; la conexión administrativa externa tiene una dirección distinta. Consultar la configuración privada sin publicarla. Nunca usar migrate:fresh, DROP SCHEMA ni restauraciones sobre otros proyectos.

Antes de actualizar: anotar revisión de Git y composer.lock, respaldar el esquema completo con pg_dump en formato custom, copiar storage/app/private y guardar la configuración y claves en un destino privado. Proteger permisos y cifrado del respaldo. Una copia de base sin documentos ni clave no es un respaldo completo.

## Restauración de ensayo

1. Crear una base aislada nueva; no sobrescribir la operativa.
2. Restaurar con pg_restore --no-owner --no-privileges, conservando el esquema del respaldo.
3. Configurar DB_SEARCH_PATH para ese esquema y archivos privados de la copia.
4. Comparar conteos y huellas de todas las tablas, secuencias, definiciones y estado de los triggers y archivos contra el inventario del respaldo y las migraciones de esa versión. No limitar la comparación a los nueve triggers históricos. Probar sesión, expediente, publicación histórica y descargas.
5. Registrar fecha, resultado y límites. No abrir la copia restaurada al público con credenciales reales.

La restauración local realizada conservó 41 tablas y nueve triggers. Las evidencias especifican qué instalación se restauró; no atribuir una prueba local al servidor remoto.

## Respuestas a incidentes

| Síntoma | Comprobar | Acción |
|---|---|---|
| Página en blanco o recursos 404 | manifest, base de Vite, APP_URL y prefijo | Restaurar recursos y manifest de la misma compilación |
| Sesión o CSRF 419 | HTTPS, cookie, SESSION_PATH y token | Renovar sesión; conservar la protección CSRF |
| PostgreSQL no conecta | Host desde PHP, puerto, esquema y permisos | Restaurar configuración validada; no migrar otros esquemas |
| Resultado incorrecto | Datos, responsables y auditoría | Usar rectificación y aprobación; conservar publicación histórica |
| Archivo no descarga | Propiedad, autorización, almacenamiento privado | Recuperar el archivo correcto y sus metadatos; no sustituirlo por una imagen |
| Decimal/BCMath | composer.lock, vendor y autoload | Instalar dependencias bloqueadas con la compatibilidad incluida |
| Fallos tras actualizar | Registro privado y revisión desplegada | Reponer código/recursos anteriores compatibles; valorar restauración con responsables |

APP_DEBUG debe permanecer false. No copiar mensajes SQL, secretos ni expedientes a reportes públicos. Los ayudantes temporales de despliegue se autentican y se retiran al terminar. Las credenciales de FTP, PostgreSQL y cuentas DEMO están excluidas del código y de la entrega.

ficct:telemetry-prune informa por defecto; --apply afecta solo eventos antiguos de acceso según la política DEMO, no el historial académico. Aprobar institucionalmente retención, recuperación y responsables antes de un concurso real.
