<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El correo no distingue mayúsculas: «Juan@x.com» y «juan@x.com» son la misma cuenta. Se pasan a minúsculas los existentes
 * y se impide repetir un correo por cambio de mayúsculas. Si ya hubiera correos repetidos por mayúsculas, la migración
 * se detiene sin tocar nada para que la administración los resuelva a mano.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('users')->selectRaw('lower(email) as e, count(*) as n')->groupByRaw('lower(email)')->havingRaw('count(*) > 1')->pluck('e');
        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('Hay correos repetidos por mayúsculas: '.$duplicates->implode(', ').'. Resuélvalos antes de migrar.');
        }
        DB::statement('UPDATE users SET email = lower(email) WHERE email <> lower(email)');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS users_email_lower_unique ON users (lower(email))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_email_lower_unique');
    }
};
