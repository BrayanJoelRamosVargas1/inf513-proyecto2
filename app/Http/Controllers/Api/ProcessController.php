<?php

namespace App\Http\Controllers\Api;

use App\Domain\Access;
use App\Domain\ApplicantDocuments;
use App\Domain\Selection;
use App\Http\Controllers\Controller;
use App\Http\Controllers\GovernanceController;
use App\Http\Controllers\ParticipationController;
use App\Http\Controllers\PortalController;
use App\Models\User;
use App\Http\Operations\AdministrationOperations;
use App\Http\Operations\CallOperations;
use App\Http\Operations\ParticipationOperations;
use App\Http\Operations\ResultsOperations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Transitional HTTP adapters: all academic mutations still execute the existing validated domain services. */
final class ProcessController extends Controller
{
    public function management(Request $r)
    {
        abort_unless($r->user()->role === 'admin', 403);

        return response()->json(['users' => User::orderBy('id')->get()->map(fn ($u) => $u->only(['id', 'name', 'email', 'role', 'faculty', 'career', 'active', 'profile_version'])), 'calls' => DB::table('calls')->orderByDesc('id')->get(['id', 'title']), 'memberships' => DB::table('memberships')->orderBy('id')->get(), 'categories' => DB::table('categories')->get()]);
    }

    public function configuration(Request $r, int $id)
    {
        Access::require($r->user(), $id, ['authority']);

        return response()->json(['call' => Selection::row('calls', $id), 'categories' => DB::table('categories')->where('active', true)->get(), 'teachers' => User::whereIn('id', Access::effective(DB::table('memberships')->where('call_id', $id)->where('role', 'evaluator'))->select('user_id'))->where('active', true)->get()->map(fn ($u) => $u->only(['id', 'name'])), 'offers' => DB::table('offers')->where('call_id', $id)->get(), 'assignments' => DB::table('assignments')->whereIn('offer_id', DB::table('offers')->where('call_id', $id)->select('id'))->get()]);
    }

    public function ranking(Request $r, int $id)
    {
        $v = app(PortalController::class)->results($r, $id)->getData();
        if ($v['ranking']) {
            $v['ranking']['rank'] = collect($v['ranking']['rank'])->map(fn ($a) => collect((array) $a)->only(['id', 'name', 'merit', 'exam', 'final', 'selection']))->values();
        }

        return response()->json($v);
    }

    public function corrections(Request $r, int $id)
    {
        $v = app(GovernanceController::class)->corrections($r, $id)->getData();
        $v['app'] = collect((array) $v['app'])->only(['id', 'user_id', 'merit', 'participation']);
        if (! $v['authority'] && ! $v['reviewer']) {
            $v['app']->forget('merit');
        }
        $v['requests'] = $v['requests']->values();
        $v['grades'] = $v['grades']->values();
        $v['versions'] = $v['versions']->values();

        return response()->json($v);
    }

    public function participation(Request $r, int $id)
    {
        $v = app(ParticipationController::class)->show($r, $id)->getData();
        $v['app'] = collect((array) $v['app'])->only(['id', 'user_id', 'status', 'participation', 'dossier_version', 'eligibility']);
        // A participant needs their award reference, never the full ranking snapshot.
        $v['award'] = $v['award'] ? collect((array) $v['award'])->only(['id', 'version', 'created_at']) : null;
        $v['documents'] = ($v['owner'] || $v['reviewer']) ? $v['documents']->map(fn ($d) => collect((array) $d)->only(['id', 'name', 'kind', 'version', 'amendment_id', 'created_at'])->put('security', app(ApplicantDocuments::class)->status($d->id))) : [];
        $v['snapshots'] = $v['snapshots']->map(function ($snapshot) {
            $snapshot->snapshot = ['documents' => collect(Selection::json($snapshot->snapshot)['documents'])->map(fn ($d) => collect((array) $d)->only(['id', 'kind', 'version']))->values()];

            return $snapshot;
        });

        return response()->json($v);
    }

    public function awards(Request $r)
    {
        $v = app(ParticipationController::class)->awards($r)->getData();
        $v['awards'] = $v['awards']->map(function ($a) {
            $call = Selection::row('calls', $a->call_id);
            $a->publication = collect((array) $a->publication)->only(['id', 'version', 'created_at']);

            return collect((array) $a)->only(['id', 'user_id', 'name', 'title', 'call_id', 'participation', 'selection', 'publication', 'choice', 'choices'])->put('can_choose', $call->mode === 'DEMO' && in_array($call->status, ['results', 'evaluation']));
        })->values();
        unset($v['groups']);

        return response()->json($v);
    }

    public function audit(Request $r, int $id)
    {
        Access::require($r->user(), $id, ['authority']);

        return response()->json(['events' => DB::table('audit_events')->where('call_id', $id)->orderByDesc('id')->paginate(40)]);
    }

    /**
     * Operación => [clase, método, requiere {id}, acción, estado HTTP si tiene éxito].
     * Cada clase valida la petición y ejecuta la regla de dominio; aquí solo se arma la respuesta JSON.
     */
    private const OPERATIONS = [
        'create-call' => [CallOperations::class, 'createCall', false, null, 201],
        'edit-call' => [CallOperations::class, 'editCall', true, null, 200],
        'offer' => [CallOperations::class, 'saveOffer', true, null, 201],
        'assign' => [CallOperations::class, 'assign', true, null, 200],
        'user' => [AdministrationOperations::class, 'saveUser', false, null, 201],
        'permission' => [AdministrationOperations::class, 'membership', false, null, 201],
        'category' => [AdministrationOperations::class, 'category', false, null, 201],
        'edit-category' => [AdministrationOperations::class, 'editCategory', true, null, 200],
        'profile' => [AdministrationOperations::class, 'profile', true, null, 200],
        'revoke' => [AdministrationOperations::class, 'revoke', true, null, 200],
        'reassign' => [AdministrationOperations::class, 'reassign', true, null, 200],
        'correction' => [ResultsOperations::class, 'requestCorrection', true, null, 201],
        'decide-correction' => [ResultsOperations::class, 'decideCorrection', true, null, 200],
        'tie' => [ResultsOperations::class, 'openTie', true, null, 200],
        'resolve-tie' => [ResultsOperations::class, 'resolveTie', true, null, 200],
        'continue-tie' => [ResultsOperations::class, 'continueTie', true, null, 200],
        'approve' => [ResultsOperations::class, 'approve', true, null, 200],
        'open-amendment' => [ParticipationOperations::class, 'open', true, null, 201],
        'amendment-upload' => [ParticipationOperations::class, 'amendment', true, 'archivo', 200],
        'amendment-submit' => [ParticipationOperations::class, 'amendment', true, 'enviar', 200],
        'amendment-review' => [ParticipationOperations::class, 'amendment', true, 'revisar', 200],
        'amendment-expire' => [ParticipationOperations::class, 'amendment', true, 'vencer', 200],
        'participation-request' => [ParticipationOperations::class, 'request', true, null, 201],
        'participation-decision' => [ParticipationOperations::class, 'decide', true, null, 200],
        'choice' => [ParticipationOperations::class, 'choose', true, null, 200],
    ];

    public function mutate(Request $r)
    {
        $operation = (string) $r->route('operation');
        [$class, $method, $needsId, $action, $status] = self::OPERATIONS[$operation] ?? abort(404);
        $arguments = [$r];
        if ($needsId) {
            $arguments[] = $r->route('id') ?? abort(404);
        }
        if ($action !== null) {
            $arguments[] = $action;
        }
        $result = app($class)->{$method}(...$arguments);
        $data = ['message' => 'Operación registrada con los controles del dominio.'];
        if ($operation === 'create-call') {
            $data['id'] = $result;
        }

        return response()->json($data, $status);
    }
}
