<?php

namespace App\Domain;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Comisión evaluadora y veedores (Resolución 021/2023, Art. 14 de docencia y Art. 13 de laboratorio).
 *
 * La designan el Director de Carrera, el Vicedecano o el Decano; aquí la registra la autoridad de la convocatoria con la
 * referencia del acto de designación. Solo quien integra la comisión puede ser asignado como evaluador. Los veedores se
 * acreditan 48 horas antes del examen, no tienen voto ni cuenta en el sistema.
 */
final class Commission
{
    public const CARGOS = [
        'director_carrera' => 'Director de Carrera',
        'vicedecano' => 'Vicedecano',
        'decano' => 'Decano',
        'docente_titular' => 'Docente titular de la materia',
        'docente_invitado' => 'Docente invitado',
        'encargado_laboratorio' => 'Encargado o Jefe del laboratorio o sala',
        'veedor_federacion' => 'Veedor de la Federación de Auxiliares',
        'veedor_vicerrectorado' => 'Veedor del Vicerrectorado',
    ];

    public const OBSERVERS = ['veedor_federacion', 'veedor_vicerrectorado'];

    /** Composición mínima por perfil: [descripción, cargos admitidos, cantidad mínima]. */
    public const COMPOSITION = [
        'asignatura' => [
            ['Director de Carrera', ['director_carrera'], 1],
            ['Docente titular de la materia', ['docente_titular'], 1],
            ['Docente invitado', ['docente_invitado'], 1],
        ],
        'sala' => [
            ['Encargado de la sala o laboratorio', ['encargado_laboratorio'], 1],
            ['Director de Carrera o Vicedecano', ['director_carrera', 'vicedecano'], 1],
            ['Docentes invitados (mínimo dos)', ['docente_invitado'], 2],
        ],
        'laboratorio' => [
            ['Director de Carrera', ['director_carrera'], 1],
            ['Jefe del laboratorio', ['encargado_laboratorio'], 1],
        ],
    ];

    public function add(User $u, int $callId, array $d): int
    {
        return Access::transaction($u, function () use ($u, $callId, $d) {
            $call = Selection::row('calls', $callId, true);
            Access::require($u, $callId, ['authority']);
            Selection::assertOperable($call);
            if (! in_array($call->status, ['draft', 'published', 'closed'])) {
                Scores::fail('La comisión se designa antes de abrir la evaluación.');
            }
            $cargo = (string) ($d['cargo'] ?? '');
            if (! array_key_exists($cargo, self::CARGOS)) {
                Scores::fail('Cargo de la comisión inválido.');
            }
            $reference = trim((string) ($d['designation_reference'] ?? ''));
            if (mb_strlen($reference) < 3 || mb_strlen($reference) > 100) {
                Scores::fail('Indique la referencia del acto que designa a la persona (por ejemplo, la resolución de designación).');
            }
            $offerId = isset($d['offer_id']) && $d['offer_id'] !== '' ? (int) $d['offer_id'] : null;
            if ($offerId !== null && Selection::row('offers', $offerId)->call_id !== $callId) {
                Scores::fail('La oferta no pertenece a esta convocatoria.');
            }
            $observer = in_array($cargo, self::OBSERVERS, true);
            $userId = null;
            $accredited = null;
            if ($observer) {
                $name = trim((string) ($d['name'] ?? ''));
                if ($name === '') {
                    Scores::fail('Indique el nombre de la persona acreditada como veedora.');
                }
                $accredited = CarbonImmutable::parse((string) ($d['accredited_on'] ?? ''), config('app.timezone'))->startOfDay();
                if ($accredited->gt(CarbonImmutable::parse($call->exam_at)->subHours(48))) {
                    Scores::fail('Los veedores se acreditan al menos 48 horas antes de la toma del examen.');
                }
            } else {
                $member = User::find((int) ($d['user_id'] ?? 0));
                if (! $member || ! $member->active) {
                    Scores::fail('Elija una cuenta activa para el integrante de la comisión.');
                }
                if ($member->role === 'admin') {
                    Scores::fail('La administración técnica no integra la comisión.');
                }
                if (Access::isApplicant($member, $callId)) {
                    Scores::fail('La persona postula en esta convocatoria: no puede integrar la comisión (conflicto de interés).');
                }
                $userId = $member->id;
                $name = $member->name;
            }
            $duplicate = DB::table('commission_members')->where('call_id', $callId)->where('cargo', $cargo)->whereNull('revoked_at')
                ->where('offer_id', $offerId)->when($userId !== null, fn ($q) => $q->where('user_id', $userId), fn ($q) => $q->where('name', $name))->exists();
            if ($duplicate) {
                Scores::fail('Esa persona ya figura con ese cargo en la comisión.');
            }
            $id = DB::table('commission_members')->insertGetId([
                'call_id' => $callId, 'offer_id' => $offerId, 'user_id' => $userId, 'name' => $name, 'cargo' => $cargo,
                'designation_reference' => $reference, 'accredited_on' => $accredited?->toDateString(),
                'created_by' => $u->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            Selection::audit($u, $callId, 'commission.member_added', 'commission-member:'.$id, null, ['cargo' => $cargo, 'user_id' => $userId, 'offer_id' => $offerId, 'reference' => $reference]);

            return $id;
        });
    }

    public function revoke(User $u, int $id, string $reason): void
    {
        Access::transaction($u, function () use ($u, $id, $reason) {
            $member = Selection::row('commission_members', $id, true);
            $call = Selection::row('calls', $member->call_id, true);
            Access::require($u, $call->id, ['authority']);
            if (! in_array($call->status, ['draft', 'published', 'closed'])) {
                Scores::fail('La comisión no se modifica una vez abierta la evaluación.');
            }
            if ($member->revoked_at || mb_strlen(trim($reason)) < 10) {
                Scores::fail('El integrante ya fue retirado o falta el motivo (mínimo 10 caracteres).');
            }
            DB::table('commission_members')->where('id', $id)->update(['revoked_at' => now(), 'revoked_by' => $u->id, 'updated_at' => now()]);
            Selection::audit($u, $call->id, 'commission.member_revoked', 'commission-member:'.$id, ['cargo' => $member->cargo], null, $reason);
        });
    }

    /** Integrantes vigentes de la convocatoria. */
    public static function members(int $callId): Collection
    {
        return DB::table('commission_members')->where('call_id', $callId)->whereNull('revoked_at')->orderBy('offer_id')->orderBy('id')->get();
    }

    /** Integra la comisión (no como veedor) de esa oferta, ya sea por designación de la oferta o de toda la convocatoria. */
    public static function isMember(int $callId, int $offerId, int $userId): bool
    {
        return DB::table('commission_members')->where('call_id', $callId)->where('user_id', $userId)->whereNull('revoked_at')
            ->whereNotIn('cargo', self::OBSERVERS)->where(fn ($q) => $q->whereNull('offer_id')->orWhere('offer_id', $offerId))->exists();
    }

    /**
     * Faltantes de composición por oferta: [oferta => descripciones de lo que falta]. Vacío si todas las ofertas cumplen.
     *
     * @return array<string, list<string>>
     */
    public static function missing(int $callId): array
    {
        $members = self::members($callId)->reject(fn ($m) => in_array($m->cargo, self::OBSERVERS, true));
        $catalogue = app(InstitutionalCatalogue::class);
        $result = [];
        foreach (DB::table('offers')->where('call_id', $callId)->orderBy('id')->get() as $offer) {
            $profile = $offer->requirement_profile ?: $catalogue->modality($offer);
            $mine = $members->filter(fn ($m) => $m->offer_id === null || $m->offer_id === $offer->id);
            $lacking = [];
            foreach (self::COMPOSITION[$profile] ?? [] as [$label, $cargos, $minimum]) {
                if ($mine->whereIn('cargo', $cargos)->count() < $minimum) {
                    $lacking[] = $label;
                }
            }
            if ($lacking) {
                $result[$offer->title] = $lacking;
            }
        }

        return $result;
    }

    public static function assertComplete(int $callId): void
    {
        $missing = self::missing($callId);
        if ($missing) {
            $text = implode('; ', array_map(fn ($title, $lack) => '«'.$title.'»: '.implode(', ', $lack), array_keys($missing), $missing));
            Scores::fail('La comisión evaluadora está incompleta (Resolución 021/2023, Arts. 13 y 14). Falta — '.$text.'.');
        }
    }
}
