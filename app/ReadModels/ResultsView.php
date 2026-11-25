<?php

namespace App\ReadModels;

use App\Domain\Access;
use App\Domain\Selection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ranking, desempates y publicaciones de una oferta (solo autoridad).
 * Compartido por la vista Blade y por la API: ninguna depende de la otra.
 */
final class ResultsView
{
    public function __construct(private Selection $service) {}

    public function data(Request $r, int $id): array
    {
        $offer = Selection::row('offers', $id);
        Access::require($r->user(), $offer->call_id, ['authority']);
        $call = Selection::row('calls', $offer->call_id);
        $documentary_reference = $call->mode === 'HISTORICAL' && DB::table('institutional_call_profiles')->where('call_id', $call->id)->exists();
        $ranking = null;
        $warning = null;
        try {
            $ranking = $this->service->ranking($id);
        } catch (ValidationException $e) {
            $warning = $e->validator->errors()->first();
        }
        $ties = DB::table('tie_exams')->where('offer_id', $id)->where('result_revision', $offer->result_revision)->get();
        $publications = DB::table('publications')->where('offer_id', $id)->get();

        $revisions = DB::table('result_revisions')->where('offer_id', $id)->orderByDesc('revision')->get();
        $oldTies = DB::table('tie_exams')->where('offer_id', $id)->where('result_revision', '!=', $offer->result_revision)->get();

        return compact('documentary_reference', 'revisions', 'oldTies', 'offer', 'call', 'ranking', 'warning', 'ties', 'publications');
    }
}
