<?php

namespace App\Domain;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class Access
{
    // Orden común: cerrojo de permisos -> convocatoria -> expediente/nota.
    // DEMO de volumen facultativo: serialización corta y conservadora.
    public static function transaction(User $user, callable $action): mixed
    {
        return DB::transaction(function () use ($user, $action) {
            DB::table('access_control_lock')->where('id', 1)->lockForUpdate()->first();
            $user->refresh();
            abort_unless($user->active, 403, 'Cuenta desactivada.');

            return $action();
        });
    }

    public static function effective($query)
    {
        return $query->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public static function has(User $user, int $call, array $roles): bool
    {
        return DB::table('users')->where('id', $user->id)->where('active', true)->exists()
            && self::effective(DB::table('memberships')->where('user_id', $user->id)->where('call_id', $call)->whereIn('role', $roles))->exists();
    }

    public static function require(User $user, int $call, array $roles): void
    {
        abort_unless(self::has($user, $call, $roles), 403, 'No tiene permiso en esta convocatoria.');
    }

    /** El usuario tiene una postulación vigente en la convocatoria (no retirada ni renunciada). */
    public static function isApplicant(User $user, int $call): bool
    {
        return DB::table('applications')->join('offers', 'offers.id', '=', 'applications.offer_id')
            ->where('offers.call_id', $call)->where('applications.user_id', $user->id)->where('applications.participation', 'active')->exists();
    }

    /** Quien postula en una convocatoria no decide sobre sus resultados. */
    public static function assertNotApplicant(User $user, int $call): void
    {
        abort_if(self::isApplicant($user, $call), 403, 'Quien postula en esta convocatoria no puede decidir sobre sus resultados.');
    }

    public static function viewApplication(User $user, $app): void
    {
        $offer = DB::table('offers')->find($app->offer_id);
        $assigned = self::has($user, $offer->call_id, ['evaluator']) && DB::table('assignments')->where('offer_id', $offer->id)->where('user_id', $user->id)->exists();
        abort_unless($user->active && ($app->user_id === $user->id || self::has($user, $offer->call_id, ['reviewer', 'authority']) || $assigned), 403);
    }

    public static function privateFile(User $user, $app): void
    {
        $offer = DB::table('offers')->find($app->offer_id);
        // El revisor solo ve los archivos de un expediente que la persona ya confirmó; un borrador es solo del postulante.
        abort_unless($user->active && ($app->user_id === $user->id || ($app->status === 'submitted' && self::has($user, $offer->call_id, ['reviewer']))), 403);
    }
}
