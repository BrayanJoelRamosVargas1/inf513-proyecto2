<?php

namespace App\ReadModels;

use App\Domain\Access;
use App\Domain\Selection;
use App\Domain\Workspace;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Detalle de una convocatoria según los permisos de quien la consulta.
 * Compartido por la vista Blade y por la API: ninguna depende de la otra.
 */
final class CallView
{
    public function data(Request $r, int $id): array
    {
        $call = Selection::row('calls', $id);
        $u = $r->user();
        $authority = Access::has($u, $id, ['authority']);
        $reviewer = Access::has($u, $id, ['reviewer']);
        $evaluator = Access::has($u, $id, ['evaluator']);
        // Misma política de lectura que la API (PortalScope): membresía, postulación propia o convocatoria publicada para postulantes.
        abort_unless(PortalScope::calls($u)->where('calls.id', $id)->exists(), 403);
        $offers = DB::table('offers')->join('categories', 'categories.id', '=', 'offers.category_id')->where('call_id', $id)->when($evaluator && ! $authority && ! $reviewer && $u->role !== 'student', fn ($q) => $q->whereIn('offers.id', DB::table('assignments')->where('user_id', $u->id)->pluck('offer_id')))->select('offers.*', 'categories.name as category')->get();
        $apps = DB::table('applications')->join('offers', 'offers.id', '=', 'applications.offer_id')->join('users', 'users.id', '=', 'applications.user_id')->where('offers.call_id', $id)
            ->when(! $authority && ! $reviewer, fn ($q) => $q->where(function ($q) use ($u, $evaluator) {
                $q->where('applications.user_id', $u->id);
                if ($evaluator) {
                    $q->orWhereIn('applications.offer_id', DB::table('assignments')->where('user_id', $u->id)->pluck('offer_id'));
                }
            }))
            ->select('applications.*', 'offers.title', 'users.name')->orderBy('applications.id')->paginate(30);
        $categories = DB::table('categories')->where('active', true)->get();
        $teachers = User::whereIn('id', Access::effective(DB::table('memberships')->where('call_id', $id)->where('role', 'evaluator'))->pluck('user_id'))->where('active', true)->get();

        $ownApplications = DB::table('applications')->where('user_id', $u->id)->whereIn('offer_id', $offers->pluck('id'))->get()->keyBy('offer_id');
        $subjects = $ownApplications->whereIn('offer_id', $offers->where('is_subject', true)->pluck('id'))->count();
        $applicationBlocks = $offers->mapWithKeys(fn ($o) => [$o->id => Workspace::applicationBlock($u, $call, $o, $subjects)]);

        $editOffer = null;
        if ($authority && $call->status === 'draft' && $r->filled('editar_oferta')) {
            $editOffer = $offers->firstWhere('id', (int) $r->input('editar_oferta'));
        }

        return compact('call', 'offers', 'apps', 'categories', 'teachers', 'authority', 'reviewer', 'evaluator', 'editOffer', 'ownApplications', 'applicationBlocks');
    }
}
