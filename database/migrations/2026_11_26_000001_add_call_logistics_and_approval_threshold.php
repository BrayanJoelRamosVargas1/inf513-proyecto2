<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resolución Vicerrectoral 021/2023, Art. 8 (contenido de la convocatoria) y Arts. 19-20 (puntaje de aprobación).
 *
 * Todo es opcional y queda vacío: el sistema no inventa valores que la institución no ha confirmado.
 * - Convocatoria: período académico, lugar de recepción y lugar del examen.
 * - Oferta: horas teóricas y prácticas y nota mínima final de aprobación. Con la nota vacía no se excluye a nadie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            if (! Schema::hasColumn('calls', 'academic_period')) {
                $table->string('academic_period', 60)->nullable();
            }
            if (! Schema::hasColumn('calls', 'reception_place')) {
                $table->string('reception_place', 200)->nullable();
            }
            if (! Schema::hasColumn('calls', 'exam_place')) {
                $table->string('exam_place', 200)->nullable();
            }
        });
        Schema::table('offers', function (Blueprint $table) {
            if (! Schema::hasColumn('offers', 'theory_hours')) {
                $table->unsignedSmallInteger('theory_hours')->nullable();
            }
            if (! Schema::hasColumn('offers', 'practice_hours')) {
                $table->unsignedSmallInteger('practice_hours')->nullable();
            }
            if (! Schema::hasColumn('offers', 'approval_threshold')) {
                $table->decimal('approval_threshold', 5, 2)->nullable();
            }
        });
        if (! DB::table('pg_constraint')->where('conname', 'offers_approval_threshold_range')->exists()) {
            DB::statement('ALTER TABLE offers ADD CONSTRAINT offers_approval_threshold_range CHECK (approval_threshold IS NULL OR (approval_threshold >= 0 AND approval_threshold <= 100))');
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE offers DROP CONSTRAINT IF EXISTS offers_approval_threshold_range');
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['theory_hours', 'practice_hours', 'approval_threshold']);
        });
        Schema::table('calls', function (Blueprint $table) {
            $table->dropColumn(['academic_period', 'reception_place', 'exam_place']);
        });
    }
};
