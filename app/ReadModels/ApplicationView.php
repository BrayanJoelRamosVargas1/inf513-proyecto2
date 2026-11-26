<?php

namespace App\ReadModels;

use App\Domain\Access;
use App\Domain\ApplicantDocuments;
use App\Domain\Selection;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Expediente de una postulación con lo que cada perfil puede ver.
 * Compartido por la vista Blade y por la API: ninguna depende de la otra.
 */
final class ApplicationView
{
    public function data(Request $r, int $id): array
    {
        $app = Selection::row('applications', $id);
        Access::viewApplication($r->user(), $app);
        $offer = Selection::row('offers', $app->offer_id);
        $call = Selection::row('calls', $offer->call_id);
        $u = $r->user();
        $owner = $app->user_id === $u->id;
        $reviewer = Access::has($u, $call->id, ['reviewer']);
        $authority = Access::has($u, $call->id, ['authority']);
        $student = User::findOrFail($app->user_id);
        $documents = ($owner || $reviewer) ? DB::table('documents')->where('application_id', $id)->orderByDesc('version')->get() : collect();
        $reviews = ($owner || $reviewer || $authority) ? DB::table('reviews')->where('application_id', $id)->get()->keyBy('criterion') : collect();
        $assignments = Access::has($u, $call->id, ['evaluator']) ? DB::table('assignments')->where('offer_id', $offer->id)->where('user_id', $u->id)->get() : collect();
        // El postulante conoce sus notas solo cuando la autoridad aprobó la publicación de la oferta, y sin saber qué evaluador las puso.
        $published = DB::table('publications')->where('offer_id', $offer->id)->exists();
        $grades = DB::table('grades')->join('assignments', 'assignments.id', '=', 'grades.assignment_id')->where('grades.application_id', $id)
            ->when(! $owner && ! $authority, fn ($q) => $q->whereIn('grades.assignment_id', $assignments->pluck('id')))
            ->when($owner && ! $authority && ! $reviewer, fn ($q) => $published ? $q->whereIn('grades.status', ['confirmed', 'corrected']) : $q->whereRaw('false'))
            ->select('grades.*', 'assignments.area')->get();
        if ($owner && ! $authority) {
            $grades = $grades->map(function ($grade) {
                unset($grade->user_id);

                return $grade;
            });
        }

        $hasAmendmentHistory = DB::table('amendments')->where('application_id', $id)->exists();
        $pendingAmendment = DB::table('amendments')->where('application_id', $id)->whereIn('status', ['open', 'submitted'])->exists();
        $currentSnapshot = DB::table('dossier_snapshots')->where('application_id', $id)->orderByDesc('id')->first();
        $effectiveDocuments = $currentSnapshot ? $documents->whereIn('id', array_column(Selection::json($currentSnapshot->snapshot)['documents'], 'id')) : $documents->whereNull('amendment_id');

        $documentValidation = app(ApplicantDocuments::class)->readiness($id);

        return compact('published', 'documentValidation', 'hasAmendmentHistory', 'pendingAmendment', 'effectiveDocuments', 'app', 'offer', 'call', 'student', 'documents', 'reviews', 'assignments', 'grades', 'owner', 'reviewer', 'authority');
    }
}
