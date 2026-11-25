<?php

namespace App\Http\Controllers;

use App\Domain\Access;
use App\Domain\Participation;
use App\Domain\Selection;
use App\Http\Operations\ParticipationOperations;
use App\ReadModels\ParticipationView;
use App\ReadModels\AwardsView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class ParticipationController extends Controller
{
    public function show(Request $r, int $id, ParticipationView $view)
    {
        return view('participation', $view->data($r, $id));
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

    public function awards(Request $r, AwardsView $view)
    {
        return view('awards', $view->data($r));
    }

    public function choose(Request $r, int $id, ParticipationOperations $ops)
    {
        $ops->choose($r, $id);

        return back()->with('success', 'Manifestación DEMO conservada. No certifica compatibilidad ni nombramiento institucional.');
    }
}
