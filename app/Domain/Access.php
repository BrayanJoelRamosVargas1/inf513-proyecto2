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

    public static function viewApplication(User $user, $app): void
    {
        $offer = DB::table('offers')->find($app->offer_id);
        $assigned = self::has($user, $offer->call_id, ['evaluator']) && DB::table('assignments')->where('offer_id', $offer->id)->where('user_id', $user->id)->exists();
        abort_unless($user->active && ($app->user_id === $user->id || self::has($user, $offer->call_id, ['reviewer', 'authority']) || $assigned), 403);
    }

    public static function privateFile(User $user, $app): void
    {
        $offer = DB::table('offers')->find($app->offer_id);
        abort_unless($user->active && ($app->user_id === $user->id || self::has($user, $offer->call_id, ['reviewer'])), 403);
    }
}
