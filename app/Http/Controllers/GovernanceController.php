<?php

namespace App\Http\Controllers;

use App\Domain\Access;
use App\Http\Operations\AdministrationOperations;
use App\Http\Operations\ResultsOperations;
use App\ReadModels\CorrectionsView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function profile(Request $r, int $id, AdministrationOperations $ops)
    {
        $ops->profile($r, $id);

        return back()->with('success', 'Perfil actualizado; las sesiones anteriores quedan invalidadas.');
    }

    public function revoke(Request $r, int $id, AdministrationOperations $ops)
    {
        $ops->revoke($r, $id);

        return back()->with('success', 'Permiso revocado y sesiones invalidadas.');
    }

    public function reassign(Request $r, int $id, AdministrationOperations $ops)
    {
        $ops->reassign($r, $id);

        return back()->with('success', 'Responsabilidad reasignada; las notas confirmadas conservan su autor original.');
    }

    public function corrections(Request $r, int $id, CorrectionsView $view)
    {
        return view('corrections', $view->data($r, $id));
    }

    public function requestCorrection(Request $r, int $id, ResultsOperations $ops)
    {
        $ops->requestCorrection($r, $id);

        return back()->with('success', 'Solicitud pendiente. Requiere decisión de otra autoridad autorizada.');
    }

    public function decide(Request $r, int $id, ResultsOperations $ops)
    {
        $ops->decideCorrection($r, $id);

        return back()->with('success', 'Decisión conservada. Una aprobación exige revisar el ranking y publicar una nueva versión.');
    }

    public function continueTie(Request $r, int $id, ResultsOperations $ops)
    {
        $ops->continueTie($r, $id);

        return back()->with('success', 'Ronda conservada; nuevo examen pendiente sin adjudicar plazas.');
    }
}
