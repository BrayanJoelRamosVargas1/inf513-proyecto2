<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutional_dossiers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('call_id')->constrained('calls');
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('reviewer_id')->constrained('users');
            $t->string('title', 150);
            $t->string('status', 25)->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        Schema::create('institutional_sources', function (Blueprint $t) {
            $t->id();
            $t->foreignId('dossier_id')->constrained('institutional_dossiers');
            $t->foreignId('created_by')->constrained('users');
            $t->string('reference', 100);
            $t->unsignedInteger('version');
            $t->string('gate', 3);
            $t->string('title', 180);
            $t->date('issued_on');
            $t->string('pages', 80);
            $t->string('classification', 30);
            $t->text('notes');
            $t->string('path');
            $t->string('sha256', 64);
            $t->string('mime', 50);
            $t->unsignedBigInteger('bytes');
            $t->timestampTz('created_at');
            $t->unique(['dossier_id', 'reference', 'version']);
        });
        Schema::create('institutional_submissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('dossier_id')->constrained('institutional_dossiers');
            $t->foreignId('created_by')->constrained('users');
            $t->unsignedInteger('dossier_version');
            $t->jsonb('snapshot');
            $t->string('sha256', 64);
            $t->timestampTz('created_at');
            $t->unique(['dossier_id', 'dossier_version']);
        });
        Schema::create('institutional_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('submission_id')->constrained('institutional_submissions');
            $t->foreignId('created_by')->constrained('users');
            $t->string('decision', 20);
            $t->text('reason');
            $t->timestampTz('created_at');
            $t->unique('submission_id');
        });
        DB::unprepared("CREATE OR REPLACE FUNCTION institutional_record_immutable() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN RAISE EXCEPTION 'Institutional preparation evidence is append-only'; END; \$\$");
        foreach (['institutional_sources', 'institutional_submissions', 'institutional_reviews'] as $table) {
            DB::unprepared("CREATE TRIGGER {$table}_immutable BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION institutional_record_immutable()");
        }
        $id = DB::table('navigation_items')->insertGetId(['label' => 'Preparación institucional', 'path' => '/institucional', 'position' => 12]);
        DB::table('navigation_roles')->insert(['navigation_item_id' => $id, 'role' => 'authority']);
    }

    public function down(): void
    {
        DB::table('navigation_items')->where('path', '/institucional')->delete();
        foreach (['institutional_reviews', 'institutional_submissions', 'institutional_sources', 'institutional_dossiers'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::unprepared('DROP FUNCTION institutional_record_immutable()');
    }
};
