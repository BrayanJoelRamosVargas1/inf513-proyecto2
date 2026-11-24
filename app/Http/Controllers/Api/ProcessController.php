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

    public function mutate(Request $r)
    {
        $operation = $r->route('operation');
        $id = $r->route('id');
        $operations = [
            'create-call' => [PortalController::class, 'createCall'], 'edit-call' => [PortalController::class, 'editCall'], 'offer' => [PortalController::class, 'saveOffer'],
            'user' => [PortalController::class, 'saveUser'], 'permission' => [PortalController::class, 'membership'], 'category' => [PortalController::class, 'category'], 'edit-category' => [PortalController::class, 'editCategory'],
            'profile' => [GovernanceController::class, 'profile'], 'revoke' => [GovernanceController::class, 'revoke'], 'reassign' => [GovernanceController::class, 'reassign'], 'assign' => [PortalController::class, 'assign'],
            'correction' => [GovernanceController::class, 'requestCorrection'], 'decide-correction' => [GovernanceController::class, 'decide'],
            'tie' => [PortalController::class, 'openTie'], 'resolve-tie' => [PortalController::class, 'resolveTie'], 'continue-tie' => [GovernanceController::class, 'continueTie'], 'approve' => [PortalController::class, 'approve'],
            'open-amendment' => [ParticipationController::class, 'open'], 'amendment-upload' => [ParticipationController::class, 'amendment', 'archivo'], 'amendment-submit' => [ParticipationController::class, 'amendment', 'enviar'], 'amendment-review' => [ParticipationController::class, 'amendment', 'revisar'], 'amendment-expire' => [ParticipationController::class, 'amendment', 'vencer'],
            'participation-request' => [ParticipationController::class, 'request'], 'participation-decision' => [ParticipationController::class, 'decide'], 'choice' => [ParticipationController::class, 'choose'],
        ];
        $target = $operations[$operation] ?? abort(404);
        $parameters = ['r' => $r];
        $method = new \ReflectionMethod($target[0], $target[1]);
        foreach ($method->getParameters() as $parameter) {
            if ($parameter->getName() === 'id') {
                $parameters['id'] = $id ?? abort(404);
            }
            if ($parameter->getName() === 'action') {
                $parameters['action'] = $target[2];
            }
        }
        $response = app()->call([app($target[0]), $target[1]], $parameters);
        $data = ['message' => 'Operación registrada con los controles del dominio.'];
        if ($operation === 'create-call' && preg_match('~/convocatorias/([0-9]+)~', $response->getTargetUrl(), $match)) {
            $data['id'] = (int) $match[1];
        }

        return response()->json($data, in_array($operation, ['create-call', 'user', 'permission', 'category', 'offer', 'correction', 'open-amendment', 'participation-request']) ? 201 : 200);
    }
}
