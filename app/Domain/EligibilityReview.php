<?php

namespace App\Domain;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Revisión de requisitos, habilitación y registro de méritos. Reglas y bloqueos idénticos a los de Selection, que expone la misma API. */
final class EligibilityReview
{
    public function review(User $u, int $id, array $decisions, string $reason): void
    {
        Access::transaction($u, function () use ($u, $id, $decisions, $reason) {
            [$app,$offer,$call] = Selection::lockedApplication($id);
            Access::require($u, $call->id, ['reviewer']);
            abort_if($app->user_id === $u->id, 403, 'No puede revisar ni habilitar su propia postulación.');
            Participation::legacyReview($id);
            Selection::demo($call);
            if ($app->status !== 'submitted' || $app->eligibility !== 'pending' || ! in_array($call->status, ['published', 'closed'])) {
                Scores::fail('La revisión no está abierta.');
            }
            $documentChecks = app(ApplicantDocuments::class)->assertDecisions($id, $decisions);
            $criteria = array_merge(array_fill_keys(array_map(fn ($x) => 'doc:'.$x, array_keys(Selection::json($offer->documents))), ''), Selection::json($offer->requirements));
            foreach ($decisions as $key => $decision) {
                if (! array_key_exists($key, $criteria) || ! in_array($decision, ['meets', 'fails', 'observed', 'not_applicable'])) {
                    Scores::fail('Criterio o decisión inválidos.');
                }
                if ($decision === 'not_applicable' && (! Selection::conditionalCriterion($offer, $key) || mb_strlen(trim($reason)) < 10)) {
                    Scores::fail('No aplica requiere un criterio condicional y fundamento documental.');
                }
                $before = DB::table('reviews')->where('application_id', $id)->where('criterion', $key)->first();
                DB::table('reviews')->updateOrInsert(['application_id' => $id, 'criterion' => $key], ['decision' => $decision, 'reason' => $reason, 'user_id' => $u->id, 'updated_at' => now(), 'created_at' => $before?->created_at ?? now()]);
                Selection::audit($u, $call->id, 'review.recorded', 'application:'.$id, $before, ['criterion' => $key, 'decision' => $decision, 'document_checks' => $documentChecks], $reason);
            }
        });
    }

    public function eligibility(User $u, int $id, bool $enable, string $reason): void
    {
        Access::transaction($u, function () use ($u, $id, $enable, $reason) {
            [$app,$offer,$call] = Selection::lockedApplication($id);
            Access::require($u, $call->id, ['reviewer']);
            abort_if($app->user_id === $u->id, 403, 'No puede revisar ni habilitar su propia postulación.');
            Participation::legacyReview($id);
            Selection::demo($call);
            if ($app->status !== 'submitted' || $app->eligibility !== 'pending' || ! in_array($call->status, ['published', 'closed'])) {
                Scores::fail('Decisión de habilitación no disponible.');
            }
            $documentChecks = [];
            if ($enable) {
                $documentChecks = app(ApplicantDocuments::class)->assertEffective($id);
                $keys = array_merge(array_map(fn ($x) => 'doc:'.$x, array_keys(Selection::json($offer->documents))), array_keys(Selection::json($offer->requirements)));
                foreach ($keys as $key) {
                    $decision = DB::table('reviews')->where('application_id', $id)->where('criterion', $key)->value('decision');
                    if (! Selection::criterionSatisfied($offer, $key, $decision)) {
                        Scores::fail('No se cumplen todos los documentos/requisitos con evidencia.');
                    }
                }
            }
            $next = $enable ? 'enabled' : 'disabled';
            DB::table('applications')->where('id', $id)->update(['eligibility' => $next, 'eligibility_reason' => $reason, 'updated_at' => now()]);
            Selection::audit($u, $call->id, 'eligibility.decided', 'application:'.$id, ['eligibility' => $app->eligibility], ['eligibility' => $next, 'document_checks' => $documentChecks], $reason);
        });
    }

    public function merit(User $u, int $id, string $score, string $reference): void
    {
        Access::transaction($u, function () use ($u, $id, $score, $reference) {
            [$app,$o,$c] = Selection::lockedApplication($id);
            Access::require($u, $c->id, ['reviewer']);
            abort_if($app->user_id === $u->id, 403, 'No puede revisar ni habilitar su propia postulación.');
            Participation::noAmendment($id);
            Selection::demo($c);
            if (! in_array($c->status, ['published', 'closed', 'evaluation']) || $app->status !== 'submitted' || $app->merit !== null) {
                Scores::fail('El mérito confirmado requiere rectificación documentada; esta operación es solo registro inicial.');
            }
            if (! DB::table('documents')->where('application_id', $id)->where('kind', 'cpd')->exists()) {
                Scores::fail('Adjunte el formulario CPD de respaldo.');
            }
            $documentChecks = app(ApplicantDocuments::class)->assertEffective($id);
            $score = Scores::value($score, Scores::MERIT_MAX);
            DB::table('applications')->where('id', $id)->update(['merit' => $score, 'cpd_reference' => $reference, 'merit_version' => 1, 'updated_at' => now()]);
            Rectifications::version($id, null, 1, $score, $u->id);
            Selection::audit($u, $c->id, 'merit.recorded', 'application:'.$id, null, ['merit' => $score, 'reference' => $reference, 'document_checks' => $documentChecks]);
        });
    }
}
