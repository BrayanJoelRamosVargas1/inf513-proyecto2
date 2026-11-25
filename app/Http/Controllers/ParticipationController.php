<?php

namespace App\Http\Controllers;

use App\Domain\Access;
use App\Domain\Participation;
use App\Domain\Selection;
use App\Http\Operations\ParticipationOperations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class ParticipationController extends Controller
{
    public function show(Request $r, int $id)
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
        $canOpen = ($authority || $reviewer) && ! $owner && $call->mode === 'DEMO' && in_array($call->status, ['published', 'closed']) && $app->status === 'submitted' && in_array($app->eligibility, ['pending', 'disabled']) && ! $amendments->whereIn('status', ['open', 'submitted'])->count() && DB::table('reviews')->where('application_id', $id)->whereIn('decision', ['observed', 'fails'])->exists();

        return view('participation', compact('call', 'canOpen', 'app', 'offer', 'owner', 'authority', 'reviewer', 'amendments', 'snapshots', 'requests', 'decisions', 'documents', 'award'));
    }

    public function open(Request $r, int $id, ParticipationOperations $ops)
    {
        $ops->open($r, $id);

        return back()->with('success', 'Ventana DEMO autorizada con plazo explícito. El expediente enviado se conserva.');
    }

    public function amendment(Request $r, int $id, string $action, ParticipationOperations $ops)
    {
        $ops->amendment($r, $id, $action);

        return back()->with('success', 'Movimiento registrado con sus versiones e historial.');
    }

    public function request(Request $r, int $id, ParticipationOperations $ops)
    {
        $ops->request($r, $id);

        return back()->with('success', 'Solicitud pendiente de otra autoridad. Todavía no cambió la participación ni la adjudicación.');
    }

    public function decide(Request $r, int $id, ParticipationOperations $ops)
    {
        $ops->decide($r, $id);

        return back()->with('success', 'Decisión conservada. Revise el ranking y la publicación cuando corresponda; no se asignaron reemplazantes.');
    }

    public function awards(Request $r)
    {
        $scope = Access::effective(DB::table('memberships')->where('user_id', $r->user()->id)->where('role', 'authority'))->pluck('call_id');
        $apps = DB::table('applications')->join('offers', 'offers.id', '=', 'applications.offer_id')->join('users', 'users.id', '=', 'applications.user_id')
            ->where(fn ($q) => $q->where('applications.user_id', $r->user()->id)->orWhereIn('offers.call_id', $scope))
            ->select('applications.*', 'offers.title', 'offers.call_id', 'users.name')->get();
        $awards = collect();
        foreach ($apps as $a) {
            $pub = Participation::award($a);
            if ($pub) {
                $a->publication = $pub;
                $a->choices = DB::table('award_choices')->where('application_id', $a->id)->orderByDesc('id')->get();
                $a->choice = $a->choices->firstWhere('publication_id', $pub->id);
                $awards->push($a);
            }
        }

        $authorityView = $scope->isNotEmpty();

        return view('awards', ['authorityView' => $authorityView, 'awards' => $awards, 'groups' => $awards->groupBy('user_id')]);
    }

    public function choose(Request $r, int $id, ParticipationOperations $ops)
    {
        $ops->choose($r, $id);

        return back()->with('success', 'Manifestación DEMO conservada. No certifica compatibilidad ni nombramiento institucional.');
    }
}
