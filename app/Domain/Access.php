<?php

namespace App\Domain;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class Access
{
    public static function has(User $user, int $call, array $roles): bool
    {
        return $user->active && DB::table('memberships')->where('user_id', $user->id)->where('call_id', $call)->whereIn('role', $roles)->exists();
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
