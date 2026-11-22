<?php

namespace App\Domain;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Document safety only; academic decisions remain with authorized reviewers. */
final class ApplicantDocuments
{
    /** Computed technical readiness, never an academic eligibility transition. */
    public function readiness(int $application): array
    {
        try {
            DB::transaction(fn () => $this->assertEffective($application));

            return ['state' => 'current', 'ready' => true];
        } catch (HttpException $e) {
            if (! in_array($e->getStatusCode(), [409, 422])) {
                throw $e;
            }

            // Only aggregate status is exposed; no file metadata or review detail.
            return ['state' => 'pending', 'ready' => false];
        }
    }

    public function assertOffer(int $offer): void
    {
        $ids = DB::table('applications')->where('offer_id', $offer)->where('status', 'submitted')->where('participation', 'active')->where('eligibility', 'enabled')->orderBy('id')->pluck('id');
        foreach ($ids as $id) {
            $this->assertEffective($id);
        }
    }

    public function status(int $id): array
    {
        return collect(app(DocumentSafety::class)->status($id, 'applicant'))->only(['state', 'download_allowed', 'finished_at'])->all();
    }

    public function effective(int $application): array
    {
        $snapshot = DB::table('dossier_snapshots')->where('application_id', $application)->orderByDesc('id')->first();
        $docs = DB::table('documents')->where('application_id', $application);
        if ($snapshot) {
            $docs->whereIn('id', array_column(Selection::json($snapshot->snapshot)['documents'], 'id'));
        } else {
            $docs->whereNull('amendment_id');
        }

        return $docs->orderByDesc('version')->get()->unique('kind')->values()->all();
    }

    public function assertDecisions(int $application, array $decisions): array
    {
        $kinds = [];
        foreach ($decisions as $key => $decision) {
            if (str_starts_with($key, 'doc:') && $decision === 'meets') {
                $kinds[] = substr($key, 4);
            }
        }
        $docs = collect($this->effective($application))->whereIn('kind', $kinds);
        abort_unless(count($kinds) === $docs->count(), 422, 'Falta un documento de la versión vigente.');

        return $this->assertClean($docs->pluck('id')->all());
    }

    public function assertEffective(int $application): array
    {
        $app = Selection::row('applications', $application);
        $offer = Selection::row('offers', $app->offer_id);
        $docs = $this->effective($application);
        abort_unless(! array_diff(array_keys(Selection::json($offer->documents)), array_column($docs, 'kind')), 422, 'Falta documentación de la versión vigente.');

        return $this->assertClean(array_column($docs, 'id'));
    }

    public function assertClean(array $ids): array
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Document verification requires a decision transaction.');
        }
        $safety = app(DocumentSafety::class);
        $checks = [];
        sort($ids, SORT_NUMERIC);
        foreach (array_unique($ids) as $id) {
            $doc = (new DocumentTarget($id, 'applicant'))->row(true);
            $before = $safety->status($id, 'applicant');
            abort_unless($before['download_allowed'], 422, 'Documento en cuarentena: falta un análisis válido antes de aprobarlo.');
            $bytes = $safety->bytes($doc);
            $after = $safety->status($id, 'applicant');
            abort_unless($after['download_allowed'] && $before['job_id'] === $after['job_id'], 422, 'El análisis cambió durante la revisión.');
            $checks[] = ['document_id' => $id, 'version' => $doc->version, 'sha256' => hash('sha256', $bytes), 'scan_job_id' => $after['job_id'], 'checked_at' => now()->toIso8601String()];
        }

        return $checks;
    }
}
