<?php

namespace App\ReadModels;

use App\Domain\Access;
use App\Domain\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pantalla de inicio: convocatorias visibles, postulaciones propias y tareas pendientes.
 * Compartido por la vista Blade y por la API: ninguna depende de la otra.
 */
final class HomeView
{
    public function data(Request $r): array
    {
        $u = $r->user();
        $ids = Access::effective(DB::table('memberships')->where('user_id', $u->id))->pluck('call_id');
        $roles = Workspace::roles($u);
        $calls = DB::table('calls')->when($u->role !== 'admin', function ($q) use ($u, $ids) {
            if ($u->role === 'student') {
                $q->where(fn ($q) => $q->where('status', '!=', 'draft')->orWhereIn('id', $ids));
            } else {
                $q->whereIn('id', $ids);
            }
        })->orderByDesc('id')->get();
        if ($u->role === 'student') {
            $calls = $calls->sortByDesc(fn ($c) => Workspace::receiving($c))->values();
        }
        $apps = DB::table('applications')->join('offers', 'offers.id', '=', 'applications.offer_id')->where('applications.user_id', $u->id)->select('applications.*', 'offers.title')->get();

        $tasks = DB::table('applications')->join('offers', 'offers.id', '=', 'applications.offer_id')->join('calls', 'calls.id', '=', 'offers.call_id')->join('users', 'users.id', '=', 'applications.user_id')
            ->where('applications.participation', 'active')->where('applications.status', 'submitted')
            ->where(function ($q) use ($u) {
                $reviewCalls = Access::effective(DB::table('memberships')->where('user_id', $u->id)->where('role', 'reviewer'))->pluck('call_id');
                $evalCalls = Access::effective(DB::table('memberships')->where('user_id', $u->id)->where('role', 'evaluator'))->pluck('call_id');
                $q->where(fn ($q) => $q->whereIn('calls.id', $reviewCalls)->where('applications.user_id', '!=', $u->id)->whereIn('calls.status', ['published', 'closed', 'evaluation']))
                    ->orWhere(fn ($q) => $q->whereIn('calls.id', $evalCalls)->where('calls.status', 'evaluation')->where('applications.eligibility', 'enabled')->whereIn('offers.id', DB::table('assignments')->where('user_id', $u->id)->pluck('offer_id')));
            })->select('applications.id', 'applications.eligibility', 'offers.title', 'users.name')->orderBy('applications.id')->limit(50)->get();

        return compact('calls', 'apps', 'roles', 'tasks');
    }
}
