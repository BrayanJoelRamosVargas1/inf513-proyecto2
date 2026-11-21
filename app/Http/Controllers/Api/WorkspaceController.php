<?php

namespace App\Http\Controllers\Api;

use App\Domain\Access;
use App\Domain\Workspace;
use App\Http\Controllers\Controller;
use App\ReadModels\PortalScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class WorkspaceController extends Controller
{
    public function navigation(Request $r)
    {
        $roles = array_unique(array_merge([$r->user()->role], Workspace::roles($r->user())));
        $items = DB::table('navigation_items')->where('active', true)->whereIn('id', DB::table('navigation_roles')->whereIn('role', $roles)->select('navigation_item_id'))->orderBy('position')->get(['id', 'label', 'path']);

        return response()->json(['items' => $items]);
    }

    public function preferences(Request $r)
    {
        if ($r->isMethod('put')) {
            $d = $r->validate(['theme' => ['required', Rule::in(['ninos', 'jovenes', 'adultos'])], 'mode' => ['required', Rule::in(['dia', 'noche', 'auto'])], 'font' => 'required|integer|in:100,125,150,175,200', 'contrast' => 'required|boolean']);
            DB::table('portal_preferences')->updateOrInsert(['user_id' => $r->user()->id], $d);
        }

        return response()->json(['preferences' => DB::table('portal_preferences')->where('user_id', $r->user()->id)->first() ?? ['theme' => 'adultos', 'mode' => 'auto', 'font' => 100, 'contrast' => false]]);
    }

    public function visit(Request $r)
    {
        $d = $r->validate(['path' => ['required', 'string', 'max:160', 'regex:~^/(?:convocatorias(?:/nueva|/[1-9][0-9]*(?:/configuracion)?)?|postulaciones(?:/[1-9][0-9]*(?:/rectificaciones|/participacion)?)?|ofertas/[1-9][0-9]*/resultados|estadisticas|simulacion|cuenta|buscar|administracion|adjudicaciones)?$~'], 'key' => 'required|uuid']);
        $result = Access::transaction($r->user(), function () use ($r, $d) {
            $existing = DB::table('visit_receipts')->where('user_id', $r->user()->id)->where('key', $d['key'])->first();
            abort_if($existing && $existing->path !== $d['path'], 409, 'El identificador de visita ya corresponde a otra página.');
            if (! $existing) {
                DB::table('visit_receipts')->insert(['user_id' => $r->user()->id] + $d);
                DB::table('page_visits')->insertOrIgnore(['path' => $d['path'], 'total' => 0]);
                DB::table('page_visits')->where('path', $d['path'])->increment('total');
                SessionController::event($r, 'page.view', 200, $d['path']);
            }

            return DB::table('page_visits')->where('path', $d['path'])->value('total');
        });

        return response()->json(['path' => $d['path'], 'total' => $result]);
    }

    public function search(Request $r)
    {
        $d = $r->validate(['q' => 'required|string|min:2|max:80', 'page' => 'nullable|integer|min:1|max:1000']);
        $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $d['q']).'%';
        $calls = PortalScope::calls($r->user());
        $items = $calls->where('title', 'ilike', $term)->orderByDesc('id')->paginate(10, ['id', 'title', 'status']);
        $offers = DB::table('offers')->whereIn('call_id', PortalScope::calls($r->user())->select('id'))->where('title', 'ilike', $term)
            ->when($r->user()->role === 'staff' && ! in_array('reviewer', Workspace::roles($r->user())) && ! in_array('authority', Workspace::roles($r->user())), fn ($q) => $q->whereIn('id', DB::table('assignments')->where('user_id', $r->user()->id)->select('offer_id')))
            ->orderBy('id')->limit(20)->get(['id', 'title', 'call_id']);

        return response()->json(['calls' => $items, 'offers' => $offers]);
    }

    public function metrics(Request $r)
    {
        $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'call_id' => 'nullable|integer|min:1']);
        $apps = PortalScope::applications($r->user())->when($r->filled('call_id'), fn ($q) => $q->where('offers.call_id', $r->integer('call_id')));
        $groups = (clone $apps)->select('applications.eligibility')->selectRaw('count(*) as total')->groupBy('applications.eligibility')->get();
        $traffic = DB::table('access_events')->when($r->user()->role !== 'admin', fn ($q) => $q->where('user_id', $r->user()->id))
            ->when($r->filled('from'), fn ($q) => $q->where('created_at', '>=', $r->date('from')->startOfDay()))
            ->when($r->filled('to'), fn ($q) => $q->where('created_at', '<=', $r->date('to')->endOfDay()));
        $popular = (clone $traffic)->where('event', 'page.view')->select('resource')->selectRaw('count(*) as total')->groupBy('resource')->orderByDesc('total')->limit(10)->get();

        return response()->json(['applications' => (clone $apps)->count(), 'eligibility' => $groups, 'traffic_scope' => $r->user()->role === 'admin' ? 'Instalación completa' : 'Mi actividad', 'popular' => $popular, 'login_success' => (clone $traffic)->where('event', 'login.success')->count(), 'login_failed' => (clone $traffic)->where('event', 'login.failed')->count()]);
    }
}
