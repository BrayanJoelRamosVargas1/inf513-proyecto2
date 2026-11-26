<?php

namespace App\ReadModels;

use App\Domain\Access;
use App\Domain\Participation;
use App\Domain\Selection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Subsanaciones, retiros y adjudicación de una postulación.
 * Compartido por la vista Blade y por la API: ninguna depende de la otra.
 */
final class ParticipationView
{
    public function data(Request $r, int $id): array
    {
        $app = Selection::row('applications', $id);
        $offer = Selection::row('offers', $app->offer_id);
        $owner = $app->user_id === $r->user()->id;
        $authority = Access::has($r->user(), $offer->call_id, ['authority']);
        $reviewer = Access::has($r->user(), $offer->call_id, ['reviewer']);
        abort_unless($owner || $authority || $reviewer, 403);
        $amendments = DB::table('amendments')->where('application_id', $id)->orderByDesc('id')->get();
        $snapshots = DB::table('dossier_snapshots')->where('application_id', $id)->orderByDesc('id')->get();
        $requests = DB::table('participation_requests')->where('application_id', $id)->orderByDesc('id')->get();
        $decisions = DB::table('participation_decisions')->whereIn('participation_request_id', $requests->pluck('id'))->get()->keyBy('participation_request_id');
        $documents = DB::table('documents')->where('application_id', $id)->orderByDesc('version')->get();
        $award = Participation::award($app);

        $call = Selection::row('calls', $offer->call_id);
        $canOpen = ($authority || $reviewer) && ! $owner && Selection::isOperable($call) && in_array($call->status, ['published', 'closed']) && $app->status === 'submitted' && in_array($app->eligibility, ['pending', 'disabled']) && ! $amendments->whereIn('status', ['open', 'submitted'])->count() && DB::table('reviews')->where('application_id', $id)->whereIn('decision', ['observed', 'fails'])->exists();

        return compact('call', 'canOpen', 'app', 'offer', 'owner', 'authority', 'reviewer', 'amendments', 'snapshots', 'requests', 'decisions', 'documents', 'award');
    }
}
