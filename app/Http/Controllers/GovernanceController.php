<?php

namespace App\Http\Controllers;

use App\Domain\Access;
use App\Domain\Administration;
use App\Domain\Rectifications;
use App\Domain\Selection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class GovernanceController extends Controller
{
    public function queue(Request $r)
    {
        $scope = Access::effective(DB::table('memberships')->where('user_id', $r->user()->id)->whereIn('role', ['authority', 'reviewer', 'evaluator']))->pluck('call_id');
        abort_if($scope->isEmpty(), 403);
        $requests = DB::table('correction_requests')->join('applications', 'applications.id', '=', 'correction_requests.application_id')->join('offers', 'offers.id', '=', 'applications.offer_id')->whereIn('offers.call_id', $scope)->orderByDesc('correction_requests.id')->select('correction_requests.*', 'offers.title', 'offers.call_id')->get()->filter(function ($x) use ($r) {
            return Access::has($r->user(), $x->call_id, ['authority']) || ($x->kind === 'merit' && Access::has($r->user(), $x->call_id, ['reviewer'])) || ($x->requester_id === $r->user()->id && Access::has($r->user(), $x->call_id, ['evaluator']));
        });

        return view('correction-queue', compact('requests'));
    }

    public function profile(Request $r, int $id, Administration $service)
    {
        $d = $r->validate(['name' => 'required|string|max:100', 'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($id)], 'role' => ['required', Rule::in(['admin', 'authority', 'staff', 'student'])], 'faculty' => 'required|string|max:80', 'career' => 'required|string|max:80', 'active' => 'required|boolean', 'version' => 'required|integer', 'reason' => 'required|string|min:10|max:3000']);
        $service->profile($r->user(), $id, $d, (int) $d['version'], $d['reason']);

        return back()->with('success', 'Perfil actualizado; las sesiones anteriores quedan invalidadas.');
    }

    public function revoke(Request $r, int $id, Administration $service)
    {
        $d = $r->validate(['version' => 'required|integer', 'reason' => 'required|string|min:10|max:3000']);
        $service->revoke($r->user(), $id, (int) $d['version'], $d['reason']);

        return back()->with('success', 'Permiso revocado y sesiones invalidadas.');
    }

    public function reassign(Request $r, int $id, Administration $service)
    {
        $d = $r->validate(['user_id' => 'required|integer|exists:users,id', 'version' => 'required|integer', 'reason' => 'required|string|min:10|max:3000']);
        $service->reassign($r->user(), $id, (int) $d['user_id'], (int) $d['version'], $d['reason']);

        return back()->with('success', 'Responsabilidad reasignada; las notas confirmadas conservan su autor original.');
    }

    public function corrections(Request $r, int $id)
    {
        $app = Selection::row('applications', $id);
        $offer = Selection::row('offers', $app->offer_id);
        Access::require($r->user(), $offer->call_id, ['authority', 'reviewer', 'evaluator']);
        $authority = Access::has($r->user(), $offer->call_id, ['authority']);
        $reviewer = Access::has($r->user(), $offer->call_id, ['reviewer']);
        $assignments = DB::table('assignments')->where('offer_id', $offer->id)->get();
        $grades = DB::table('grades')->where('application_id', $id)->get()->filter(fn ($g) => $authority || $assignments->contains(fn ($a) => $a->id === $g->assignment_id && $a->user_id === $r->user()->id));
        abort_unless($authority || $reviewer || $grades->isNotEmpty(), 403);
        $requests = DB::table('correction_requests')->where('application_id', $id)->orderByDesc('id')->get()->filter(fn ($x) => $authority || ($reviewer && $x->kind === 'merit') || $x->requester_id === $r->user()->id);
        $versions = DB::table('score_versions')->where('application_id', $id)->orderBy('id')->get()->filter(fn ($x) => $authority || ($reviewer && ! $x->grade_id) || $grades->contains('id', $x->grade_id));

        $call = Selection::row('calls', $offer->call_id);

        return view('corrections', compact('app', 'offer', 'call', 'authority', 'reviewer', 'grades', 'requests', 'versions'));
    }

    public function requestCorrection(Request $r, int $id, Rectifications $service)
    {
        $d = $r->validate(['grade_id' => 'nullable|integer', 'score' => 'required|string', 'reason' => 'required|string|min:10|max:3000', 'evidence' => 'required|string|min:10|max:3000']);
        $service->request($r->user(), $id, isset($d['grade_id']) ? (int) $d['grade_id'] : null, $d['score'], $d['reason'], $d['evidence']);

        return back()->with('success', 'Solicitud pendiente. Requiere decisión de otra autoridad autorizada.');
    }

    public function decide(Request $r, int $id, Rectifications $service)
    {
        $d = $r->validate(['approved' => 'required|boolean', 'reason' => 'required|string|min:10|max:3000']);
        $service->decide($r->user(), $id, (bool) $d['approved'], $d['reason']);

        return back()->with('success', 'Decisión conservada. Una aprobación exige revisar el ranking y publicar una nueva versión.');
    }

    public function continueTie(Request $r, int $id, Selection $service)
    {
        $d = $r->validate(['results' => 'required|array', 'results.*' => 'required|string|max:1000', 'minutes' => 'required|string|min:20|max:8000', 'commission' => 'required|string|max:1500', 'conditions' => 'required|string|min:20|max:5000', 'scheduled_at' => 'required|date']);
        $d['commission'] = array_values(array_filter(array_map('trim', explode(',', $d['commission']))));
        $service->continueTie($r->user(), $id, $d['results'], $d);

        return back()->with('success', 'Ronda conservada; nuevo examen pendiente sin adjudicar plazas.');
    }
}
