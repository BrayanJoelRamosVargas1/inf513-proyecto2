<?php

namespace App\Domain;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class Workspace
{
    public static function roles(User $user): array
    {
        return Access::effective(DB::table('memberships')->where('user_id', $user->id))
            ->distinct()->pluck('role')->all();
    }

    public static function receiving(object $call): bool
    {
        return Selection::isOperable($call) && $call->status === 'published'
            && now()->gte($call->opens_at) && now()->lte($call->closes_at);
    }

    public static function applicationBlock(User $user, object $call, object $offer, int $subjects): ?string
    {
        if (! self::receiving($call)) {
            return 'Recepción no disponible en este momento. Consulte las fechas y la etapa.';
        }
        if (($call->scope === 'faculty' && $user->faculty !== $call->scope_value)
            || ($call->scope === 'career' && $user->career !== $call->scope_value)) {
            return 'Su perfil no pertenece al ámbito de esta convocatoria.';
        }
        if ($offer->is_subject && $call->subject_limit !== null && $subjects >= $call->subject_limit) {
            return 'Alcanzó el límite de asignaturas de esta convocatoria (incluye borradores).';
        }

        return null;
    }
}
