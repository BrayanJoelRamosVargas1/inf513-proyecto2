<?php

namespace App\Domain;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Plazas sin cubrir, convocatorias desiertas y nueva convocatoria (Resolución 021/2023: Art. 20 de docencia, Art. 19 de laboratorio).
 *
 * Si nadie se presenta, nadie alcanza el puntaje de aprobación o el designado no acepta, la plaza queda sin cubrir y se
 * convoca de nuevo. Solo se admiten dos convocatorias consecutivas; después, el Vicedecano (o el Decano) levanta un acta de
 * declaración desierta o vacante y puede elegir entre los aprobados, entre los presentados o invitar con formulario por
 * excepción de la DICAA. El sistema cuenta las convocatorias y lleva las plazas a la siguiente; el acta y la elección las
 * registra la autoridad en el registro institucional.
 */
final class Vacancies
{
    public const MAX_CONSECUTIVE = 2;

    /** @return array{call: object, offers: list<array<string, mixed>>} */
    public function report(User $u, int $callId): array
    {
        Access::require($u, $callId, ['authority']);
        $call = Selection::row('calls', $callId);
        $offers = [];
        foreach (DB::table('offers')->where('call_id', $callId)->orderBy('id')->get() as $offer) {
            $offers[] = $this->offerStatus($offer);
        }

        return ['call' => $call, 'offers' => $offers];
    }

    private function offerStatus(object $offer): array
    {
        $published = DB::table('publications')->where('offer_id', $offer->id)->exists();
        $applicants = DB::table('applications')->where('offer_id', $offer->id)->where('status', 'submitted')->where('participation', 'active')->count();
        $selected = DB::table('applications')->where('offer_id', $offer->id)->where('participation', 'active')->where('selection', 'selected')->count();
        $seats = (int) ($offer->seats ?? 0);
        $vacancies = $published ? max(0, $seats - $selected) : null;
        $consecutive = 0;
        if ($vacancies !== null && $vacancies > 0) {
            $consecutive = 1;
            $current = $offer;
            while ($previous = $this->previousOffer($current)) {
                if (! DB::table('publications')->where('offer_id', $previous->id)->exists() || $this->unfilled($previous) < 1) {
                    break;
                }
                $consecutive++;
                $current = $previous;
            }
        }
        $outcome = match (true) {
            ! $published => 'pending',
            $vacancies === 0 => 'covered',
            $selected === 0 => 'deserted',
            default => 'partial',
        };

        return [
            'offer_id' => $offer->id, 'title' => $offer->title, 'modality' => $offer->modality, 'seats' => $seats,
            'applicants' => $applicants, 'selected' => $selected, 'vacancies' => $vacancies, 'outcome' => $outcome,
            'consecutive_calls_unfilled' => $consecutive,
            'may_declare' => $consecutive >= self::MAX_CONSECUTIVE,
        ];
    }

    private function unfilled(object $offer): int
    {
        $selected = DB::table('applications')->where('offer_id', $offer->id)->where('participation', 'active')->where('selection', 'selected')->count();

        return max(0, (int) ($offer->seats ?? 0) - $selected);
    }

    private function previousOffer(object $offer): ?object
    {
        $parent = DB::table('calls')->where('id', $offer->call_id)->value('parent_id');
        if (! $parent) {
            return null;
        }

        return DB::table('offers')->where('call_id', $parent)->where('title', $offer->title)->where('modality', $offer->modality)->first();
    }

    /**
     * Crea un borrador de convocatoria vinculado a la anterior con las plazas que quedaron sin cubrir.
     * La autoridad completa fechas y fuente y la publica por el camino normal (con la aprobación del HCF).
     */
    public function carryOver(User $u, int $callId, array $d): int
    {
        $source = Selection::row('calls', $callId);
        Access::require($u, $callId, ['authority']);
        if (! in_array($source->status, ['results', 'archived'])) {
            Scores::fail('Las plazas sin cubrir se llevan a una nueva convocatoria cuando los resultados están aprobados.');
        }
        $pending = collect($this->report($u, $callId)['offers'])->filter(fn ($o) => ($o['vacancies'] ?? 0) > 0);
        if ($pending->isEmpty()) {
            Scores::fail('Todas las ofertas de esta convocatoria tienen sus plazas cubiertas.');
        }
        $blocked = $pending->first(fn ($o) => $o['consecutive_calls_unfilled'] >= self::MAX_CONSECUTIVE);
        if ($blocked) {
            Scores::fail('La oferta «'.$blocked['title'].'» ya cumplió dos convocatorias consecutivas sin cubrirse: corresponde el acta de declaración desierta o vacante y elegir una de las opciones del reglamento, no una tercera convocatoria.');
        }
        $selection = app(Selection::class);
        $newCall = $selection->createCall($u, $d + [
            'scope' => $source->scope, 'scope_value' => $source->scope_value, 'subject_limit' => $source->subject_limit,
            'management_year' => $source->management_year, 'academic_period' => $source->academic_period,
            'reception_place' => $source->reception_place, 'exam_place' => $source->exam_place, 'parent_id' => $callId,
        ]);
        foreach ($pending as $row) {
            $offer = (array) Selection::row('offers', $row['offer_id']);
            foreach (['id', 'call_id', 'created_at', 'updated_at', 'version', 'held_vacancies', 'result_revision', 'needs_publication_review'] as $drop) {
                unset($offer[$drop]);
            }
            $offer['call_id'] = $newCall;
            $offer['seats'] = $row['vacancies'];
            $offer['created_at'] = now();
            $offer['updated_at'] = now();
            DB::table('offers')->insert($offer);
        }
        Selection::audit($u, $newCall, 'call.carried_over', 'call:'.$callId, null, ['offers' => $pending->pluck('title')->values()->all()]);

        return $newCall;
    }
}
