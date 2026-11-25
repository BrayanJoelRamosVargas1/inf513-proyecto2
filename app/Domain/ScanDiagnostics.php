<?php

namespace App\Domain;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ScanDiagnostics
{
    /** Read-only operational diagnosis; never authorizes document access. */
    public function queueStatus(): array
    {
        $now = now()->toImmutable();
        $ready = DB::table('institutional_scan_tasks')->where('state', 'queued')->where('available_at', '<=', $now);
        $oldest = (clone $ready)->min('available_at');
        $age = $oldest === null ? 0 : max(0, $now->getTimestamp() - CarbonImmutable::parse($oldest)->getTimestamp());
        $expired = DB::table('institutional_scan_tasks')->where('state', 'leased')->where('lease_until', '<=', $now)->count();
        $failed = DB::table('institutional_scan_tasks as t')->where('state', 'failed')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('institutional_scan_jobs as j')
                ->whereRaw('(j.source_id = t.source_id OR j.document_id = t.document_id)')->whereColumn('j.id', '>', 't.job_id'))->count();
        $unmanaged = DB::table('institutional_scan_jobs as j')
            ->where('j.created_at', '<=', $now->subSeconds(config('document_processor.orphan_seconds')))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('institutional_scan_jobs as n')
                ->whereRaw('(n.source_id = j.source_id OR n.document_id = j.document_id)')->whereColumn('n.id', '>', 'j.id'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('institutional_scan_results as r')->whereColumn('r.job_id', 'j.id'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('institutional_scan_events as e')->whereColumn('e.job_id', 'j.id'))->count();
        $threshold = max(1, (int) config('document_processor.backlog_seconds'));
        $warnings = [];
        if ($oldest !== null && $age >= $threshold) {
            $warnings[] = 'QUEUE_OVERDUE';
        }
        if ($expired > 0) {
            $warnings[] = 'LEASES_EXPIRED';
        }
        if ($failed > 0) {
            $warnings[] = 'CURRENT_TASKS_FAILED';
        }
        if ($unmanaged > 0) {
            $warnings[] = 'UNMANAGED_CURRENT_PENDING';
        }

        return [
            'observed_at' => $now->toIso8601String(),
            'tasks' => DB::table('institutional_scan_tasks')->selectRaw('state, count(*) as total')->groupBy('state')->orderBy('state')->get(),
            'ready' => (clone $ready)->count(),
            'delayed' => DB::table('institutional_scan_tasks')->where('state', 'queued')->where('available_at', '>', $now)->count(),
            'expired_leases' => $expired, 'oldest_ready_seconds' => $age,
            'failed_current' => $failed, 'unmanaged_current' => $unmanaged,
            'backlog_threshold_seconds' => $threshold,
            'health' => $warnings === [] ? 'ok' : 'attention', 'warnings' => $warnings,
        ];
    }
}
