<?php

namespace App\ReadModels;

use App\Domain\Access;
use App\Domain\Selection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Rectificaciones y versiones de nota de una postulación.
 * Compartido por la vista Blade y por la API: ninguna depende de la otra.
 */
final class CorrectionsView
{
    public function data(Request $r, int $id): array
    {
        $app = Selection::row('applications', $id);
        $offer = Selection::row('offers', $app->offer_id);
        Access::require($r->user(), $offer->call_id, ['authority', 'reviewer', 'evaluator']);
        $authority = Access::has($r->user(), $offer->call_id, ['authority']);
        $reviewer = Access::has($r->user(), $offer->call_id, ['reviewer']);
        $evaluator = Access::has($r->user(), $offer->call_id, ['evaluator']);
        $assignments = DB::table('assignments')->where('offer_id', $offer->id)->get();
        $grades = DB::table('grades')->where('application_id', $id)->get()->filter(fn ($g) => $authority || ($evaluator && $assignments->contains(fn ($a) => $a->id === $g->assignment_id && $a->user_id === $r->user()->id)));
        abort_unless($authority || $reviewer || $grades->isNotEmpty(), 403);
        $requests = DB::table('correction_requests')->where('application_id', $id)->orderByDesc('id')->get()->filter(fn ($x) => $authority || ($reviewer && $x->kind === 'merit') || $x->requester_id === $r->user()->id);
        $versions = DB::table('score_versions')->where('application_id', $id)->orderBy('id')->get()->filter(fn ($x) => $authority || ($reviewer && ! $x->grade_id) || $grades->contains('id', $x->grade_id));

        $call = Selection::row('calls', $offer->call_id);

        return compact('app', 'offer', 'call', 'authority', 'reviewer', 'grades', 'requests', 'versions');
    }
}
