<?php

namespace App\Http\Controllers\Api;

use App\Domain\Access;
use App\Domain\Commission;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Comisión evaluadora y veedores de una convocatoria. La consulta y el registro son de la autoridad de la convocatoria. */
final class CommissionController extends Controller
{
    public function index(Request $r, int $id)
    {
        Access::require($r->user(), $id, ['authority']);
        $members = Commission::members($id);
        $accounts = User::whereIn('id', $members->pluck('user_id')->filter())->pluck('name', 'id');

        return response()->json([
            'cargos' => Commission::CARGOS,
            'observers' => Commission::OBSERVERS,
            'members' => $members->map(fn ($m) => [
                'id' => $m->id,
                'offer_id' => $m->offer_id,
                'user_id' => $m->user_id,
                'name' => $accounts[$m->user_id] ?? $m->name,
                'cargo' => $m->cargo,
                'designation_reference' => $m->designation_reference,
                'accredited_on' => $m->accredited_on,
            ])->values(),
            'missing' => (object) Commission::missing($id),
            'offers' => DB::table('offers')->where('call_id', $id)->orderBy('id')->get(['id', 'title']),
            // Cuentas que pueden integrar la comisión: personal y autoridades activos que no postulan en la convocatoria.
            'candidates' => User::whereIn('role', ['staff', 'authority'])->where('active', true)->orderBy('name')->get(['id', 'name'])
                ->reject(fn ($u) => Access::isApplicant($u, $id))->values(),
            'exam_at' => DB::table('calls')->where('id', $id)->value('exam_at'),
        ]);
    }

    public function store(Request $r, int $id)
    {
        $d = $r->validate([
            'cargo' => 'required|string|max:40',
            'user_id' => 'nullable|integer',
            'name' => 'nullable|string|max:180',
            'offer_id' => 'nullable|integer',
            'designation_reference' => 'required|string|max:100',
            'accredited_on' => 'nullable|date_format:Y-m-d',
        ]);

        return response()->json(['id' => app(Commission::class)->add($r->user(), $id, $d)], 201);
    }

    public function revoke(Request $r, int $id)
    {
        $d = $r->validate(['reason' => 'required|string|min:10|max:3000']);
        app(Commission::class)->revoke($r->user(), $id, $d['reason']);

        return response()->noContent();
    }
}
