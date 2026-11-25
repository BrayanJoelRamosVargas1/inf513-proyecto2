# Aceptación independiente del tarball INF513

Se prueba el mismo código entregado, sin sustituir la aplicación de trabajo. El
directorio extraído es desechable y sirve exclusivamente para aceptar la instalación.
No utiliza la configuración, los documentos ni las dependencias instaladas de la DEMO.

## Herramientas necesarias

PHP compatible con `composer.lock` y sus extensiones, Composer, Node/npm y las
herramientas de PostgreSQL (`initdb`, `pg_ctl`, `psql`, `createdb`, `pg_dump`,
`pg_restore`). El procedimiento comprueba los requisitos reales de Composer.
Descarga o reutiliza la caché de los gestores; no copia `vendor` ni `node_modules`.
La disponibilidad de esas herramientas no equivale a una instalación de sistema
operativo desde cero. En Linux, `initdb` requiere una cuenta sin privilegios de root.

## Ejecución

Desde `sistema`, con las rutas de herramientas y destino del equipo:

```text
python scripts/verify_clean_install.py --archive ../2026-2_INF513-P2_grupo09sa.tar.gz --work-root .local/release-install-acceptance --pg-bin "C:/Program Files/PostgreSQL/15/bin" --report evidence/clean-install-current/installation.json
```

El conductor valida CRC, manifiesto completo y contrato canónico antes de extraer.
Rechaza enlaces, rutas de escape, duplicados incluso por mayúsculas y archivos
privados. Crea una carpeta nueva con identificador aleatorio y un clúster PostgreSQL
nuevo, enlazado únicamente a loopback y un puerto libre. La aplicación utiliza un
rol propietario sin superusuario ni permiso para crear bases o roles; PHPUnit
utiliza `ficct_test` dentro de ese mismo clúster aislado.

Se ejecutan instalación de dependencias, comprobación de plataforma, pruebas React,
compilación, migraciones, datos sintéticos, cachés, PHPUnit, respaldo/restauración
con comparación del contenido de cada tabla y acceso HTTP con cinco perfiles.
Las cuentas y contraseñas son aleatorias y quedan únicamente en la carpeta privada.

El analizador antivirus se deja **sin configurar** en esta aceptación de instalación.
No se fabrica un resultado limpio para completar el recorrido. Las regresiones de
seguridad de PHPUnit no constituyen aceptación de un motor real. La prueba real de
ClamAV y los recorridos completos anteriores conservan su alcance propio; en el
servidor de destino hay que comprobar nuevamente motor, firmas, cola y permisos.

La salida pública identifica el SHA-256 exacto del tarball instalado. Al terminar se
detienen el servidor de prueba y el clúster; se conservan los archivos de ensayo y
sus registros para inspección. No se registran servicios de Windows ni se activa
inicio automático. Una interrupción externa puede impedir el cierre: antes de
repetir, comprobar únicamente los procesos y directorios identificados de ese ensayo.
No borrar clústeres ni detener PostgreSQL de la DEMO por coincidencia de nombre.

## Alcance y cierre

El resultado de una instalación pertenece al hash de su paquete, no a todos los
tarballs futuros. Si se cambia el archivo de entrega, debe conservarse la evidencia
anterior y aceptar el nuevo hash. La matriz de requisitos académicos y el plan de
respaldo/reversión remota están en `CIERRE_INF513_ACTUAL.md`.

La aceptación local no autoriza publicación, entrega en plataforma ni concursos
oficiales. Se conserva `NO_GO_INSTITUCIONAL`.
