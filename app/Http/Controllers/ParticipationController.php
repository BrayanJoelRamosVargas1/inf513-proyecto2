<?php

namespace App\Http\Controllers;

use App\Domain\Access;
use App\Domain\Participation;
use App\Domain\Selection;
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

        return view('participation', compact('canOpen', 'app', 'offer', 'owner', 'authority', 'reviewer', 'amendments', 'snapshots', 'requests', 'decisions', 'documents', 'award'));
    }

    public function open(Request $r, int $id, Participation $s)
    {
        $d = $r->validate(['kinds' => 'required|array|min:1', 'kinds.*' => 'required|string', 'deadline' => 'required|date', 'reason' => 'required|string|min:10|max:3000', 'evidence' => 'required|string|min:10|max:3000']);
        $s->open($r->user(), $id, $d['kinds'], $d['deadline'], $d['reason'], $d['evidence']);

        return back()->with('success', 'Ventana DEMO autorizada con plazo explícito. El expediente enviado se conserva.');
    }

    public function amendment(Request $r, int $id, string $action, Participation $s)
    {
        if ($action === 'archivo') {
            $d = $r->validate(['kind' => 'required|string', 'file' => 'required|file']);
            $s->upload($r->user(), $id, $d['kind'], $d['file']);
        } elseif ($action === 'enviar') {
            $s->submit($r->user(), $id);
        } elseif ($action === 'revisar') {
            $d = $r->validate(['decisions' => 'required|array', 'decisions.*' => 'required|string', 'reason' => 'required|string|min:10|max:3000']);
            $s->review($r->user(), $id, $d['decisions'], $d['reason']);
        } else {
            $d = $r->validate(['reason' => 'required|string|min:10|max:3000']);
            $s->expire($r->user(), $id, $d['reason']);
        }

        return back()->with('success', 'Movimiento registrado con sus versiones e historial.');
    }

    public function request(Request $r, int $id, Participation $s)
    {
        $d = $r->validate(['kind' => ['required', Rule::in(['withdrawal', 'renunciation'])], 'reason' => 'required|string|min:10|max:3000', 'evidence' => 'required|string|min:10|max:3000']);
        $s->request($r->user(), $id, $d['kind'], $d['reason'], $d['evidence']);

        return back()->with('success', 'Solicitud pendiente de otra autoridad. Todavía no cambió la participación ni la adjudicación.');
    }

    public function decide(Request $r, int $id, Participation $s)
    {
        $d = $r->validate(['approved' => 'required|boolean', 'reason' => 'required|string|min:10|max:3000']);
        $s->decide($r->user(), $id, (bool) $d['approved'], $d['reason']);

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

        return view('awards', ['awards' => $awards, 'groups' => $awards->groupBy('user_id')]);
    }

    public function choose(Request $r, int $id, Participation $s)
    {
        $d = $r->validate(['publication_id' => 'required|integer', 'version' => 'required|integer|min:0', 'choice' => ['required', Rule::in(['accept', 'defer'])], 'reason' => 'required|string|min:10|max:3000', 'evidence' => 'required|string|min:10|max:3000']);
        $s->choose($r->user(), $id, (int) $d['publication_id'], $d['choice'], (int) $d['version'], $d['reason'], $d['evidence']);

        return back()->with('success','Manifestación DEMO conservada. No certifica compatibilidad ni nombramiento institucional.');
    }
}
