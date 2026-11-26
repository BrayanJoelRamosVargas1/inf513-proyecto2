# Despliegue de la instalación real

Guía para poner el sistema en un servidor de la facultad. Para practicar con datos de ejemplo use el modo entrenamiento del README, en otra base y otro servidor: **nunca mezcle ambos**.

## Requisitos del servidor

PHP 8.3 (BCMath, PDO PostgreSQL, mbstring, gd, zip, dom, fileinfo, intl, openssl), Composer 2, PostgreSQL 15, Node 20 o superior (solo para compilar), un servidor web con HTTPS (Nginx o Apache) y ClamAV con sus firmas actualizadas.

## Puesta en marcha

```bash
git clone <repositorio> sistema && cd sistema
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.production.example .env        # completar los valores (ver abajo)
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force            # solo catálogos y fuentes históricas públicas; no crea cuentas
php artisan ficct:crear-administrador administracion@su-dominio --nombre="Administración FICCT"
php artisan config:cache && php artisan route:cache
php artisan ficct:verificar-instalacion
```

La clave inicial del administrador se muestra una sola vez en pantalla: anótela y cámbiela al ingresar (menú «Apariencia y accesibilidad» → «Cambiar clave»).

### Valores obligatorios de `.env`

| Variable | Valor |
|---|---|
| `APP_ENV`, `APP_DEBUG` | `production`, `false` |
| `APP_URL` | dirección pública con `https://` |
| `FICCT_EXPERIENCE` | `institutional` |
| `DB_*` | base PostgreSQL propia de esta instalación (nunca la de entrenamiento) |
| `SESSION_SECURE_COOKIE`, `SESSION_DRIVER` | `true`, `database` |
| `TRUSTED_PROXIES` | IP del proxy inverso, si lo hay |
| `FICCT_CLAMSCAN_BINARY`, `FICCT_CLAMSCAN_DATABASE` | rutas absolutas de `clamscan` y de las firmas |
| `FICCT_NOTA_MINIMA` | `51` (nota mínima propuesta en cada oferta; la facultad la puede cambiar) |

El servidor web debe apuntar a `public/` y reenviar todo lo que no sea un archivo a `index.php`.

## Planificador (obligatorio)

El análisis antivirus de cada documento caduca a las 24 h; sin planificador los expedientes enviados dejan de ser válidos.

```
* * * * * cd /ruta/sistema && php artisan schedule:run >> /dev/null 2>&1      # Linux
```

En Windows, una tarea del Programador de tareas cada minuto con `php artisan schedule:run`. Tareas programadas: renovación y cola de análisis, mantenimiento y limpieza diaria de la telemetría de navegación.

## Primeros pasos con el sistema abierto

1. La administración crea las cuentas de la autoridad, de las personas revisoras y de las evaluadoras (Administración → Usuarios). Los postulantes se registran solos. Cada cuenta creada por la administración (o por consola) obliga a cambiar la clave inicial en el primer ingreso, de modo que nadie más pueda actuar a nombre de esa persona. La autoridad y quienes revisan o evalúan en una misma convocatoria deben ser cuentas distintas (separación de funciones).
2. La autoridad crea la convocatoria (borrador), define ofertas, plazas, pesos del examen y nota mínima, asigna revisores y evaluadores y publica.
3. Los resultados se aprueban oferta por oferta con el acta; los reportes PDF/XLSX salen de la convocatoria.

Los roles y lo que cada uno puede hacer están en `DECISIONES_ADOPTADAS_20261126.md`.

## Operación

- **Verificación:** `php artisan ficct:verificar-instalacion` antes de abrir y después de cada actualización. Devuelve error si falta algo obligatorio.
- **Olvido de clave:** `php artisan ficct:restablecer-clave correo@…` (la administración entrega la clave nueva en persona).
- **Respaldo:** `pg_dump` en formato custom y copia de `storage/app/private`; ver `RUNBOOK_BD_INCIDENTES.md`. Una copia de la base sin los documentos no es un respaldo completo.
- **Actualización:** respaldar, `git pull`, `composer install --no-dev`, `npm ci && npm run build`, `php artisan migrate --force`, `config:cache`, `route:cache`, verificar.
- **Registros:** `storage/logs` (rotación diaria con `LOG_STACK=daily`). No publicar mensajes de error ni contenidos de expedientes.

## Qué no hace el sistema

No emite el nombramiento ni los certificados, no envía correos, no integra el CPD ni el sistema académico, y no cruza horarios de clases. Esos actos los realiza la autoridad con los reportes y el acta que el sistema produce.
