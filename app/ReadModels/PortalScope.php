<?php

namespace App\ReadModels;

use App\Domain\Access;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PortalScope
{
    public static function calls(User $u)
    {
        $ids = Access::effective(DB::table('memberships')->where('user_id', $u->id))->pluck('call_id');

        return DB::table('calls')->when($u->role !== 'admin', function ($q) use ($u, $ids) {
            $q->where(function ($q) use ($u, $ids) {
                $q->whereIn('id', $ids);
                if ($u->role === 'student') {
                    $q->orWhere('status', '!=', 'draft');
                }
                $q->orWhereIn('id', DB::table('offers')->join('applications', 'applications.offer_id', '=', 'offers.id')->where('applications.user_id', $u->id)->select('offers.call_id'));
            });
        });
    }

    public static function applications(User $u)
    {
        $review = Access::effective(DB::table('memberships')->where('user_id', $u->id)->whereIn('role', ['reviewer', 'authority']))->pluck('call_id');
        $evaluation = Access::effective(DB::table('memberships')->where('user_id', $u->id)->where('role', 'evaluator'))->pluck('call_id');

        return DB::table('applications')->join('offers', 'offers.id', '=', 'applications.offer_id')->join('users', 'users.id', '=', 'applications.user_id')
            ->where(fn ($q) => $q->where('applications.user_id', $u->id)->orWhereIn('offers.call_id', $review)
                ->orWhere(fn ($q) => $q->whereIn('offers.call_id', $evaluation)->whereIn('offers.id', DB::table('assignments')->where('user_id', $u->id)->pluck('offer_id'))));
    }
}
