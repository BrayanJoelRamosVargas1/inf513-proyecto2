<?php

namespace App\Domain;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class Selection
{
    public static function conditionalCriterion(object $offer, string $key): bool
    {
        // Art. 7(e) del reglamento de docencia y Art. 6 del de laboratorio: la titulación en mora solo concierne a quien ya terminó el plan de estudios.
        return in_array($offer->requirement_profile ?? null, ['asignatura', 'sala', 'laboratorio'], true) && $key === 'graduacion_mora';
    }

    public static function criterionSatisfied(object $offer, string $key, ?string $decision): bool
    {
        return $decision === 'meets' || ($decision === 'not_applicable' && self::conditionalCriterion($offer, $key));
    }

    public static function row(string $table, int $id, bool $lock = false): object
    {
        $q = DB::table($table)->where('id', $id);
        if ($lock) {
            $q->lockForUpdate();
        }

        return $q->first() ?? abort(404);
    }

    public static function json(mixed $value): array
    {
        return is_array($value) ? $value : json_decode($value ?? '[]', true, 512, JSON_THROW_ON_ERROR);
    }

    public static function lockedApplication(int $id): array
    {
        $application = self::row('applications', $id);
        $offer = self::row('offers', $application->offer_id);
        // Orden único de bloqueo: convocatoria -> oferta/postulación -> nota.
        // Serializa operaciones sensibles de la misma convocatoria para una DEMO de volumen facultativo.
        $call = self::row('calls', $offer->call_id, true);

        $current = self::row('applications', $id, true);
        Participation::active($current);

        return [$current, $offer, $call];
    }

    public static function audit(User $u, ?int $call, string $action, string $entity, mixed $before = null, mixed $after = null, ?string $reason = null): void
    {
        DB::table('audit_events')->insert([
            'user_id' => $u->id,
            'call_id' => $call,
            'action' => $action,
            'entity' => $entity,
            'before' => $before === null ? null : json_encode($before),
            'after' => $after === null ? null : json_encode($after),
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    /** Modo de las convocatorias con las que opera esta instalación: DEMO en entrenamiento, OFICIAL en la instalación institucional. */
    public static function operationalMode(): string
    {
        return config('portal.experience') === 'institutional' ? 'OFICIAL' : 'DEMO';
    }

    /** La convocatoria pertenece al modo operativo de la instalación (las históricas son solo de consulta). */
    public static function isOperable(object $call): bool
    {
        return $call->mode === self::operationalMode();
    }

    /** Qué respaldos institucionales se registraron para la convocatoria: comisión evaluadora y actos (aprobación del HCF o del Decano). */
    public static function institutionalEvidence(int $callId): array
    {
        $has = fn (string $kind) => DB::table('institutional_register')->where('call_id', $callId)->where('kind', $kind)->exists();

        return ['commission_registered' => $has('commission'), 'act_registered' => $has('act')];
    }

    /** Existe un acto institucional registrado de alguno de esos tipos (por ejemplo la aprobación del HCF o su resolución). */
    public static function hasAct(int $callId, array $types): bool
    {
        return DB::table('institutional_register')->where('call_id', $callId)->where('kind', 'act')
            ->whereIn(DB::raw("payload->>'act_type'"), $types)->exists();
    }

    /** Leyenda de los reportes según el modo de la convocatoria. */
    public static function reportNotice(object $call): string
    {
        return match ($call->mode) {
            'HISTORICAL' => 'Referencia histórica · Reporte interno, sin aprobación institucional; no es una resolución oficial.',
            'OFICIAL' => 'Reporte del sistema de selección de auxiliares. Los resultados rigen cuando la autoridad competente los aprueba por resolución; este documento no la reemplaza.',
            default => 'DEMO · Datos sintéticos · Sin validez institucional ni nombramiento.',
        };
    }

    public static function reportPrefix(object $call, string $base): string
    {
        return match ($call->mode) {
            'HISTORICAL' => $base.'-referencia-',
            'OFICIAL' => $base.'-',
            default => $base.'-demo-',
        };
    }

    public static function assertOperable(object $call): void
    {
        if (! self::isOperable($call)) {
            Scores::fail('Esta convocatoria es de otro modo (histórica o de otra instalación) y solo admite consulta.');
        }
    }

    public static function receiving(object $call): void
    {
        self::assertOperable($call);
        if (! Workspace::receiving($call)) {
            Scores::fail('La recepción no está abierta. El instante exacto de cierre es inclusivo.');
        }
    }

    public function createCall(User $u, array $d): int
    {
        return app(CallLifecycle::class)->createCall($u, $d);
    }

    public function editCall(User $u, int $callId, array $d): void
    {
        app(CallLifecycle::class)->editCall($u, $callId, $d);
    }

    public function saveOffer(User $u, int $callId, array $d, ?int $offerId = null): int
    {
        return app(CallLifecycle::class)->saveOffer($u, $callId, $d, $offerId);
    }

    public function transition(User $u, int $callId, string $next): void
    {
        app(CallLifecycle::class)->transition($u, $callId, $next);
    }

    public function apply(User $u, int $offerId): int
    {
        return app(ApplicationIntake::class)->apply($u, $offerId);
    }

    public function upload(User $u, int $appId, string $kind, UploadedFile $file): int
    {
        return app(ApplicationIntake::class)->upload($u, $appId, $kind, $file);
    }

    public function submit(User $u, int $id): void
    {
        app(ApplicationIntake::class)->submit($u, $id);
    }

    public function review(User $u, int $id, array $decisions, string $reason): void
    {
        app(EligibilityReview::class)->review($u, $id, $decisions, $reason);
    }

    public function eligibility(User $u, int $id, bool $enable, string $reason): void
    {
        app(EligibilityReview::class)->eligibility($u, $id, $enable, $reason);
    }

    public function merit(User $u, int $id, string $score, string $reference): void
    {
        app(EligibilityReview::class)->merit($u, $id, $score, $reference);
    }

    public function assign(User $u, int $offerId, int $teacher, string $area): void
    {
        app(Evaluation::class)->assign($u, $offerId, $teacher, $area);
    }

    public function grade(User $u, int $appId, int $assignmentId, string $score, bool $confirmed = true): void
    {
        app(Evaluation::class)->grade($u, $appId, $assignmentId, $score, $confirmed);
    }

    public function correct(User $u, int $gradeId, string $score, string $reason): void
    {
        app(Evaluation::class)->correct($u, $gradeId, $score, $reason);
    }

    public function calculate(User $u, int $id): void
    {
        app(Evaluation::class)->calculate($u, $id);
    }

    public function ranking(int $offerId): array
    {
        return app(ResultsPublication::class)->ranking($offerId);
    }

    public function openTie(User $u, int $offerId, array $d): int
    {
        return app(ResultsPublication::class)->openTie($u, $offerId, $d);
    }

    public function resolveTie(User $u, int $id, array $results, array $winners, string $minutes): void
    {
        app(ResultsPublication::class)->resolveTie($u, $id, $results, $winners, $minutes);
    }

    public function continueTie(User $u, int $id, array $results, array $d): int
    {
        return app(ResultsPublication::class)->continueTie($u, $id, $results, $d);
    }

    public function approve(User $u, int $offerId, string $minutes): void
    {
        app(ResultsPublication::class)->approve($u, $offerId, $minutes);
    }
}
