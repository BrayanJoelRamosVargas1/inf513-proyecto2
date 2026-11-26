<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Identidad única de la persona y comisión evaluadora ligada a cuentas.
 *
 * - users.document_number (CI) y users.student_code (registro universitario): únicos cuando existen, para que una persona no
 *   abra varias cuentas y esquive el límite de asignaturas. Son opcionales en la base; la instalación real los exige al registrarse.
 * - commission_members: integrantes de la comisión (Arts. 14 y 13 de la Resolución 021/2023) y veedores acreditados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'document_number')) {
                $table->string('document_number', 20)->nullable();
            }
            if (! Schema::hasColumn('users', 'student_code')) {
                $table->string('student_code', 20)->nullable();
            }
        });
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS users_document_number_unique ON users (upper(document_number)) WHERE document_number IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS users_student_code_unique ON users (upper(student_code)) WHERE student_code IS NOT NULL');

        if (! Schema::hasTable('commission_members')) {
            Schema::create('commission_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('call_id')->constrained('calls');
                $table->foreignId('offer_id')->nullable()->constrained('offers');
                $table->foreignId('user_id')->nullable()->constrained('users');
                $table->string('name', 180);
                $table->string('cargo', 40);
                $table->string('designation_reference', 100);
                $table->date('accredited_on')->nullable();
                $table->timestampTz('revoked_at')->nullable();
                $table->foreignId('revoked_by')->nullable()->constrained('users');
                $table->foreignId('created_by')->constrained('users');
                $table->timestampsTz();
                $table->index(['call_id', 'offer_id']);
            });
            DB::statement("ALTER TABLE commission_members ADD CONSTRAINT commission_members_cargo_values CHECK (cargo IN ('director_carrera','vicedecano','decano','docente_titular','docente_invitado','encargado_laboratorio','veedor_federacion','veedor_vicerrectorado'))");
            DB::statement("ALTER TABLE commission_members ADD CONSTRAINT commission_members_veedor_accreditation CHECK (cargo NOT LIKE 'veedor%' OR accredited_on IS NOT NULL)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_members');
        DB::statement('DROP INDEX IF EXISTS users_document_number_unique');
        DB::statement('DROP INDEX IF EXISTS users_student_code_unique');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['document_number', 'student_code']));
    }
};
