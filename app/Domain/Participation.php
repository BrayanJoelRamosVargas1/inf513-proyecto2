<?php

namespace App\Domain;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Fachada de los flujos técnicos de participación. Plazos y compatibilidades institucionales no se infieren. */
final class Participation
{
    public static function active(object $a): void
    {
        if ($a->participation !== 'active') {
            Scores::fail('Postulación retirada o plaza renunciada: operación bloqueada.');
        }
    }

    public static function noAmendment(int $id): void
    {
        if (DB::table('amendments')->where('application_id', $id)->whereIn('status', ['open', 'submitted'])->exists()) {
            Scores::fail('Complete el circuito de subsanación y nueva revisión del expediente.');
        }
    }

    public static function legacyReview(int $id): void
    {
        if (DB::table('amendments')->where('application_id', $id)->exists()) {
            Scores::fail('Este expediente usa subsanaciones versionadas; complete su revisión desde Procesos.');
        }
    }

    public static function snapshot(int $id, int $version, string $phase, ?int $actor, ?array $documents = null): void
    {
        $a = Selection::row('applications', $id);
        $documents ??= DB::table('documents')->where('application_id', $id)->whereNull('amendment_id')->orderByDesc('version')->get()->unique('kind')->values()->map(fn ($d) => [
            'id' => $d->id,
            'kind' => $d->kind,
            'version' => $d->version,
            'sha256' => $d->sha256,
        ])->all();
        DB::table('dossier_snapshots')->insert([
            'application_id' => $id,
            'version' => $version,
            'phase' => $phase,
            'snapshot' => json_encode([
                'documents' => $documents,
                'reviews' => DB::table('reviews')->where('application_id', $id)->get()->all(),
                'eligibility' => $a->eligibility,
                'eligibility_reason' => $a->eligibility_reason,
            ]),
            'user_id' => $actor,
            'created_at' => now(),
        ]);
    }

    public static function award(object $a): ?object
    {
        $pub = DB::table('publications')->where('offer_id', $a->offer_id)->orderByDesc('version')->first();

        return $pub && $a->participation === 'active' && in_array($a->id, Selection::json($pub->snapshot)['selected']) ? $pub : null;
    }

    public function open(User $u, int $app, array $kinds, string $deadline, string $reason, string $evidence): int
    {
        return app(Amendments::class)->open($u, $app, $kinds, $deadline, $reason, $evidence);
    }

    public function upload(User $u, int $id, string $kind, UploadedFile $file): int
    {
        return app(Amendments::class)->upload($u, $id, $kind, $file);
    }

    public function submit(User $u, int $id): void
    {
        app(Amendments::class)->submit($u, $id);
    }

    public function review(User $u, int $id, array $decisions, string $reason): void
    {
        app(Amendments::class)->review($u, $id, $decisions, $reason);
    }

    public function expire(User $u, int $id, string $reason): void
    {
        app(Amendments::class)->expire($u, $id, $reason);
    }

    public function request(User $u, int $id, string $kind, string $reason, string $evidence): int
    {
        return app(ParticipationRequests::class)->request($u, $id, $kind, $reason, $evidence);
    }

    public function decide(User $u, int $id, bool $approve, string $reason): void
    {
        app(ParticipationRequests::class)->decide($u, $id, $approve, $reason);
    }

    public function choose(User $u, int $id, int $publication, string $choice, int $version, string $reason, string $evidence): void
    {
        app(AwardChoices::class)->choose($u, $id, $publication, $choice, $version, $reason, $evidence);
    }
}
