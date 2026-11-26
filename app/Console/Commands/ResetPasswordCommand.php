<?php

namespace App\Console\Commands;

use App\Domain\Selection;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Recuperación de acceso sin correo saliente: la administración de sistemas entrega la clave nueva en persona. */
final class ResetPasswordCommand extends Command
{
    protected $signature = 'ficct:restablecer-clave {email : Correo de la cuenta}';

    protected $description = 'Genera una clave nueva para una cuenta y cierra sus sesiones abiertas';

    public function handle(): int
    {
        $user = User::where('email', mb_strtolower(trim((string) $this->argument('email'))))->first();
        if (! $user) {
            $this->error('No existe una cuenta con ese correo.');

            return self::FAILURE;
        }
        $password = Str::password(20, symbols: false);
        DB::transaction(function () use ($user, $password) {
            $user->forceFill(['password' => $password, 'must_change_password' => true, 'security_version' => $user->security_version + 1])->save();
            Selection::audit($user, null, 'user.password_reset', 'user:'.$user->id, null, ['origin' => 'consola']);
        });
        $this->info("Clave restablecida para {$user->email}. Se cerraron sus sesiones.");
        $this->line("Clave nueva (se muestra una sola vez): {$password}");

        return self::SUCCESS;
    }
}
