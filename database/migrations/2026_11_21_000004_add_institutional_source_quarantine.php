<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutional_scan_jobs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('source_id')->constrained('institutional_sources');
            $t->string('sha256', 64);
            $t->string('policy_hash', 64);
            $t->timestampTz('created_at');
            $t->index(['source_id', 'id']);
        });
        Schema::create('institutional_scan_results', function (Blueprint $t) {
            $t->id();
            $t->foreignId('job_id')->unique()->constrained('institutional_scan_jobs');
            $t->string('state', 12);
            $t->string('engine', 200);
            $t->string('reason', 60);
            $t->timestampTz('finished_at');
        });
        foreach (['institutional_scan_jobs', 'institutional_scan_results'] as $table) {
            DB::unprepared("CREATE TRIGGER {$table}_immutable BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION institutional_record_immutable()");
        }
        // No clean rows are backfilled. Existing sources are quarantined until scanned.
    }

    public function down(): void
    {
        Schema::dropIfExists('institutional_scan_results');
        Schema::dropIfExists('institutional_scan_jobs');
    }
};
