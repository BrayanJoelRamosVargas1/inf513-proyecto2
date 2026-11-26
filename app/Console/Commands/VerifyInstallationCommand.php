<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Revisa que una instalación real esté lista antes de abrirla a postulantes. Solo lee; no modifica nada. */
final class VerifyInstallationCommand extends Command
{
    protected $signature = 'ficct:verificar-instalacion';

    protected $description = 'Comprueba la configuración de producción (entorno, base, antivirus, cuentas, tareas programadas)';

    private int $errors = 0;

    private int $warnings = 0;

    public function handle(): int
    {
        $this->check('Entorno de producción (APP_ENV=production)', app()->environment('production'), true);
        $this->check('Depuración desactivada (APP_DEBUG=false)', ! config('app.debug'), true);
        $this->check('Clave de aplicación definida (APP_KEY)', filled(config('app.key')), true);
        $this->check('Dirección en HTTPS (APP_URL)', str_starts_with((string) config('app.url'), 'https://'), true);
        $this->check('Instalación institucional (FICCT_EXPERIENCE=institutional)', config('portal.experience') === 'institutional', true);
        $this->check('Cookies de sesión seguras (SESSION_SECURE_COOKIE=true)', (bool) config('session.secure'), true);
        $this->check('Sesiones fuera de memoria (SESSION_DRIVER distinto de array)', config('session.driver') !== 'array', true);

        try {
            DB::select('select 1');
            $database = true;
        } catch (\Throwable) {
            $database = false;
        }
        $this->check('Conexión a PostgreSQL', $database, true);
        if ($database) {
            $this->check('Motor PostgreSQL (no SQLite)', DB::getDriverName() === 'pgsql', true);
            $applied = DB::table('migrations')->pluck('migration')->all();
            $files = array_map(fn ($f) => basename($f, '.php'), glob(database_path('migrations/*.php')) ?: []);
            $this->check('Migraciones al día', ! array_diff($files, $applied), true);
            $this->check('Existe una cuenta de administración activa', DB::table('users')->where('role', 'admin')->where('active', true)->exists(), true);
            $this->check('Sin datos de entrenamiento (convocatorias DEMO ni correos .test)', ! DB::table('calls')->where('mode', 'DEMO')->exists() && ! DB::table('users')->where('email', 'ilike', '%.test')->exists(), true);
            $this->check('Catálogo institucional cargado (asignaturas y laboratorios)', DB::table('offers')->exists(), false);
            $this->check('Sin correos de ejemplo (.test, .invalid) entre las cuentas', ! DB::table('users')->where(fn ($q) => $q->where('email', 'ilike', '%.test')->orWhere('email', 'ilike', '%.invalid'))->exists(), true);
            $this->check('Hay una autoridad de convocatoria registrada', DB::table('users')->where('role', 'authority')->where('active', true)->exists(), false);
        }
        $binary = (string) config('document_safety.binary');
        $this->check('ClamAV configurado y ejecutable (FICCT_CLAMSCAN_BINARY)', $binary !== '' && is_file($binary), true);
        $signatures = (string) config('document_safety.database');
        $this->check('Firmas de ClamAV disponibles (FICCT_CLAMSCAN_DATABASE)', $signatures !== '' && is_dir($signatures), true);
        $this->check('Nota mínima de aprobación propuesta (FICCT_NOTA_MINIMA)', config('portal.approval_threshold_default') !== null, false);
        $this->check('Límite de subida de PHP (upload_max_filesize) de al menos 16 MB', $this->bytes((string) ini_get('upload_max_filesize')) >= 16 * 1048576, false);
        $this->check('Límite de envío de PHP (post_max_size) de al menos 20 MB', $this->bytes((string) ini_get('post_max_size')) >= 20 * 1048576, false);
        foreach (['bcmath', 'gd', 'zip', 'intl', 'mbstring', 'pdo_pgsql'] as $extension) {
            $this->check("Extensión PHP {$extension}", extension_loaded($extension), true);
        }
        $this->check('Almacenamiento escribible', is_writable(storage_path('app')) && is_writable(storage_path('logs')), true);
        $this->check('Caché de configuración generada (php artisan config:cache)', app()->configurationIsCached(), false);
        $this->check('Compilación del frontend presente (npm run build)', is_file(public_path('build/manifest.json')), true);

        $this->newLine();
        $this->line($this->errors === 0 ? "Instalación lista. Advertencias: {$this->warnings}." : "Hay {$this->errors} error(es) por corregir y {$this->warnings} advertencia(s).");
        $this->line('Recuerde: el planificador (schedule:run cada minuto) debe estar activo; sin él caducan los análisis de archivos.');

        return $this->errors === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function bytes(string $value): int
    {
        $value = trim($value);
        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1073741824,
            'm' => $number * 1048576,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function check(string $label, bool $ok, bool $required): void
    {
        if ($ok) {
            $this->line("  [OK]          {$label}");

            return;
        }
        if ($required) {
            $this->errors++;
            $this->line("  <fg=red>[ERROR]       {$label}</>");
        } else {
            $this->warnings++;
            $this->line("  <fg=yellow>[ADVERTENCIA] {$label}</>");
        }
    }
}
