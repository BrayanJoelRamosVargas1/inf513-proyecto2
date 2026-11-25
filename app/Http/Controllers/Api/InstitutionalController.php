<?php

namespace App\Http\Controllers\Api;

use App\Domain\Access;
use App\Domain\DocumentSafety;
use App\Domain\InstitutionalPreparation;
use App\Domain\Selection;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

final class InstitutionalController extends Controller
{
    public function index(Request $r)
    {
        $ids = Access::effective(DB::table('memberships')->where('user_id', $r->user()->id)->where('role', 'authority'))->pluck('call_id');
        abort_unless($r->user()->active && $ids->isNotEmpty(), 403);
        $calls = DB::table('calls')->whereIn('id', $ids)->orderByDesc('id')->get(['id', 'title', 'mode', 'status']);
        foreach ($calls as $call) {
            $call->reviewers = Access::effective(DB::table('memberships')->join('users', 'users.id', '=', 'memberships.user_id')->where('call_id', $call->id)->where('memberships.role', 'authority')->where('users.active', true)->where('users.id', '!=', $r->user()->id))->get(['users.id', 'users.name']);
        }

        return response()->json(['calls' => $calls, 'gates' => InstitutionalPreparation::GATES, 'dossiers' => DB::table('institutional_dossiers')->whereIn('call_id', $ids)->where(fn ($q) => $q->where('created_by', $r->user()->id)->orWhere('reviewer_id', $r->user()->id))->orderByDesc('id')->get(), 'official_activation_allowed' => false]);
    }

    public function create(Request $r, InstitutionalPreparation $service)
    {
        $data = $r->validate(['call_id' => 'required|integer|min:1', 'reviewer_id' => 'required|integer|min:1', 'title' => 'required|string|max:150']);

        return response()->json(['id' => $service->create($r->user(), $data)], 201);
    }

    public function show(Request $r, int $id, InstitutionalPreparation $service)
    {
        $d = $service->dossier($r->user(), $id);
        $submissions = DB::table('institutional_submissions')->where('dossier_id', $id)->orderBy('id')->get();
        foreach ($submissions as $submission) {
            $submission->snapshot = Selection::json($submission->snapshot);
        }
        $reviews = DB::table('institutional_reviews')->whereIn('submission_id', $submissions->pluck('id'))->orderBy('id')->get();

        return response()->json(['dossier' => $d, 'readiness' => $service->readiness($id), 'submissions' => $submissions, 'reviews' => $reviews, 'can_edit' => $d->created_by === $r->user()->id && $d->status === 'draft', 'can_review' => $d->reviewer_id === $r->user()->id && $d->status === 'under_review']);
    }

    public function source(Request $r, int $id, InstitutionalPreparation $service)
    {
        $service->dossier($r->user(), $id);
        $data = $r->validate(['expected_version' => 'required|integer|min:1', 'reference' => 'required|string|max:100|regex:/^[A-Za-z0-9._-]+$/D', 'gate' => ['required', Rule::in(array_keys(InstitutionalPreparation::GATES))], 'title' => 'required|string|max:180', 'issued_on' => 'required|date_format:Y-m-d', 'pages' => 'required|string|max:80', 'classification' => ['required', Rule::in(['historical_reference', 'proposed_rule'])], 'notes' => 'required|string|max:5000', 'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:15360']);
        unset($data['file']);

        return response()->json(['id' => $service->source($r->user(), $id, $data, $r->file('file'))], 201);
    }

    public function submit(Request $r, int $id, InstitutionalPreparation $service)
    {
        $data = $r->validate(['expected_version' => 'required|integer|min:1']);

        return response()->json(['id' => $service->submit($r->user(), $id, $data['expected_version'])], 201);
    }

    public function review(Request $r, int $id, InstitutionalPreparation $service)
    {
        $data = $r->validate(['expected_version' => 'required|integer|min:1', 'decision' => ['required', Rule::in(['observed', 'reviewed'])], 'reason' => 'required|string|min:10|max:5000']);
        $service->review($r->user(), $id, $data);

        return response()->json(['recorded' => true, 'official_activation_allowed' => false]);
    }

    public function download(Request $r, int $id, InstitutionalPreparation $service)
    {
        $source = Selection::row('institutional_sources', $id);
        $service->dossier($r->user(), $source->dossier_id);
        $safety = app(DocumentSafety::class);
        $before = $safety->status($id);
        abort_unless($before['download_allowed'], 423, 'Fuente en cuarentena: el análisis falta, venció, falló o bloqueó el archivo.');
        $bytes = $safety->bytes($source);
        $after = $safety->status($id);
        abort_unless($after['download_allowed'] && $after['job_id'] === $before['job_id'], 423, 'El análisis cambió durante la lectura. Descarga bloqueada.');
        $ext = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$source->mime] ?? abort(415, 'Tipo de archivo no permitido.');

        // Return the bytes actually hashed, not a pathname that could change before transmission.
        return response($bytes, 200, ['Content-Disposition' => 'attachment; filename="fuente-'.$source->id.'-v'.$source->version.'.'.$ext.'"', 'Content-Type' => $source->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function activation(Request $r, int $id, InstitutionalPreparation $service)
    {
        $service->dossier($r->user(), $id);

        return response()->json(['official_activation_allowed' => false, 'decision' => 'NO_GO_INSTITUCIONAL', 'message' => 'La preparación y revisión técnica no habilitan concursos reales. Activación oficial no implementada; requiere validación e infraestructura institucional.'], 409);
    }
}
