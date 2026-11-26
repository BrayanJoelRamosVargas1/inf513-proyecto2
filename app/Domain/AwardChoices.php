<?php

namespace App\Domain;

use App\Domain\Concerns\ParticipationContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Manifestaciones del postulante sobre una adjudicación publicada. */
final class AwardChoices
{
    use ParticipationContext;

    public function choose(User $u, int $id, int $publication, string $choice, int $version, string $reason, string $evidence): void
    {
        Access::transaction($u, function () use ($u, $id, $publication, $choice, $version, $reason, $evidence) {
            [$a,$o,$c] = $this->context($id);
            abort_unless($a->user_id === $u->id, 403);
            $this->evidence($reason, $evidence);
            $pub = Participation::award($a);
            if (! $pub || $pub->id !== $publication || ! in_array($choice, ['accept', 'defer'])) {
                Scores::fail('Adjudicación obsoleta o decisión inválida. Rechazar una plaza requiere solicitar renuncia.');
            }
            if (DB::table('participation_requests')->where('application_id', $id)->where('status', 'pending')->exists()) {
                Scores::fail('Resuelva la solicitud de retiro/renuncia antes de registrar otra manifestación.');
            }
            $current = DB::table('award_choices')->where('application_id', $id)->where('publication_id', $publication)->max('version') ?? 0;
            if ($current !== $version) {
                Scores::fail('La manifestación cambió; recargue la versión actual.');
            }
            DB::table('award_choices')->insert([
                'application_id' => $id,
                'publication_id' => $publication,
                'version' => $version + 1,
                'user_id' => $u->id,
                'choice' => $choice,
                'reason' => $reason,
                'evidence' => $evidence,
                'created_at' => now(),
            ]);
            Selection::audit($u, $c->id, 'award.choice_recorded', 'application:'.$id, ['version' => $version], [
                'publication' => $publication,
                'version' => $version + 1,
                'choice' => $choice,
                'compatibility' => 'PENDIENTE_VALIDACION_INSTITUCIONAL',
            ], $reason);
        });
    }
}
