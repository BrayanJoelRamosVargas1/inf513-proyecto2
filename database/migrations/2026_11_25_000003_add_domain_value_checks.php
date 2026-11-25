<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Auditoría B-21: restricciones de dominio para estados y roles, que hasta ahora solo cuidaba el código.
 *
 * Se crean con NOT VALID: se aplican a toda fila nueva o modificada sin recorrer las existentes, de modo que la
 * migración no puede fallar sobre una base con datos históricos. Cada restricción se puede validar después con
 * ALTER TABLE ... VALIDATE CONSTRAINT cuando se confirme que los datos actuales cumplen.
 */
return new class extends Migration
{
    private const CHECKS = [
        'calls_status_values' => ['calls', 'status', ['draft', 'published', 'closed', 'evaluation', 'results', 'archived']],
        'calls_mode_values' => ['calls', 'mode', ['DEMO', 'HISTORICAL', 'OFICIAL']],
        'applications_status_values' => ['applications', 'status', ['draft', 'submitted']],
        'applications_eligibility_values' => ['applications', 'eligibility', ['pending', 'enabled', 'disabled']],
        'applications_selection_values' => ['applications', 'selection', ['pending', 'selected', 'not_selected', 'under_review', 'withdrawn', 'resigned']],
        'users_role_values' => ['users', 'role', ['student', 'staff', 'authority', 'admin']],
        'memberships_role_values' => ['memberships', 'role', ['reviewer', 'evaluator', 'authority']],
    ];

    public function up(): void
    {
        foreach (self::CHECKS as $name => [$table, $column, $values]) {
            if (DB::table('pg_constraint')->where('conname', $name)->exists()) {
                continue;
            }
            $list = implode(', ', array_map(fn ($v) => "'".$v."'", $values));
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$column} IN ({$list})) NOT VALID");
        }
    }

    public function down(): void
    {
        foreach (self::CHECKS as $name => [$table]) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name}");
        }
    }
};
