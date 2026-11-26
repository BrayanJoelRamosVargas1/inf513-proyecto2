<?php

namespace App\Domain\Concerns;

use App\Domain\Scores;
use App\Domain\Selection;

/** Contexto y validación de evidencia comunes a los flujos de participación. */
trait ParticipationContext
{
    private function context(int $id): array
    {
        $a = Selection::row('applications', $id);
        $o = Selection::row('offers', $a->offer_id);
        $c = Selection::row('calls', $o->call_id, true);
        if (! Selection::isOperable($c) || ! in_array($c->status, ['published', 'closed', 'evaluation', 'results'])) {
            Scores::fail('Procedimiento disponible solo en convocatorias DEMO no archivadas.');
        }

        return [Selection::row('applications', $id, true), Selection::row('offers', $o->id, true), $c];
    }

    private function evidence(string $reason, string $evidence): void
    {
        if (mb_strlen(trim($reason)) < 10 || mb_strlen(trim($evidence)) < 10) {
            Scores::fail('Motivo y referencia de evidencia requieren al menos 10 caracteres.');
        }
    }
}
