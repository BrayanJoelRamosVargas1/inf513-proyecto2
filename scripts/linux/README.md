# Procesador documental en Linux — preparación de aceptación

Objetivo: una aplicación web accesible desde navegadores y un backend común desplegable en Linux o Windows. Este adaptador reutiliza `scripts/run_document_processor.py`, Laravel, PostgreSQL y `ClamAvScanner`; no replica reglas académicas. WIN-02 sigue pendiente y no es requisito para ensayar Linux. `NO_GO_INSTITUCIONAL` continúa vigente.

## Configuración y permisos

La unidad es una plantilla para un servidor con systemd, Python 3, PHP >=8.2 con las extensiones de Composer (incluida pdo_pgsql), PostgreSQL y ClamAV. No instala paquetes, usuarios, bases ni servicios. La configuración académica y del antivirus permanece en el `.env`/configuración de Laravel; no se importa el JSON privado del servicio Windows. No cargar `.env` como `EnvironmentFile`: los formatos tienen reglas distintas. Revisar cualquier caché Laravel al mover de entorno: una caché copiada puede contener rutas y credenciales antiguas.

Instalar una copia independiente en `/srv/ficct/sistema`; adaptar TODAS las rutas de la unidad si se usa otra ubicación. No reutilizar carpetas privadas, claves ni la base de la DEMO Windows. Generar APP_KEY propia y base/rol limitados de ensayo. Instalar dependencias mediante los lockfiles; no transportar ejecutables o node_modules de Windows.

Crear una cuenta sin inicio de sesión `ficct-worker` y un grupo privado `ficct-documents`. El código y la unidad deben pertenecer al administrador y no permitir escritura al trabajador. Precrear `storage`, `bootstrap/cache` y `.local/document-processor`; son las únicas excepciones de escritura de la unidad, además del temporal privado. Ajustar permisos NTFS no aplica en Linux: verificar propietario, grupos, ACL POSIX y SELinux/AppArmor de la distribución. No desactivar sus protecciones para resolver errores.

El procesador debe poder leer `.env`, dependencias y documentos que recibe, y escribir su estado y temporales. `UMask=0077` es conservador: los documentos nuevos creados por el servidor web pueden quedar inaccesibles si ambos usan usuarios distintos. Definir y probar una ACL de herencia limitada entre esos usuarios para el almacenamiento documental. No usar chmod 777 ni otorgar escritura al código o a la base de firmas. La escritura concedida por systemd no reemplaza los permisos del sistema de archivos.

En el `.env` privado usar `FICCT_CLAMSCAN_BINARY=/usr/bin/clamscan` y `FICCT_CLAMSCAN_DATABASE=/var/lib/clamav` (adaptar a la distribución). Firmas administradas por un actualizador separado, legibles pero no modificables por el trabajador. Verificar que estén vigentes. No copiar las firmas de ensayo a un servicio oficial. PHP se resuelve por el PATH explícito de la unidad; verificar versión bajo la cuenta real antes del inicio.

## Ensayo manual, solo en Linux aislado

1. Confirmar base, puerto, claves, carpetas, respaldo y documentos sintéticos. Comparar hash del contrato canónico y registrar su igualdad. Crear tarea sintética por el flujo de Laravel, sin insertar estados de aprobación a mano.
2. Revisar la unidad y `systemd-analyze verify scripts/linux/ficct-document-processor.service`. Validar lectura/escritura con la cuenta del trabajador y conexión al PostgreSQL de ensayo. Ejecutar `php artisan ficct:scan-queue-status --check` bajo esa cuenta; consulta la cola, no procesa documentos.
3. Copiar la unidad revisada a `/etc/systemd/system/`, ejecutar `systemctl daemon-reload` y `systemctl start ficct-document-processor.service`. Estos pasos requieren administración en el servidor aislado. No usar `enable`. Verificar `systemctl show` (User, Group, MainPID, ControlGroup, NRestarts) y procesos efectivos, junto con `journalctl -u ficct-document-processor.service` sin publicar secretos.
4. Comprobar consumo real del trabajo, ClamAV limpio/detección/fallo/firmas vencidas, originales intactos y resultados históricos. Las pruebas sintéticas de Python no sustituyen estas operaciones.
5. Probar `stop` y `start`: todos los hijos deben desaparecer al parar. Probar caída del supervisor y recuperación, interrupción de una reserva persistente y posterior recuperación conforme al dominio; verificar ausencia de dos consumidores del mismo trabajo. Registrar PID, usuario, reservas y resultados antes/después.
6. Respaldar/restaurar en una segunda base y comparar tablas, triggers y archivos. Repetir PHPUnit, integración HTTP, accesibilidad y carga desde el entorno destino. Monitorizar cola y heartbeat externamente; la existencia del proceso no demuestra salud. No hay alertas externas configuradas en esta plantilla.

La unidad usa `KillMode=control-group` para terminar su grupo de procesos y `Restart=always` para recuperar también salidas normales inesperadas; una parada explícita de systemd no se reinicia. Un marcador `linux-service.stop` persistente hará que el supervisor salga repetidamente hasta el límite de arranque: investigarlo, no borrarlo automáticamente. `PrivateTmp` separa los temporales del servicio y `ProtectHome` impide depender de ejecutables alojados en perfiles personales. Consultar la [documentación de systemd](https://www.freedesktop.org/software/systemd/man/latest/systemd.exec.html) y [terminación de procesos](https://www.freedesktop.org/software/systemd/man/latest/systemd.kill.html) para verificar compatibilidad con la versión destino.

No se habilita arranque automático ni se reinicia Windows/Linux con esta entrega. La aceptación del arranque tras reinicio requiere un ensayo posterior autorizado. Una VM/WSL con systemd permite algunos ensayos, pero no demuestra recuperación del host ni rendimiento del servidor definitivo. AWS y la autorización universitaria siguen siendo decisiones independientes.

## Evidencia y límites

`test_supervisor_posix.py` ejecuta el supervisor real en un directorio temporal con un sustituto sintético de PHP; verifica exclusión mutua y parada por SIGTERM. No necesita Laravel, PostgreSQL ni ClamAV. Su nombre y resultados deben conservar esta distinción. No modifica la aplicación ni servicios existentes.
