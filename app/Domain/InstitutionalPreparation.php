<?php

namespace App\Domain;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Technical preparation only. No state in this workflow authorizes a real competition.
 * Academic memberships are access controls, not proof of institutional accreditation.
 */
final class InstitutionalPreparation
{
    public const GATES = [
        'G01' => 'Autorización institucional escrita',
        'G02' => 'Normativa aplicable y vigencia',
        'G03' => 'Plazos y catálogos oficiales',
        'G04' => 'Identidad y elegibilidad oficial',
        'G05' => 'Fórmula, rúbrica, umbral y redondeo',
        'G06' => 'Comisión y aprobaciones formales',
        'G07' => 'Repetición de examen por observaciones',
        'G08' => 'Privacidad y retención',
        'G09' => 'Seguridad, carga y contingencia',
        'G10' => 'Infraestructura y responsables',
    ];

    public function dossier(User $user, int $id, bool $lock = false): object
    {
        $d = Selection::row('institutional_dossiers', $id, $lock);
        Access::require($user, $d->call_id, ['authority']);
        abort_unless(in_array($user->id, [$d->created_by, $d->reviewer_id], true), 403);

        return $d;
    }

    private function editable(User $user, int $id, int $version): object
    {
        $d = $this->dossier($user, $id, true);
        abort_unless($d->created_by === $user->id, 403);
        abort_if($d->version !== $version, 409, 'El expediente cambió. Recarga antes de continuar.');
        abort_unless($d->status === 'draft', 422, 'Solo se modifica la preparación en borrador.');

        return $d;
    }

    public function create(User $user, array $data): int
    {
        return Access::transaction($user, function () use ($user, $data) {
            Access::require($user, $data['call_id'], ['authority']);
            $reviewer = User::findOrFail($data['reviewer_id']);
            abort_if($user->id === $reviewer->id, 422, 'La revisión debe corresponder a otra autoridad del ámbito.');
            Access::require($reviewer, $data['call_id'], ['authority']);
            $id = DB::table('institutional_dossiers')->insertGetId($data + ['created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
            Selection::audit($user, $data['call_id'], 'institutional.draft.created', 'institutional:'.$id, null, $data);

            return $id;
        });
    }

    public function source(User $user, int $id, array $data, UploadedFile $file): int
    {
        $path = null;
        try {
            return Access::transaction($user, function () use ($user, $id, $data, $file, &$path) {
                $d = $this->editable($user, $id, $data['expected_version']);
                $version = 1 + (int) DB::table('institutional_sources')->where('dossier_id', $id)->where('reference', $data['reference'])->max('version');
                $sha = hash_file('sha256', $file->getRealPath());
                $path = $file->store('institutional-quarantine', 'local');
                abort_unless(is_string($path), 500, 'No se pudo conservar la fuente.');
                unset($data['expected_version']);
                $source = DB::table('institutional_sources')->insertGetId($data + ['dossier_id' => $id, 'created_by' => $user->id, 'version' => $version, 'path' => $path, 'sha256' => $sha, 'mime' => $file->getMimeType(), 'bytes' => $file->getSize(), 'created_at' => now()]);
                app(DocumentProcessor::class)->enqueue($source);
                DB::table('institutional_dossiers')->where('id', $id)->update(['version' => $d->version + 1, 'updated_at' => now()]);
                Selection::audit($user, $d->call_id, 'institutional.source.recorded', 'institutional-source:'.$source, null, [
                    'sha256' => $sha,
                    'version' => $version,
                    'verification' => 'UNVERIFIED',
                ]);

                return $source;
            });
        } catch (\Throwable $e) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }
    }

    public function readiness(int $id): array
    {
        $sources = DB::table('institutional_sources')->where('dossier_id', $id)->orderBy('id')->get([
            'id',
            'reference',
            'version',
            'gate',
            'title',
            'issued_on',
            'pages',
            'classification',
            'notes',
            'sha256',
            'mime',
            'bytes',
            'created_at',
        ]);
        foreach ($sources as $source) {
            $source->security = app(DocumentSafety::class)->status($source->id);
        }
        $latest = $sources->groupBy('reference')->map(fn ($versions) => $versions->last())->values();
        $gates = [];
        foreach (self::GATES as $key => $name) {
            $gates[] = [
                'id' => $key,
                'name' => $name,
                'state' => $latest->where('gate', $key)->isEmpty() ? 'MISSING' : 'DOCUMENTED_UNVERIFIED',
                'source_ids' => $latest->where('gate', $key)->pluck('id')->values(),
            ];
        }

        return [
            'official_activation_allowed' => false,
            'decision' => 'NO_GO_INSTITUCIONAL',
            'gates' => $gates,
            'sources' => $sources,
            'latest_source_ids' => $latest->pluck('id')->values(),
            'notice' => 'La carga y revisión técnica no prueban autenticidad, vigencia, acreditación ni aceptación institucional. No hay mecanismo de activación oficial en esta fase.',
        ];
    }

    public function submit(User $user, int $id, int $version): int
    {
        return Access::transaction($user, function () use ($user, $id, $version) {
            $d = $this->editable($user, $id, $version);
            $readiness = $this->readiness($id);
            abort_if($readiness['sources']->isEmpty(), 422, 'Adjunta al menos una fuente antes de solicitar revisión.');
            abort_if($readiness['sources']->contains(fn ($s) => ! $s->security['download_allowed']), 422, 'Las fuentes deben superar el análisis antimalware antes de solicitar revisión.');
            $checks = app(DocumentSafety::class)->assertCurrentSources($id);
            // Capture status after obtaining source locks, so the snapshot references
            // the same scan jobs that verified the bytes of this decision.
            $readiness = $this->readiness($id);
            // Store the exact string whose hash is returned, rather than relying on JSONB key ordering.
            $snapshot = json_encode([
                'dossier' => $d,
                'readiness' => $readiness,
                'integrity_checks' => $checks,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $submission = DB::table('institutional_submissions')->insertGetId([
                'dossier_id' => $id,
                'created_by' => $user->id,
                'dossier_version' => $version,
                'snapshot' => json_encode(['serialized' => $snapshot]),
                'sha256' => hash('sha256', $snapshot),
                'created_at' => now(),
            ]);
            DB::table('institutional_dossiers')->where('id', $id)->update([
                'status' => 'under_review',
                'version' => $version + 1,
                'updated_at' => now(),
            ]);
            Selection::audit($user, $d->call_id, 'institutional.review.requested', 'institutional:'.$id, null, ['submission_id' => $submission]);

            return $submission;
        });
    }

    public function review(User $user, int $id, array $data): void
    {
        Access::transaction($user, function () use ($user, $id, $data) {
            $d = $this->dossier($user, $id, true);
            abort_unless($d->reviewer_id === $user->id && $d->created_by !== $user->id, 403);
            abort_if($d->version !== $data['expected_version'], 409, 'El expediente cambió. Recarga antes de continuar.');
            abort_unless($d->status === 'under_review', 422, 'No hay una solicitud de revisión abierta.');
            // An observation can always be recorded, but completion requires current scans.
            $checks = [];
            if ($data['decision'] === 'reviewed') {
                $checks = app(DocumentSafety::class)->assertCurrentSources($id);
            }
            $submission = DB::table('institutional_submissions')->where('dossier_id', $id)->orderByDesc('id')->first();
            DB::table('institutional_reviews')->insert([
                'submission_id' => $submission->id,
                'created_by' => $user->id,
                'decision' => $data['decision'],
                'reason' => $data['reason'],
                'created_at' => now(),
            ]);
            DB::table('institutional_dossiers')->where('id', $id)->update([
                'status' => $data['decision'] === 'observed' ? 'draft' : 'technically_reviewed',
                'version' => $d->version + 1,
                'updated_at' => now(),
            ]);
            Selection::audit($user, $d->call_id, 'institutional.review.recorded', 'institutional:'.$id, null, $data + ['integrity_checks' => $checks], $data['reason']);
        });
    }
}
