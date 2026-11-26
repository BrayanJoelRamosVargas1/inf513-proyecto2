<?php

namespace App\Domain;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Clasificación, desempates y aprobación de resultados. Reglas y bloqueos idénticos a los de Selection, que expone la misma API. */
final class ResultsPublication
{
    public function ranking(int $offerId): array
    {
        $offer = Selection::row('offers', $offerId);
        if ($offer->seats === null) {
            throw ValidationException::withMessages([
                'seats' => 'Las plazas de esta oferta documental no están especificadas; no se genera clasificación.',
            ]);
        }
        if (Selection::row('calls', $offer->call_id)->mode === 'HISTORICAL' && DB::table('institutional_call_profiles')->where('call_id', $offer->call_id)->exists()) {
            Scores::fail('La fuente histórica contiene ofertas, no resultados oficiales incorporados. No se infieren ganadores, cobertura ni vacantes.');
        }
        $all = DB::table('applications')->join('users', 'users.id', '=', 'applications.user_id')->where('applications.offer_id', $offerId)->where('applications.status', 'submitted')->where('applications.participation', 'active')->select('applications.*', 'users.name')->orderBy('applications.id')->get();
        if ($all->contains(fn ($a) => $a->eligibility === 'pending' || ($a->eligibility === 'enabled' && $a->final === null))) {
            Scores::fail('Hay revisiones o resultados pendientes en esta oferta.');
        }
        $rank = $all->where('eligibility', 'enabled')->sort(fn ($a, $b) => bccomp($b->final, $a->final, 2) ?: $a->id <=> $b->id)->values()->all();
        // Nota mínima de aprobación (opcional): quien no la alcanza queda fuera de la clasificación y de las plazas.
        $below = [];
        if ($offer->approval_threshold !== null) {
            $threshold = (string) $offer->approval_threshold;
            $below = array_values(array_filter($rank, fn ($a) => bccomp($a->final, $threshold, 2) < 0));
            $rank = array_values(array_filter($rank, fn ($a) => bccomp($a->final, $threshold, 2) >= 0));
        }
        $tied = [];
        $cut = $offer->seats - $offer->held_vacancies;
        if ($cut > 0 && count($rank) > $cut && bccomp($rank[$cut - 1]->final, $rank[$cut]->final, 2) === 0) {
            $score = $rank[$cut]->final;
            $tied = array_values(array_map(fn ($a) => $a->id, array_filter($rank, fn ($a) => bccomp($a->final, $score, 2) === 0)));
        }

        return [
            'rank' => $rank,
            'below_threshold' => $below,
            'approval_threshold' => $offer->approval_threshold,
            'tied' => $tied,
            'seats' => $cut,
            'held_vacancies' => $offer->held_vacancies,
            'vacancies' => max($offer->held_vacancies, $offer->seats - count($rank)),
        ];
    }

    public function openTie(User $u, int $offerId, array $d): int
    {
        return Access::transaction($u, function () use ($u, $offerId, $d) {
            $o = Selection::row('offers', $offerId);
            $c = Selection::row('calls', $o->call_id, true);
            $o = Selection::row('offers', $offerId, true);
            Access::require($u, $c->id, ['authority']);
            Access::assertNotApplicant($u, $c->id);
            Selection::demo($c);
            if ((! in_array($c->status, ['evaluation', 'results']) || ($c->status === 'results' && ! $o->needs_publication_review)) || DB::table('publications')->where('offer_id', $offerId)->where('result_revision', $o->result_revision)->exists() || DB::table('tie_exams')->where('offer_id', $offerId)->where('result_revision', $o->result_revision)->exists()) {
                Scores::fail('No se puede abrir otro desempate en este estado.');
            }
            app(ApplicantDocuments::class)->assertOffer($offerId);
            $r = $this->ranking($offerId);
            if (! $r['tied']) {
                Scores::fail('No hay empate que afecte el corte de plazas.');
            }
            $id = DB::table('tie_exams')->insertGetId([
                'offer_id' => $offerId,
                'result_revision' => $o->result_revision,
                'status' => 'scheduled',
                'participants' => json_encode($r['tied']),
                'commission' => json_encode($d['commission']),
                'conditions' => $d['conditions'],
                'scheduled_at' => $d['scheduled_at'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            Selection::audit($u, $c->id, 'tie.exam_scheduled', 'tie:'.$id, null, [
                'participants' => $r['tied'],
                'conditions' => $d['conditions'],
                'commission' => $d['commission'],
            ]);

            return $id;
        });
    }

    public function resolveTie(User $u, int $id, array $results, array $winners, string $minutes): void
    {
        Access::transaction($u, function () use ($u, $id, $results, $winners, $minutes) {
            $t = Selection::row('tie_exams', $id);
            $o = Selection::row('offers', $t->offer_id);
            $c = Selection::row('calls', $o->call_id, true);
            $o = Selection::row('offers', $o->id, true);
            $t = Selection::row('tie_exams', $id, true);
            Access::require($u, $c->id, ['authority']);
            Access::assertNotApplicant($u, $c->id);
            Selection::demo($c);
            if ($t->status !== 'scheduled' || ! in_array($c->status, ['evaluation', 'results']) || $t->result_revision !== $o->result_revision) {
                Scores::fail('El nuevo examen no está pendiente.');
            }
            if (now()->lt($t->scheduled_at)) {
                Scores::fail('El nuevo examen aún no se realizó.');
            }
            app(ApplicantDocuments::class)->assertOffer($o->id);
            $participants = Selection::json($t->participants);
            $keys = array_map('intval', array_keys($results));
            sort($keys);
            $expected = $participants;
            sort($expected);
            if ($keys !== $expected || array_diff($winners, $participants) || count($winners) !== count(array_unique($winners))) {
                Scores::fail('Solo pueden participar los empatados y todos requieren resultado.');
            }
            foreach ($results as $result) {
                if (! is_string($result) || ! trim($result) || mb_strlen($result) > 1000) {
                    Scores::fail('Registre el resultado de cada aspirante según condiciones de comisión.');
                }
            }
            $r = $this->ranking($o->id);
            $above = count(array_filter($r['rank'], fn ($a) => ! in_array($a->id, $participants) && bccomp($a->final, Selection::row('applications', $participants[0])->final, 2) > 0));
            if (count($winners) !== max(0, $o->seats - $o->held_vacancies - $above)) {
                Scores::fail('La decisión debe respetar las plazas en disputa. Si persiste empate, mantenga pendiente y registre el procedimiento institucional.');
            }
            DB::table('tie_exams')->where('id', $id)->update([
                'status' => 'resolved',
                'results' => json_encode($results),
                'winners' => json_encode(array_map('intval', $winners)),
                'minutes' => $minutes,
                'updated_at' => now(),
            ]);
            Selection::audit($u, $c->id, 'tie.exam_resolved', 'tie:'.$id, null, ['results' => $results, 'winners' => $winners], $minutes);
        });
    }

    public function continueTie(User $u, int $id, array $results, array $d): int
    {
        return Access::transaction($u, function () use ($u, $id, $results, $d) {
            $t = Selection::row('tie_exams', $id);
            $o = Selection::row('offers', $t->offer_id);
            $c = Selection::row('calls', $o->call_id, true);
            Access::require($u, $c->id, ['authority']);
            Access::assertNotApplicant($u, $c->id);
            Selection::demo($c);
            if ($t->status !== 'scheduled' || ! in_array($c->status, ['evaluation', 'results']) || $t->result_revision !== $o->result_revision || now()->lt($t->scheduled_at)) {
                Scores::fail('La ronda no admite continuidad en este estado.');
            }
            app(ApplicantDocuments::class)->assertOffer($o->id);
            $participants = Selection::json($t->participants);
            $expected = $participants;
            sort($expected);
            $keys = array_map('intval', array_keys($results));
            sort($keys);
            if ($keys !== $expected || count(array_filter($results, fn ($v) => ! is_string($v) || ! trim($v) || mb_strlen($v) > 1000))) {
                Scores::fail('Registre el resultado de cada participante.');
            }
            if (mb_strlen(trim($d['minutes'] ?? '')) < 20 || mb_strlen(trim($d['conditions'] ?? '')) < 20 || empty($d['commission']) || CarbonImmutable::parse($d['scheduled_at'])->lte(CarbonImmutable::parse($t->scheduled_at))) {
                Scores::fail('Documente la persistencia, nueva comisión/condiciones y fecha posterior.');
            }
            DB::table('tie_exams')->where('id', $id)->update([
                'status' => 'continued',
                'results' => json_encode($results),
                'minutes' => $d['minutes'],
                'updated_at' => now(),
            ]);
            // La comisión declara persistencia; el sistema no inventa una fórmula ni elimina aspirantes.
            $new = DB::table('tie_exams')->insertGetId([
                'offer_id' => $o->id,
                'result_revision' => $o->result_revision,
                'round' => $t->round + 1,
                'previous_tie_id' => $id,
                'status' => 'scheduled',
                'participants' => json_encode($participants),
                'commission' => json_encode($d['commission']),
                'conditions' => $d['conditions'],
                'scheduled_at' => $d['scheduled_at'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            Selection::audit($u, $c->id, 'tie.continued', 'tie:'.$id, ['status' => $t->status], ['next_tie' => $new, 'results' => $results], $d['minutes']);

            return $new;
        });
    }

    public function approve(User $u, int $offerId, string $minutes): void
    {
        Access::transaction($u, function () use ($u, $offerId, $minutes) {
            $o = Selection::row('offers', $offerId);
            $c = Selection::row('calls', $o->call_id, true);
            $o = Selection::row('offers', $offerId, true);
            Access::require($u, $c->id, ['authority']);
            Access::assertNotApplicant($u, $c->id);
            Selection::demo($c);
            if ((! in_array($c->status, ['evaluation', 'results']) || ($c->status === 'results' && ! $o->needs_publication_review)) || DB::table('publications')->where('offer_id', $offerId)->where('result_revision', $o->result_revision)->exists()) {
                Scores::fail('La oferta no admite otra aprobación silenciosa.');
            }
            if (mb_strlen(trim($minutes)) < 10) {
                Scores::fail('Se requiere acta de aprobación DEMO.');
            }
            if (DB::table('correction_requests')->join('applications', 'applications.id', '=', 'correction_requests.application_id')->where('applications.offer_id', $offerId)->where('correction_requests.status', 'pending')->exists()) {
                Scores::fail('Resuelva las rectificaciones pendientes.');
            }
            if (DB::table('participation_requests')->join('applications', 'applications.id', '=', 'participation_requests.application_id')->where('applications.offer_id', $offerId)->where('participation_requests.status', 'pending')->exists() || DB::table('amendments')->join('applications', 'applications.id', '=', 'amendments.application_id')->where('applications.offer_id', $offerId)->whereIn('amendments.status', ['open', 'submitted'])->exists()) {
                Scores::fail('Resuelva solicitudes y subsanaciones antes de publicar.');
            }
            app(ApplicantDocuments::class)->assertOffer($offerId);
            $r = $this->ranking($offerId);
            $winners = array_map(fn ($a) => $a->id, array_slice($r['rank'], 0, $o->seats - $o->held_vacancies));
            if ($r['tied']) {
                $tie = DB::table('tie_exams')->where('offer_id', $offerId)->where('result_revision', $o->result_revision)->where('status', 'resolved')->first();
                if (! $tie) {
                    Scores::fail('Se requiere nuevo examen de desempate y acta de comisión.');
                }
                $winners = array_values(array_diff($winners, $r['tied']));
                $winners = array_merge($winners, Selection::json($tie->winners));
            }
            if (count($winners) > $o->seats - $o->held_vacancies) {
                Scores::fail('La selección excede las plazas.');
            }
            foreach (array_merge($r['rank'], $r['below_threshold']) as $a) {
                DB::table('applications')->where('id', $a->id)->update([
                    'selection' => in_array($a->id, $winners) ? 'selected' : 'not_selected',
                    'updated_at' => now(),
                ]);
            }
            $snapshot = [
                'mode' => 'DEMO',
                'offer' => (array) $o,
                'ranking' => $this->ranking($offerId)['rank'],
                'selected' => $winners,
                'approved_at' => now()->toIso8601String(),
            ];
            DB::table('publications')->insert([
                'offer_id' => $offerId,
                'version' => (DB::table('publications')->where('offer_id', $offerId)->max('version') ?? 0) + 1,
                'result_revision' => $o->result_revision,
                'previous_publication_id' => DB::table('publications')->where('offer_id', $offerId)->orderByDesc('version')->value('id'),
                'snapshot' => json_encode($snapshot),
                'minutes' => $minutes,
                'user_id' => $u->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('offers')->where('id', $offerId)->update(['needs_publication_review' => false]);
            Selection::audit($u, $c->id, 'result.approved', 'offer:'.$offerId, null, ['selected' => $winners], $minutes);
        });
    }
}
