<?php

namespace App\ReadModels;

use App\Domain\Access;
use App\Domain\Participation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Adjudicaciones visibles para la persona o la autoridad.
 * Compartido por la vista Blade y por la API: ninguna depende de la otra.
 */
final class AwardsView
{
    public function data(Request $r): array
    {
        $scope = Access::effective(DB::table('memberships')->where('user_id', $r->user()->id)->where('role', 'authority'))->pluck('call_id');
        $apps = DB::table('applications')->join('offers', 'offers.id', '=', 'applications.offer_id')->join('users', 'users.id', '=', 'applications.user_id')
            ->where(fn ($q) => $q->where('applications.user_id', $r->user()->id)->orWhereIn('offers.call_id', $scope))
            ->select('applications.*', 'offers.title', 'offers.call_id', 'users.name')->get();
        $awards = collect();
        foreach ($apps as $a) {
            $pub = Participation::award($a);
            if ($pub) {
                $a->publication = $pub;
                $a->choices = DB::table('award_choices')->where('application_id', $a->id)->orderByDesc('id')->get();
                $a->choice = $a->choices->firstWhere('publication_id', $pub->id);
                $awards->push($a);
            }
        }

        $authorityView = $scope->isNotEmpty();

        return ['authorityView' => $authorityView, 'awards' => $awards, 'groups' => $awards->groupBy('user_id')];
    }
}
