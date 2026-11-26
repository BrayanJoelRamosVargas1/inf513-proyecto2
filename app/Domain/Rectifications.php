<?php

namespace App\Domain;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Procedimiento técnico DEMO; no sustituye una resolución institucional. */
final class Rectifications
{
    public static function version(int $app, ?int $grade, int $version, string $score, ?int $actor, ?int $request = null): void
    {
        DB::table('score_versions')->insert([
            'application_id' => $app,
            'grade_id' => $grade,
            'key' => $grade ? 'grade:'.$grade : 'merit:'.$app,
            'version' => $version,
            'score' => $score,
            'recorded_by' => $actor,
            'correction_request_id' => $request,
            'created_at' => now(),
        ]);
    }

    public function request(User $u, int $app, ?int $grade, string $score, string $reason, string $evidence): int
    {
        return Access::transaction($u, function () use ($u, $app, $grade, $score, $reason, $evidence) {
            $a = Selection::row('applications', $app);
            Participation::active($a);
            $o = Selection::row('offers', $a->offer_id);
            $c = Selection::row('calls', $o->call_id, true);
            Access::assertNotApplicant($u, $c->id);
            if (! Selection::isOperable($c) || ! in_array($c->status, ['evaluation', 'results'])) {
                Scores::fail('Rectificación disponible únicamente en evaluación o resultados DEMO.');
            }
            if (mb_strlen(trim($reason)) < 10 || mb_strlen(trim($evidence)) < 10) {
                Scores::fail('Motivo y referencia de evidencia obligatorios (mínimo 10 caracteres).');
            }
            $g = $grade ? Selection::row('grades', $grade, true) : null;
            if ($g) {
                abort_unless($g->application_id === $app, 403);
                $assignment = Selection::row('assignments', $g->assignment_id);
                abort_unless(Access::has($u, $c->id, ['authority']) || ($assignment->user_id === $u->id && Access::has($u, $c->id, ['evaluator'])), 403);
                if ($g->status === 'draft') {
                    Scores::fail('Confirme el borrador antes de solicitar rectificación.');
                }
            } else {
                Access::require($u, $c->id, ['reviewer', 'authority']);
            }
            $old = $g ? $g->score : $a->merit;
            if ($old === null) {
                Scores::fail('No hay calificación original confirmada.');
            }
            $score = Scores::value($score, $g ? '100' : Scores::MERIT_MAX);
            if (bccomp($old, $score, 2) === 0) {
                Scores::fail('La propuesta debe cambiar el valor.');
            }
            $id = DB::table('correction_requests')->insertGetId([
                'application_id' => $app,
                'grade_id' => $grade,
                'kind' => $g ? 'exam' : 'merit',
                'old_value' => $old,
                'new_value' => $score,
                'base_version' => $g ? $g->version : $a->merit_version,
                'requester_id' => $u->id,
                'original_evaluator_id' => $g?->user_id,
                'reason' => $reason,
                'evidence_reference' => $evidence,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            Selection::audit($u, $c->id, 'correction.requested', 'correction:'.$id, null, [
                'old' => $old,
                'new' => $score,
                'evidence' => $evidence,
            ], $reason);

            return $id;
        });
    }

    public function decide(User $u, int $id, bool $approve, string $reason): void
    {
        Access::transaction($u, function () use ($u, $id, $approve, $reason) {
            $request = Selection::row('correction_requests', $id);
            $a = Selection::row('applications', $request->application_id);
            Participation::active($a);
            $o = Selection::row('offers', $a->offer_id);
            $c = Selection::row('calls', $o->call_id, true);
            $a = Selection::row('applications', $a->id, true);
            $o = Selection::row('offers', $o->id, true);
            $r = Selection::row('correction_requests', $id, true);
            Access::require($u, $c->id, ['authority']);
            abort_if($a->user_id === $u->id, 403, 'No puede decidir una rectificación de su propia postulación.');
            if (! Selection::isOperable($c) || ! in_array($c->status, ['evaluation', 'results'])) {
                Scores::fail('Etapa cerrada o convocatoria histórica.');
            }
            if ($r->requester_id === $u->id) {
                Scores::fail('La decisión requiere una autoridad distinta del solicitante (control técnico DEMO).');
            }
            if ($r->status !== 'pending' || mb_strlen(trim($reason)) < 10) {
                Scores::fail('Solicitud ya decidida o justificación insuficiente.');
            }
            if ($approve) {
                app(ApplicantDocuments::class)->assertEffective($a->id);
                $g = $r->grade_id ? Selection::row('grades', $r->grade_id, true) : null;
                if (($g ? $g->version : $a->merit_version) !== $r->base_version || bccomp($g ? $g->score : $a->merit, $r->old_value, 2) !== 0) {
                    Scores::fail('Propuesta obsoleta: existe otra versión. Rechace y solicite una nueva.');
                }
                $revisions = app(ResultRevisions::class);
                $before = $revisions->snapshot($o->id);
                if ($g) {
                    DB::table('grades')->where('id', $g->id)->update([
                        'score' => $r->new_value,
                        'status' => 'corrected',
                        'version' => $g->version + 1,
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('applications')->where('id', $a->id)->update([
                        'merit' => $r->new_value,
                        'merit_version' => $a->merit_version + 1,
                        'updated_at' => now(),
                    ]);
                }
                self::version($a->id, $r->grade_id, $r->base_version + 1, $r->new_value, $u->id, $id);
                if (! in_array($o->scheme, Scores::CALCULABLE_SCHEMES) || $o->rounding !== 'HALF_UP_FINAL') {
                    Scores::fail('Composición no validada.');
                }
                $notes = DB::table('grades')->join('assignments', 'assignments.id', '=', 'grades.assignment_id')->where('grades.application_id', $a->id)->whereIn('grades.status', ['confirmed', 'corrected'])->pluck('grades.score', 'assignments.area')->all();
                $merit = $g ? $a->merit : $r->new_value;
                $complete = $merit !== null && ! array_diff(array_keys(Selection::json($o->weights)), array_keys($notes));
                $exam = $complete ? Scores::exam(Selection::json($o->weights), $notes) : null;
                DB::table('applications')->where('id', $a->id)->update([
                    'exam' => $exam,
                    'final' => $complete ? bcadd($merit, $exam, 2) : null,
                    'updated_at' => now(),
                ]);
                DB::table('applications')->where('offer_id', $o->id)->where('participation', 'active')->where('eligibility', 'enabled')->update(['selection' => 'under_review']);
                $after = $revisions->snapshot($o->id);
                $changes = $revisions->changes($before, $after);
                // Captura de todas las versiones de entrada; no se mezclan operaciones gracias al cerrojo común.
                $after['score_versions'] = DB::table('score_versions')->whereIn('application_id', DB::table('applications')->where('offer_id', $o->id)->select('id'))->orderBy('id')->get()->all();
                DB::table('result_revisions')->insert([
                    'offer_id' => $o->id,
                    'revision' => $o->result_revision + 1,
                    'correction_request_id' => $id,
                    'before' => json_encode($before),
                    'after' => json_encode($after),
                    'changes' => json_encode($changes),
                    'created_at' => now(),
                ]);
                DB::table('offers')->where('id', $o->id)->update(['result_revision' => $o->result_revision + 1, 'needs_publication_review' => true]);
            }
            DB::table('correction_requests')->where('id', $id)->update([
                'status' => $approve ? 'approved' : 'rejected',
                'decided_by' => $u->id,
                'decision_reason' => $reason,
                'decided_at' => now(),
                'updated_at' => now(),
            ]);
            Selection::audit($u, $c->id, $approve ? 'correction.approved' : 'correction.rejected', 'correction:'.$id, ['status' => 'pending'], [
                'old' => $r->old_value,
                'new' => $r->new_value,
                'result_revision' => $approve ? $o->result_revision + 1 : $o->result_revision,
            ], $reason);
        });
    }
}
