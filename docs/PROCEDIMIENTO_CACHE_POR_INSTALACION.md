# Configuración y caché de cada instalación

1. Confirmar la carpeta de destino y respaldar su configuración privada. No copiar `.env`, claves, conexiones ni cachés de otra instalación.
2. Verificar base, rol limitado, URL, experiencia y almacenamiento autorizados. El modo institucional requiere otra base y almacenamiento; no se cambia la etiqueta sobre la DEMO.
3. En esa misma instalación y con sus variables propias, ejecutar `php artisan config:cache`, `php artisan route:cache` y `php artisan view:cache`. Estos comandos no migran ni recrean la base.
4. Si se separan cachés mediante `APP_CONFIG_CACHE` y `APP_ROUTES_CACHE` en Windows, utilizar rutas relativas a la aplicación, como se comprobó en este ensayo. No reutilizar rutas compiladas de otro entorno.
5. Comprobar de forma saneada base/rol actuales, directorios de almacenamiento, sesiones, vistas, URL y modo. No volcar contraseñas, claves completas ni toda la configuración a un informe público.
6. Verificar acceso, documentos y cuarentena; conservar el bloqueo oficial. Una configuración nueva no convierte datos sintéticos en institucionales ni acredita homologación.

La causa del 503 anterior fue una caché compilada antes de existir la opción nueva del portal. Se refrescó con los valores propios de la DEMO. En esta aceptación se comprobaron además cachés diferentes para entrenamiento e institucional; solo se restringieron privilegios de las bases nuevas del laboratorio.
