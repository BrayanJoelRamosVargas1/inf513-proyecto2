<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_control_lock', function (Blueprint $t) {
            $t->integer('id')->primary();
        });
        DB::table('access_control_lock')->insert(['id' => 1]);
        Schema::table('users', function (Blueprint $t) {
            $t->integer('security_version')->default(0);
            $t->integer('profile_version')->default(1);
        });
        Schema::table('memberships', function (Blueprint $t) {
            $t->timestampTz('starts_at')->nullable();
            $t->timestampTz('expires_at')->nullable();
            $t->timestampTz('revoked_at')->nullable();
            $t->foreignId('revoked_by')->nullable()->constrained('users');
            $t->text('reason')->nullable();
            $t->integer('version')->default(1);
        });
        Schema::table('assignments', fn (Blueprint $t) => $t->integer('version')->default(1));
        Schema::table('grades', fn (Blueprint $t) => $t->integer('version')->default(1));
        Schema::table('applications', fn (Blueprint $t) => $t->integer('merit_version')->default(1));
        Schema::table('offers', function (Blueprint $t) {
            $t->integer('result_revision')->default(0);
            $t->boolean('needs_publication_review')->default(false);
        });
        Schema::table('publications', function (Blueprint $t) {
            $t->integer('result_revision')->default(0);
            $t->foreignId('previous_publication_id')->nullable()->constrained('publications');
        });
        Schema::table('tie_exams', function (Blueprint $t) {
            $t->integer('result_revision')->default(0);
            $t->integer('round')->default(1);
            $t->foreignId('previous_tie_id')->nullable()->constrained('tie_exams');
        });
        Schema::create('correction_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->constrained();
            $t->foreignId('grade_id')->nullable()->constrained();
            $t->string('kind');
            $t->decimal('old_value', 5, 2);
            $t->decimal('new_value', 5, 2);
            $t->integer('base_version');
            $t->foreignId('requester_id')->constrained('users');
            $t->foreignId('original_evaluator_id')->nullable()->constrained('users');
            $t->text('reason');
            $t->text('evidence_reference');
            $t->string('status')->default('pending');
            $t->foreignId('decided_by')->nullable()->constrained('users');
            $t->text('decision_reason')->nullable();
            $t->timestampTz('decided_at')->nullable();
            $t->timestampsTz();
        });
        Schema::create('score_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->constrained();
            $t->foreignId('grade_id')->nullable()->constrained();
            $t->string('key');
            $t->integer('version');
            $t->decimal('score', 5, 2);
            $t->foreignId('recorded_by')->nullable()->constrained('users');
            $t->foreignId('correction_request_id')->nullable()->constrained();
            $t->timestampTz('created_at');
            $t->unique(['key', 'version']);
        });
        foreach (DB::table('grades')->where('status', '!=', 'draft')->get() as $g) {
            DB::table('score_versions')->insert(['application_id' => $g->application_id, 'grade_id' => $g->id, 'key' => 'grade:'.$g->id, 'version' => 1, 'score' => $g->score, 'recorded_by' => $g->user_id, 'created_at' => now()]);
        }
        foreach (DB::table('applications')->whereNotNull('merit')->get() as $a) {
            DB::table('score_versions')->insert(['application_id' => $a->id, 'key' => 'merit:'.$a->id, 'version' => 1, 'score' => $a->merit, 'created_at' => now()]);
        }
        Schema::create('result_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('offer_id')->constrained();
            $t->integer('revision');
            $t->foreignId('correction_request_id')->constrained();
            $t->json('before');
            $t->json('after');
            $t->json('changes');
            $t->timestampTz('created_at');
            $t->unique(['offer_id', 'revision']);
        });
        DB::statement('ALTER TABLE memberships ADD CONSTRAINT membership_dates CHECK (expires_at IS NULL OR starts_at IS NULL OR expires_at > starts_at)');
        DB::unprepared("CREATE OR REPLACE FUNCTION ficct_immutable() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Registro histórico inmutable'; END $$");
        foreach (['publications', 'score_versions', 'result_revisions', 'audit_events'] as $table) {
            DB::unprepared("CREATE TRIGGER immutable_record BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION ficct_immutable()");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Migración de conservación: restaure un respaldo verificado; no revierta destruyendo historia.');
    }
};
