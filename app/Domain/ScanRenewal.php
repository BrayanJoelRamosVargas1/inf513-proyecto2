<?php

namespace App\Domain;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Renueva el análisis antimalware de los documentos vigentes de postulaciones activas.
 *
 * Un análisis «clean» caduca a las `document_safety.valid_hours` y toda decisión académica
 * exige un análisis vigente (auditoría B-01). Esta clase solo ENCOLA nuevas solicitudes de
 * análisis; nunca libera, aprueba ni marca un documento como limpio. Los documentos
 * bloqueados o con error no se reencolan: requieren decisión humana.
 */
final class ScanRenewal
{
    /** Convocatorias en las que aún pueden tomarse decisiones sobre el expediente. */
    private const CALL_STATUSES = ['published', 'closed', 'evaluation', 'results'];

    /**
     * @return array{apply:bool,margin_hours:int,inspected:int,renewed:int,by_reason:array<string,int>,skipped:array<string,int>,tasks:list<int>}
     */
    public function run(bool $apply, int $marginHours = 6): array
    {
        $marginHours = max(0, min(23, $marginHours));
        $validHours = (int) config('document_safety.valid_hours');
        $safety = app(DocumentSafety::class);
        $applicantDocuments = app(ApplicantDocuments::class);
        $report = ['apply' => $apply, 'margin_hours' => $marginHours, 'inspected' => 0, 'renewed' => 0, 'by_reason' => ['expired' => 0, 'expiring' => 0], 'skipped' => [], 'tasks' => []];

        $applications = DB::table('applications')
            ->join('offers', 'offers.id', '=', 'applications.offer_id')
            ->join('calls', 'calls.id', '=', 'offers.call_id')
            ->where('calls.mode', 'DEMO')->whereIn('calls.status', self::CALL_STATUSES)
            ->where('applications.status', 'submitted')->where('applications.participation', 'active')
            ->orderBy('applications.id')->pluck('applications.id');

        foreach ($applications as $applicationId) {
            foreach ($applicantDocuments->effective($applicationId) as $document) {
                $report['inspected']++;
                $status = $safety->status($document->id, 'applicant');
                $reason = match (true) {
                    $status['state'] === 'expired' => 'expired',
                    $status['state'] === 'clean' && CarbonImmutable::parse($status['finished_at'])->addHours($validHours - $marginHours)->lte(now()) => 'expiring',
                    default => null,
                };
                if ($reason === null) {
                    $report['skipped'][$status['state']] = ($report['skipped'][$status['state']] ?? 0) + 1;

                    continue;
                }
                $report['by_reason'][$reason]++;
                $report['renewed']++;
                if ($apply) {
                    $report['tasks'][] = app(DocumentProcessor::class)->enqueue($document->id, 'applicant');
                }
            }
        }

        return $report;
    }
}
