<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutional_scan_tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('source_id')->constrained('institutional_sources');
            $t->foreignId('job_id')->nullable()->constrained('institutional_scan_jobs');
            $t->string('state', 16)->default('queued');
            $t->unsignedInteger('attempts')->default(0);
            $t->unsignedInteger('max_attempts');
            $t->timestampTz('available_at');
            $t->uuid('lease_token')->nullable();
            $t->timestampTz('lease_until')->nullable();
            $t->timestampsTz();
            $t->index(['state', 'available_at']);
        });
        DB::unprepared("CREATE UNIQUE INDEX institutional_scan_one_active ON institutional_scan_tasks(source_id) WHERE state IN ('queued','leased')");
        Schema::create('institutional_scan_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_id')->nullable()->constrained('institutional_scan_tasks');
            $t->foreignId('source_id')->constrained('institutional_sources');
            $t->foreignId('job_id')->nullable()->constrained('institutional_scan_jobs');
            $t->string('action', 50);
            $t->unsignedInteger('attempt')->default(0);
            $t->jsonb('details');
            $t->timestampTz('created_at');
        });
        DB::unprepared('CREATE TRIGGER institutional_scan_events_immutable BEFORE UPDATE OR DELETE ON institutional_scan_events FOR EACH ROW EXECUTE FUNCTION institutional_record_immutable()');
        // Existing sources and evidence are not rewritten or released by this migration.
    }

    public function down(): void
    {
        Schema::dropIfExists('institutional_scan_events');
        Schema::dropIfExists('institutional_scan_tasks');
    }
};
