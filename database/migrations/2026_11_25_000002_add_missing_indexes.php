<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Auditoría B-10: índices para las consultas más frecuentes (permisos, bandejas, auditoría y recuperación del procesador).
 * Idempotente (IF NOT EXISTS): puede aplicarse sobre una base que ya tenga alguno.
 */
return new class extends Migration
{
    private const INDEXES = [
        'applications_user_id_index' => 'applications (user_id)',
        'memberships_user_id_index' => 'memberships (user_id)',
        'assignments_user_id_index' => 'assignments (user_id)',
        'audit_events_call_id_id_index' => 'audit_events (call_id, id)',
        'tie_exams_offer_id_index' => 'tie_exams (offer_id)',
        'correction_requests_application_id_index' => 'correction_requests (application_id)',
        'score_versions_application_id_index' => 'score_versions (application_id)',
        'documents_amendment_id_index' => 'documents (amendment_id)',
        'access_events_user_id_index' => 'access_events (user_id)',
        'institutional_scan_events_job_id_index' => 'institutional_scan_events (job_id)',
        'participation_requests_application_id_index' => 'participation_requests (application_id)',
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $name => $target) {
            DB::statement("CREATE INDEX IF NOT EXISTS {$name} ON {$target}");
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::INDEXES) as $name) {
            DB::statement("DROP INDEX IF EXISTS {$name}");
        }
    }
};
