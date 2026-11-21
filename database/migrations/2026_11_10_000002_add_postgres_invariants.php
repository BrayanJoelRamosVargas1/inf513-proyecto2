<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // La DEMO objetivo usa PostgreSQL. Las restricciones se suman a la validación de dominio.
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('ALTER TABLE offers ADD CONSTRAINT offers_seats_nonnegative CHECK (seats >= 0)');
        DB::statement('ALTER TABLE calls ADD CONSTRAINT calls_limit_positive CHECK (subject_limit IS NULL OR subject_limit > 0)');
        DB::statement('ALTER TABLE applications ADD CONSTRAINT applications_merit_range CHECK (merit BETWEEN 0 AND 40), ADD CONSTRAINT applications_exam_range CHECK (exam BETWEEN 0 AND 60), ADD CONSTRAINT applications_final_range CHECK (final BETWEEN 0 AND 100)');
        DB::statement('ALTER TABLE grades ADD CONSTRAINT grades_score_range CHECK (score BETWEEN 0 AND 100)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('ALTER TABLE grades DROP CONSTRAINT grades_score_range');
        DB::statement('ALTER TABLE applications DROP CONSTRAINT applications_merit_range, DROP CONSTRAINT applications_exam_range, DROP CONSTRAINT applications_final_range');
        DB::statement('ALTER TABLE calls DROP CONSTRAINT calls_limit_positive');
        DB::statement('ALTER TABLE offers DROP CONSTRAINT offers_seats_nonnegative');
    }
};
