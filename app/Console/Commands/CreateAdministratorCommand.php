<?php

namespace App\Console\Commands;

use App\Domain\Selection;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/** Crea la cuenta de administración de una instalación nueva. No hay cuentas de ejemplo en una instalación real. */
final class CreateAdministratorCommand extends Command
{
    protected $signature = 'ficct:crear-administrador
        {email : Correo con el que ingresará la persona administradora}
        {--nombre= : Nombre completo}
        {--facultad=FICCT : Facultad}
        {--carrera=Administración : Carrera o unidad}';

    protected $description = 'Crea una cuenta de administración con una clave aleatoria que se muestra una sola vez';

    public function handle(): int
    {
        $data = [
            'email' => (string) $this->argument('email'),
            'name' => (string) ($this->option('nombre') ?: $this->ask('Nombre completo')),
        ];
        $validator = Validator::make($data, ['email' => 'required|email|max:150|unique:users', 'name' => 'required|string|max:100']);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }
        $password = Str::password(20, symbols: false);
        $user = DB::transaction(function () use ($data, $password) {
            $u = new User;
            $u->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $password,
                'role' => 'admin',
                'faculty' => (string) $this->option('facultad'),
                'career' => (string) $this->option('carrera'),
            ])->save();
            Selection::audit($u, null, 'user.created', 'user:'.$u->id, null, ['role' => 'admin', 'origin' => 'consola']);

            return $u;
        });
        $this->info("Administración creada: {$user->email}");
        $this->line("Clave inicial (se muestra una sola vez; cámbiela al ingresar): {$password}");

        return self::SUCCESS;
    }
}
