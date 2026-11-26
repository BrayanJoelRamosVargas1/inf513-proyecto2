<?php

namespace App\Domain;

use Illuminate\Support\Facades\DB;

/** Bitácora técnica del procesador de análisis; nunca autoriza acceso a documentos. */
final class ScanEvents
{
    public static function record(?object $task, ?int $source, ?int $job, string $action, array $details = [], ?int $document = null): void
    {
        DB::table('institutional_scan_events')->insert([
            'task_id' => $task?->id, 'source_id' => $source, 'document_id' => $task?->document_id ?? $document, 'job_id' => $job,
            'action' => $action, 'attempt' => $task?->attempts ?? 0,
            'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }
}
