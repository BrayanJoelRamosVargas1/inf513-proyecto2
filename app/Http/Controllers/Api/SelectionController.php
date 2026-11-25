<?php

namespace App\Http\Controllers\Api;

use App\Domain\Access;
use App\Domain\ApplicantDocuments;
use App\Domain\Selection;
use App\Domain\Workspace;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalController;
use App\ReadModels\PortalScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class SelectionController extends Controller
{
    public function calls(Request $r)
    {
        return response()->json(['calls' => PortalScope::calls($r->user())->orderByDesc('id')->get()]);
    }

    public function call(Request $r, int $id)
    {
        $call = PortalScope::calls($r->user())->where('id', $id)->first() ?? abort(404);
        $authority = Access::has($r->user(), $id, ['authority']);
        $reviewer = Access::has($r->user(), $id, ['reviewer']);
        $evaluator = Access::has($r->user(), $id, ['evaluator']);
        $offers = DB::table('offers')->where('call_id', $id)->when($evaluator && ! $authority && ! $reviewer && $r->user()->role !== 'student', fn ($q) => $q->whereIn('id', DB::table('assignments')->where('user_id', $r->user()->id)->select('offer_id')))->get();
        $own = DB::table('applications')->where('user_id', $r->user()->id)->whereIn('offer_id', $offers->pluck('id'))->get()->keyBy('offer_id');
        $subjects = $own->whereIn('offer_id', $offers->where('is_subject', true)->pluck('id'))->count();
        foreach ($offers as $offer) {
            foreach (['documents', 'requirements', 'weights'] as $key) {
                $offer->$key = Selection::json($offer->$key);
            }
            $offer->own_application = $own->get($offer->id)?->id;
            $offer->application_block = Workspace::applicationBlock($r->user(), $call, $offer, $subjects);
        }

        return response()->json(['call' => $call, 'documentary' => app(\App\Domain\InstitutionalCatalogue::class)->forCall($id), 'offers' => $offers, 'authority' => $authority, 'reviewer' => $reviewer, 'evaluator' => $evaluator, 'applications' => PortalScope::applications($r->user())->where('offers.call_id', $id)->orderBy('applications.id')->get(['applications.id', 'applications.status', 'applications.eligibility', 'applications.participation', 'offers.title', 'users.name'])]);
    }

    public function applications(Request $r)
    {
        $r->validate(['workspace' => ['nullable', Rule::in(['own', 'reviewer', 'evaluator', 'authority'])]]);
        $workspace = $r->input('workspace');
        $apps = PortalScope::applications($r->user());
        if ($workspace === 'own') {
            $apps->where('applications.user_id', $r->user()->id);
        } elseif ($workspace) {
            $calls = Access::effective(DB::table('memberships')->where('user_id', $r->user()->id)->where('role', $workspace))->pluck('call_id');
            abort_if($calls->isEmpty(), 403, 'No tiene esta responsabilidad vigente.');
            $apps->whereIn('offers.call_id', $calls)->where('applications.user_id', '!=', $r->user()->id);
            if ($workspace === 'evaluator') {
                $apps->whereIn('offers.id', DB::table('assignments')->where('user_id', $r->user()->id)->select('offer_id'));
            }
        }
        $items = $apps->join('calls', 'calls.id', '=', 'offers.call_id')->orderByDesc('applications.id')->get([
            'applications.id', 'applications.offer_id', 'applications.user_id', 'applications.status', 'applications.eligibility', 'applications.participation',
            'offers.title', 'offers.call_id', 'users.name', 'calls.status as call_status', 'calls.title as call_title',
        ]);
        $assignments = $workspace === 'evaluator' ? DB::table('assignments')->where('user_id', $r->user()->id)->get() : collect();
        $grades = $workspace === 'evaluator' ? DB::table('grades')->whereIn('application_id', $items->pluck('id'))->whereIn('status', ['confirmed', 'corrected'])->get() : collect();
        foreach ($items as $item) {
            $mine = $assignments->where('offer_id', $item->offer_id);
            $item->assigned_areas = $mine->pluck('area')->values();
            $confirmed = $grades->where('application_id', $item->id)->pluck('assignment_id')->all();
            $item->pending_areas = $mine->whereNotIn('id', $confirmed)->pluck('area')->values();
        }

        return response()->json(['applications' => $items, 'workspace' => $workspace]);
    }

    public function apply(Request $r, int $id, Selection $s)
    {
        return response()->json(['id' => $s->apply($r->user(), $id)], 201);
    }

    public function application(Request $r, int $id)
    {
        // Reuse the existing read policy and sealed-document selection; serialize an explicit allow-list.
        $v = app(PortalController::class)->application($r, $id)->getData();
        $app = $v['app'];
        $data = collect((array) $app)->only(['id', 'offer_id', 'user_id', 'status', 'eligibility', 'participation', 'selection', 'dossier_version']);
        if ($v['owner'] || $v['authority']) {
            $data = $data->merge(collect((array) $app)->only(['merit', 'exam', 'final']));
        }
        if ($v['owner'] || $v['authority'] || $v['reviewer']) {
            $data->put('eligibility_reason', $app->eligibility_reason);
        }
        if ($v['reviewer']) {
            $data->put('merit', $app->merit);
        }
        $offer = $v['offer'];
        $criteria = array_merge(array_combine(array_map(fn ($key) => 'doc:'.$key, array_keys(Selection::json($offer->documents))), array_values(Selection::json($offer->documents))), Selection::json($offer->requirements));
        $active = $app->participation === 'active' && $v['call']->mode === 'DEMO';
        $frozen = DB::table('publications')->where('offer_id', $offer->id)->exists() || DB::table('tie_exams')->where('offer_id', $offer->id)->exists();
        $confirmedAreas = DB::table('grades')->join('assignments', 'assignments.id', '=', 'grades.assignment_id')
            ->where('grades.application_id', $id)->whereIn('grades.status', ['confirmed', 'corrected'])->pluck('assignments.area')->all();
        $allConfirmed = ! array_diff(array_keys(Selection::json($offer->weights)), $confirmedAreas);
        $hasEditableArea = $v['assignments']->contains(fn ($assignment) => ! $v['grades']->contains(fn ($grade) => $grade->assignment_id === $assignment->id && $grade->status !== 'draft'));
        $canCorrect = $v['reviewer'] || $v['authority'] || (Access::has($r->user(), $v['call']->id, ['evaluator']) && $v['grades']->isNotEmpty());
        $canReview = $active && $v['reviewer'] && ! $v['owner'] && ! $v['hasAmendmentHistory'] && $app->status === 'submitted' && $app->eligibility === 'pending' && in_array($v['call']->status, ['published', 'closed']);
        $allCriteriaMet = collect(array_keys($criteria))->every(fn ($key) => Selection::criterionSatisfied($offer, $key, $v['reviews']->get($key)?->decision));
        $cpd = null;
        if ($v['owner'] || $v['reviewer']) {
            $catalogue = app(\App\Domain\InstitutionalCatalogue::class);
            $modality = $catalogue->modality($offer);
            $cpd = ['modality' => $modality, 'definition' => $catalogue->definitions()['profiles']['convocatoria-01']['modalities'][$modality],
                'records' => DB::table('institutional_register')->where('call_id', $v['call']->id)->where('kind', 'cpd')->whereRaw("payload->>'application_id' = ?", [(string) $id])->orderByDesc('id')->get()->map(fn ($row) => ['id' => $row->id, 'reference' => $row->reference, 'version' => $row->version, 'recorded_by' => $row->recorded_by, 'created_at' => $row->created_at, 'payload' => Selection::json($row->payload)])];
        }

        return response()->json(['cpd_context' => $cpd, 'application' => $data, 'student' => ['name' => $v['student']->name], 'offer' => ['id' => $offer->id, 'title' => $offer->title, 'documents' => Selection::json($offer->documents), 'weights' => Selection::json($offer->weights)], 'call' => collect((array) $v['call'])->only(['id', 'title', 'status', 'mode']),
            'owner' => $v['owner'], 'reviewer' => $v['reviewer'], 'authority' => $v['authority'], 'conditional_criteria' => $v['owner'] || $v['reviewer'] || $v['authority'] ? array_values(array_filter(array_keys($criteria), fn ($key) => Selection::conditionalCriterion($offer, $key))) : [], 'criteria' => $v['owner'] || $v['reviewer'] || $v['authority'] ? $criteria : [],
            'documents' => $v['effectiveDocuments']->map(fn ($doc) => collect((array) $doc)->only(['id', 'kind', 'name', 'mime', 'version', 'created_at'])->put('security', app(ApplicantDocuments::class)->status($doc->id)))->values(),
            'reviews' => $v['reviews']->values(), 'grades' => $v['grades'], 'assignments' => $v['assignments'],
            'can_view_corrections' => $canCorrect,
            'can_enable' => $canReview && $v['documentValidation']['ready'] && $allCriteriaMet,
            'document_validation' => $v['documentValidation'],
            'can_upload' => $active && $v['owner'] && $app->status === 'draft' && Workspace::receiving($v['call']),
            'can_review' => $canReview,
            'can_merit' => $v['documentValidation']['ready'] && $active && $v['reviewer'] && ! $v['owner'] && ! $v['pendingAmendment'] && $app->status === 'submitted' && $app->merit === null && in_array($v['call']->status, ['published', 'closed', 'evaluation']),
            'can_grade' => $v['documentValidation']['ready'] && $active && $hasEditableArea && ! $v['owner'] && $app->eligibility === 'enabled' && $v['call']->status === 'evaluation' && ! $frozen,
            'can_calculate' => $v['documentValidation']['ready'] && $active && $v['authority'] && $app->eligibility === 'enabled' && $v['call']->status === 'evaluation' && $app->merit !== null && $allConfirmed && in_array($offer->scheme, ['DEMO_AREAS_100', 'DEMO_COMPONENTS_100']) && $offer->rounding === 'HALF_UP_FINAL' && ! $frozen]);
    }

    public function action(Request $r, int $id, string $action)
    {
        app(PortalController::class)->action($r, $id, $action);

        return response()->json(['message' => 'Operación registrada correctamente.']);
    }

    public function transition(Request $r, int $id, Selection $s)
    {
        $d = $r->validate(['status' => 'required|string']);
        $s->transition($r->user(), $id, $d['status']);

        return response()->json(['message' => 'Etapa actualizada.']);
    }
}
