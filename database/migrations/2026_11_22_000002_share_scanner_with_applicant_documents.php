<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['institutional_scan_jobs', 'institutional_scan_tasks', 'institutional_scan_events'] as $table) {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN source_id DROP NOT NULL");
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('document_id')->nullable()->constrained('documents');
            });
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_one_target CHECK ((source_id IS NULL) <> (document_id IS NULL))");
        }
        DB::statement("CREATE UNIQUE INDEX document_scan_one_active ON institutional_scan_tasks(document_id) WHERE state IN ('queued','leased')");
        Schema::table('institutional_scan_jobs', function (Blueprint $t) {
            $t->index(['document_id', 'id']);
        });
        // Preserve original bytes, document versions, and historical scan evidence.
        DB::unprepared('CREATE TRIGGER applicant_documents_immutable BEFORE UPDATE OR DELETE ON documents FOR EACH ROW EXECUTE FUNCTION institutional_record_immutable()');
    }

    public function down(): void
    {
        // History with applicant targets cannot be discarded by a rollback.
        foreach (['institutional_scan_jobs', 'institutional_scan_tasks', 'institutional_scan_events'] as $table) {
            if (DB::table($table)->whereNotNull('document_id')->exists()) {
                throw new RuntimeException('Applicant scan history exists; use a verified forward migration.');
            }
        }
        DB::statement('DROP TRIGGER applicant_documents_immutable ON documents');
        DB::statement('DROP INDEX document_scan_one_active');
        foreach (['institutional_scan_events', 'institutional_scan_tasks', 'institutional_scan_jobs'] as $table) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_one_target");
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('document_id');
            });
            DB::statement("ALTER TABLE {$table} ALTER COLUMN source_id SET NOT NULL");
        }
    }
};
