<?php

namespace App\Domain;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Instantánea mínima y determinista del orden de una oferta, para las revisiones de resultado
 * (rectificaciones, retiros y renuncias). Las tablas de revisiones son inmutables, así que solo se
 * guarda lo necesario para comparar posiciones: identificador y puntajes, sin nombres ni datos
 * personales (auditoría B-08) y con un único formato, con o sin resultados completos (B-13).
 */
final class ResultRevisions
{
    /** @return array{rank:list<array{id:int,merit:?string,exam:?string,final:?string}>,tied:list<int>,seats:?int,incomplete:bool,pending:list<int>} */
    public function snapshot(int $offer): array
    {
        try {
            $ranking = app(Selection::class)->ranking($offer);
            $rank = $ranking['rank'];
            $tied = $ranking['tied'];
            $seats = $ranking['seats'];
            $incomplete = false;
            $pending = [];
        } catch (ValidationException $e) {
            // Resultados todavía incompletos: se conserva el orden parcial de quienes ya tienen nota final.
            $entries = DB::table('applications')->where('offer_id', $offer)->where('status', 'submitted')->where('participation', 'active')->orderBy('id')->get();
            $rank = $entries->where('eligibility', 'enabled')->whereNotNull('final')
                ->sort(fn ($a, $b) => bccomp($b->final, $a->final, 2) ?: $a->id <=> $b->id)->values()->all();
            $tied = [];
            $seats = Selection::row('offers', $offer)->seats;
            $incomplete = true;
            $pending = $entries->filter(fn ($a) => $a->eligibility === 'pending' || ($a->eligibility === 'enabled' && $a->final === null))->pluck('id')->all();
        }

        return [
            'rank' => array_map(fn ($a) => ['id' => $a->id, 'merit' => $a->merit, 'exam' => $a->exam, 'final' => $a->final], $rank),
            'tied' => $tied, 'seats' => $seats, 'incomplete' => $incomplete, 'pending' => $pending,
        ];
    }

    /** Cambios de posición y de nota final entre dos instantáneas. */
    public function changes(array $before, array $after): array
    {
        $positions = array_flip(array_column($before['rank'], 'id'));
        $changes = [];
        foreach ($after['rank'] as $i => $entry) {
            $j = $positions[$entry['id']] ?? null;
            $changes[] = [
                'application_id' => $entry['id'], 'old_position' => $j === null ? null : $j + 1, 'new_position' => $i + 1,
                'old_final' => $j === null ? null : $before['rank'][$j]['final'], 'new_final' => $entry['final'],
            ];
        }

        return $changes;
    }
}
