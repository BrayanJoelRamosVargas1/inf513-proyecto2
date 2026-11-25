<?php

namespace App\Http\Controllers\Api;

use App\Domain\Access;
use App\Domain\Workspace;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\ReadModels\PortalScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class WorkspaceController extends Controller
{
    public function navigation(Request $r)
    {
        $effectiveRoles = Workspace::roles($r->user());
        $roles = array_unique(array_merge([$r->user()->role], $effectiveRoles));
        $items = DB::table('navigation_items')->where('active', true)->whereIn('id', DB::table('navigation_roles')->whereIn('role', $roles)->select('navigation_item_id'))
            ->when($r->user()->role !== 'student', fn ($q) => $q->where('path', '!=', '/simulacion'))
            ->when(config('portal.experience') !== 'training', fn ($q) => $q->where('path', '!=', '/simulacion'))
            ->when(! in_array('authority', $effectiveRoles), fn ($q) => $q->whereNotIn('path', ['/expedientes', '/institucional']))
            ->orderBy('position')->orderBy('id')->get(['id', 'label', 'path']);

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

    public function publicVisit(Request $r)
    {
        $d = $r->validate([
            'path' => ['required', Rule::in(['/ingresar', '/registro'])],
            'key' => ['required', 'uuid'],
        ]);
        $total = DB::transaction(function () use ($d) {
            $receipt = DB::table('public_visit_receipts')->where('key', $d['key'])->first();
            abort_if($receipt && $receipt->path !== $d['path'], 409, 'Este identificador corresponde a otra vista.');
            if (! $receipt) {
                $inserted = DB::table('public_visit_receipts')->insertOrIgnore([
                    'key' => $d['key'], 'path' => $d['path'], 'created_at' => now(),
                ]);
                abort_if(DB::table('public_visit_receipts')->where('key', $d['key'])->value('path') !== $d['path'], 409, 'Este identificador corresponde a otra vista.');
                if ($inserted) {
                    DB::table('page_visits')->insertOrIgnore(['path' => $d['path'], 'total' => 0]);
                    DB::table('page_visits')->where('path', $d['path'])->increment('total');
                }
            }

            return DB::table('page_visits')->where('path', $d['path'])->value('total');
        });

        return response()->json(['path' => $d['path'], 'total' => $total]);
    }

    public function visit(Request $r)
    {
        $d = $r->validate(['path' => ['required', 'string', 'max:160', 'regex:~^/(?:institucional(?:/[1-9][0-9]*)?|convocatorias(?:/nueva|/[1-9][0-9]*(?:/configuracion|/auditoria)?)?|postulaciones(?:/[1-9][0-9]*(?:/rectificaciones|/participacion)?)?|ofertas/[1-9][0-9]*/resultados|revision|evaluaciones|expedientes|estadisticas|simulacion|cuenta|buscar|administracion|adjudicaciones)?$~'], 'key' => 'required|uuid']);
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
        $visibleOffers = DB::table('offers')->whereIn('call_id', PortalScope::calls($r->user())->select('id'));
        if (! in_array($r->user()->role, ['student', 'admin'])) {
            $memberships = fn (array $roles) => Access::effective(DB::table('memberships')->where('user_id', $r->user()->id)->whereIn('role', $roles))->select('call_id');
            $visibleOffers->where(fn ($q) => $q->whereIn('call_id', $memberships(['authority', 'reviewer']))
                ->orWhere(fn ($s) => $s->whereIn('call_id', $memberships(['evaluator']))->whereIn('id', DB::table('assignments')->where('user_id', $r->user()->id)->select('offer_id')))
                ->orWhereIn('id', DB::table('applications')->where('user_id', $r->user()->id)->select('offer_id')));
        }
        $offers = (clone $visibleOffers)->where(fn ($q) => $q->where('title', 'ilike', $term)->orWhereIn('category_id', DB::table('categories')->where('name', 'ilike', $term)->select('id')))->orderBy('id')->paginate(10, ['id', 'title', 'call_id']);
        $types = DB::table('categories')->where('name', 'ilike', $term)
            ->whereIn('id', (clone $visibleOffers)->select('category_id'))->orderBy('id')->paginate(10, ['id', 'name']);
        $lastPage = max($items->lastPage(), $offers->lastPage(), $types->lastPage());
        abort_if($items->currentPage() > $lastPage, 404, 'La página solicitada no existe. Vuelve a la primera página de la búsqueda.');

        return response()->json(['calls' => $items, 'offers' => $offers->items(), 'types' => $types->items(),
            'pagination' => ['page' => $items->currentPage(), 'last_page' => $lastPage, 'total' => $items->total() + $offers->total() + $types->total()]]);
    }

    public function metrics(Request $r)
    {
        $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'call_id' => 'nullable|integer|min:1']);
        $visibleCalls = PortalScope::calls($r->user());
        if ($r->filled('call_id')) {
            abort_unless((clone $visibleCalls)->where('id', $r->integer('call_id'))->exists(), 403);
        }
        $apps = PortalScope::applications($r->user())->when($r->filled('call_id'), fn ($q) => $q->where('offers.call_id', $r->integer('call_id')))
            ->when($r->filled('from'), fn ($q) => $q->where('applications.created_at', '>=', $r->date('from')->startOfDay()))
            ->when($r->filled('to'), fn ($q) => $q->where('applications.created_at', '<=', $r->date('to')->endOfDay()));
        $groups = (clone $apps)->select('applications.eligibility')->selectRaw('count(*) as total')->groupBy('applications.eligibility')->get();
        $ids = (clone $apps)->select('applications.id');
        $authorityCalls = Access::effective(DB::table('memberships')->where('user_id', $r->user()->id)->where('role', 'authority'))->select('call_id');
        $evaluatorCalls = Access::effective(DB::table('memberships')->where('user_id', $r->user()->id)->where('role', 'evaluator'))->select('call_id');
        $exams = DB::table('grades')->join('applications as a', 'a.id', '=', 'grades.application_id')->join('offers as o', 'o.id', '=', 'a.offer_id')
            ->whereIn('grades.application_id', clone $ids)->whereIn('grades.status', ['confirmed', 'corrected'])
            ->where(fn ($q) => $q->where('a.user_id', $r->user()->id)->orWhereIn('o.call_id', $authorityCalls)->orWhere(fn ($q) => $q->whereIn('o.call_id', $evaluatorCalls)->whereIn('grades.assignment_id', DB::table('assignments')->where('user_id', $r->user()->id)->select('id'))))->count();
        $traffic = DB::table('access_events')->when($r->user()->role !== 'admin', fn ($q) => $q->where('user_id', $r->user()->id))
            ->when($r->filled('from'), fn ($q) => $q->where('created_at', '>=', $r->date('from')->startOfDay()))
            ->when($r->filled('to'), fn ($q) => $q->where('created_at', '<=', $r->date('to')->endOfDay()));
        $popular = (clone $traffic)->where('event', 'page.view')->select('resource')->selectRaw('count(*) as total')->groupBy('resource')->orderByDesc('total')->limit(10)->get();
        $series = (clone $traffic)->selectRaw("to_char(created_at AT TIME ZONE 'America/La_Paz', 'YYYY-MM-DD') as day, count(*) as total")->groupBy('day')->orderByDesc('day')->limit(90)->get()->sortBy('day')->values();

        return response()->json(['applications' => (clone $apps)->count(), 'eligibility' => $groups,
            'calls' => (clone $visibleCalls)->when($r->filled('call_id'), fn ($q) => $q->where('id', $r->integer('call_id')))->count(),
            'documents' => DB::table('documents')->whereIn('application_id', clone $ids)->count(), 'confirmed_exams' => $exams,
            'active_awards' => (clone $apps)->where('applications.selection', 'selected')->where('applications.participation', 'active')->count(),
            'registered_users' => $r->user()->role === 'admin' ? User::count() : null,
            'call_options' => (clone $visibleCalls)->orderByDesc('id')->get(['id', 'title']), 'series' => $series,
            'traffic_scope' => $r->user()->role === 'admin' ? 'Instalación completa' : 'Mi actividad', 'popular' => $popular,
            'login_success' => (clone $traffic)->where('event', 'login.success')->count(), 'login_failed' => (clone $traffic)->where('event', 'login.failed')->count()]);
    }
}
