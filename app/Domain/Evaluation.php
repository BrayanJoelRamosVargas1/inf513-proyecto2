<?php

namespace App\Domain;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Evaluación: asignación de responsables, notas, rectificación de nota y cálculo del resultado. Reglas y bloqueos idénticos a los de Selection, que expone la misma API. */
final class Evaluation
{
    public function assign(User $u, int $offerId, int $teacher, string $area): void
    {
        Access::transaction($u, function () use ($u, $offerId, $teacher, $area) {
            $o = Selection::row('offers', $offerId);
            $c = Selection::row('calls', $o->call_id, true);
            $o = Selection::row('offers', $offerId, true);
            Access::require($u, $c->id, ['authority']);
            Selection::demo($c);
            if (! in_array($c->status, ['draft', 'published', 'closed', 'evaluation'])) {
                Scores::fail('La evaluación está cerrada.');
            }
            if (! array_key_exists($area, Selection::json($o->weights))) {
                Scores::fail('Área ajena al esquema.');
            }
            Access::require(User::findOrFail($teacher), $c->id, ['evaluator']);
            if (DB::table('assignments')->where('offer_id', $offerId)->where('area', $area)->exists()) {
                Scores::fail('Ya existe responsable. La reasignación requiere procedimiento documentado; no se promedian docentes automáticamente.');
            }
            $id = DB::table('assignments')->insertGetId(['offer_id' => $offerId, 'user_id' => $teacher, 'area' => $area, 'created_at' => now(), 'updated_at' => now()]);
            Selection::audit($u, $c->id, 'evaluator.assigned', 'assignment:'.$id, null, ['user_id' => $teacher, 'area' => $area]);
        });
    }

    public function grade(User $u, int $appId, int $assignmentId, string $score, bool $confirmed = true): void
    {
        Access::transaction($u, function () use ($u, $appId, $assignmentId, $score, $confirmed) {
            [$a,$o,$c] = Selection::lockedApplication($appId);
            $as = Selection::row('assignments', $assignmentId);
            Access::require($u, $c->id, ['evaluator']);
            abort_if($a->user_id === $u->id, 403, 'No puede calificar su propia postulación.');
            Selection::demo($c);
            abort_unless($as->user_id === $u->id && $as->offer_id === $o->id, 403);
            if ($a->eligibility !== 'enabled' || $c->status !== 'evaluation') {
                Scores::fail('Solo habilitados en evaluación abierta.');
            }
            if (DB::table('publications')->where('offer_id', $o->id)->exists() || DB::table('tie_exams')->where('offer_id', $o->id)->exists()) {
                Scores::fail('La evaluación está congelada por publicación o desempate.');
            }
            $before = DB::table('grades')->where('application_id', $appId)->where('assignment_id', $assignmentId)->first();
            if ($before && $before->status !== 'draft') {
                Scores::fail('Nota confirmada: use rectificación autorizada.');
            }
            $documentChecks = app(ApplicantDocuments::class)->assertEffective($appId);
            $score = Scores::value($score);
            $data = ['user_id' => $u->id, 'score' => $score, 'status' => $confirmed ? 'confirmed' : 'draft', 'updated_at' => now()];
            if ($before) {
                $id = $before->id;
                DB::table('grades')->where('id', $id)->update($data);
            } else {
                $id = DB::table('grades')->insertGetId($data + ['application_id' => $appId, 'assignment_id' => $assignmentId, 'user_id' => $u->id, 'created_at' => now()]);
            }
            if ($confirmed) {
                Rectifications::version($appId, $id, 1, $score, $u->id);
            }
            Selection::audit($u, $c->id, $confirmed ? 'grade.confirmed' : 'grade.draft_saved', 'grade:'.$id, $before ? ['score' => $before->score, 'status' => $before->status] : null, $data + ['document_checks' => $documentChecks]);
        });
    }

    public function correct(User $u, int $gradeId, string $score, string $reason): void
    {
        Access::transaction($u, function () use ($u, $gradeId, $score, $reason) {
            $g = Selection::row('grades', $gradeId);
            [$a,$o,$c] = Selection::lockedApplication($g->application_id);
            $g = Selection::row('grades', $gradeId, true);
            Access::require($u, $c->id, ['authority']);
            Selection::demo($c);
            if ($g->status === 'draft') {
                Scores::fail('El borrador solo puede completarlo su evaluador asignado.');
            }
            if ($c->status !== 'evaluation' || DB::table('tie_exams')->where('offer_id', $o->id)->exists() || DB::table('publications')->where('offer_id', $o->id)->exists()) {
                Scores::fail('Hay resultados o desempates iniciados: requiere nueva versión del procedimiento.');
            }
            if (mb_strlen(trim($reason)) < 10) {
                Scores::fail('Explique la autorización y motivo de rectificación.');
            }
            $documentChecks = app(ApplicantDocuments::class)->assertEffective($a->id);
            $score = Scores::value($score);
            DB::table('grades')->where('id', $gradeId)->update(['score' => $score, 'status' => 'corrected', 'version' => $g->version + 1, 'updated_at' => now()]);
            DB::table('applications')->where('id', $a->id)->update(['exam' => null, 'final' => null, 'updated_at' => now()]);
            Rectifications::version($a->id, $gradeId, $g->version + 1, $score, $u->id);
            Selection::audit($u, $c->id, 'grade.corrected', 'grade:'.$gradeId, ['score' => $g->score], ['score' => $score, 'document_checks' => $documentChecks], $reason);
        });
    }

    public function calculate(User $u, int $id): void
    {
        Access::transaction($u, function () use ($u, $id) {
            [$a,$o,$c] = Selection::lockedApplication($id);
            Access::require($u, $c->id, ['authority']);
            abort_if($a->user_id === $u->id, 403, 'No puede calcular su propia postulación.');
            Access::assertNotApplicant($u, $c->id);
            Selection::demo($c);
            if ($a->eligibility !== 'enabled' || $c->status !== 'evaluation' || $a->merit === null) {
                Scores::fail('Requiere habilitación, mérito y etapa de evaluación.');
            }
            if (DB::table('publications')->where('offer_id', $o->id)->exists() || DB::table('tie_exams')->where('offer_id', $o->id)->exists()) {
                Scores::fail('Resultados congelados por publicación/desempate.');
            }
            if (! in_array($o->scheme, Scores::CALCULABLE_SCHEMES) || $o->rounding !== 'HALF_UP_FINAL') {
                Scores::fail('Escala/composición/redondeo no definidos.');
            }
            $documentChecks = app(ApplicantDocuments::class)->assertEffective($id);
            $notes = DB::table('grades')->join('assignments', 'assignments.id', '=', 'grades.assignment_id')->where('grades.application_id', $id)->whereIn('grades.status', ['confirmed', 'corrected'])->pluck('grades.score', 'assignments.area')->all();
            $exam = Scores::exam(Selection::json($o->weights), $notes);
            $final = bcadd(Scores::value($a->merit, Scores::MERIT_MAX), $exam, 2);
            DB::table('applications')->where('id', $id)->update(['exam' => $exam, 'final' => $final, 'updated_at' => now()]);
            Selection::audit($u, $c->id, 'result.calculated', 'application:'.$id, ['exam' => $a->exam, 'final' => $a->final], ['notes' => $notes, 'weights' => Selection::json($o->weights), 'exam' => $exam, 'final' => $final, 'scheme' => $o->scheme, 'rounding' => $o->rounding, 'document_checks' => $documentChecks]);
        });
    }
}
