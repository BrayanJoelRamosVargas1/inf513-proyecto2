<?php

namespace App\ReadModels;

use App\Domain\Access;
use App\Domain\InstitutionalCatalogue;
use App\Domain\Selection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CallConsolidation
{
    public function read(User $user, int $id): array
    {
        // Una exportación no mezcla notas y publicaciones de instantes distintos.
        if (DB::transactionLevel() > 0) {
            return $this->build($user, $id);
        }

        return DB::transaction(function () use ($user, $id) {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');

            return $this->build($user, $id);
        });
    }

    private function build(User $user, int $id): array
    {
        Access::require($user, $id, ['authority']);
        $call = Selection::row('calls', $id);
        $offers = DB::table('offers')->where('call_id', $id)->orderBy('id')->get();
        $apps = DB::table('applications')->join('users', 'users.id', '=', 'applications.user_id')
            ->whereIn('offer_id', $offers->pluck('id'))->select('applications.*', 'users.name')->orderBy('applications.id')->get();
        $grades = DB::table('grades')->join('assignments', 'assignments.id', '=', 'grades.assignment_id')
            ->whereIn('grades.application_id', $apps->pluck('id'))->whereIn('grades.status', ['confirmed', 'corrected'])
            ->select('grades.*', 'assignments.area')->get()->groupBy('application_id');
        $documents = DB::table('documents')->whereIn('application_id', $apps->pluck('id'))->orderByDesc('version')->get()->groupBy('application_id');
        $snapshots = DB::table('dossier_snapshots')->whereIn('application_id', $apps->pluck('id'))->orderByDesc('id')->get()->unique('application_id')->keyBy('application_id');
        $reviews = DB::table('reviews')->whereIn('application_id', $apps->pluck('id'))->get()->groupBy('application_id');
        $pendingReviews = DB::table('amendments')->whereIn('application_id', $apps->pluck('id'))->where('status', 'submitted')->pluck('application_id')->all();
        $publications = DB::table('publications')->whereIn('offer_id', $offers->pluck('id'))->orderByDesc('version')->get()->unique('offer_id')->keyBy('offer_id');
        $resolved = DB::table('tie_exams')->whereIn('offer_id', $offers->pluck('id'))->where('status', 'resolved')->get();
        $groups = [];
        foreach ($offers as $offer) {
            $entries = $apps->where('offer_id', $offer->id);
            $publication = $publications->get($offer->id);
            $selected = $publication ? Selection::json($publication->snapshot)['selected'] : [];
            $positions = [];
            $tied = [];
            $warning = null;
            try {
                $ranking = app(Selection::class)->ranking($offer->id);
                $previous = null;
                $position = null;
                foreach ($ranking['rank'] as $i => $entry) {
                    if ($previous === null || bccomp($entry->final, $previous, 2) !== 0) {
                        $position = $i + 1;
                    }
                    $positions[$entry->id] = $position;
                    $previous = $entry->final;
                }
                $tied = $ranking['tied'];
            } catch (ValidationException $e) {
                $warning = 'Clasificación pendiente: hay revisiones o resultados incompletos.';
            }
            $tieResolved = $resolved->contains(fn ($t) => $t->offer_id === $offer->id && $t->result_revision === $offer->result_revision);
            $rows = [];
            foreach ($entries as $a) {
                $notes = ($grades->get($a->id) ?? collect())->keyBy('area');
                $snapshot = $snapshots->get($a->id);
                $files = $documents->get($a->id) ?? collect();
                $current = $snapshot
                    ? $files->whereIn('id', array_column(Selection::json($snapshot->snapshot)['documents'], 'id'))->keyBy('kind')
                    : ($a->status === 'draft' ? $files->whereNull('amendment_id')->unique('kind')->keyBy('kind') : collect());
                $checks = ($reviews->get($a->id) ?? collect())->keyBy('criterion');
                $areas = [];
                foreach (Selection::json($offer->weights) as $area => $weight) {
                    $grade = $notes->get($area);
                    $areas[] = ['area' => $area, 'weight' => $weight, 'score' => $grade?->score, 'status' => $grade?->status ?? 'pending'];
                }
                $filesSummary = [];
                foreach (Selection::json($offer->documents) as $kind => $title) {
                    $doc = $current->get($kind);
                    $filesSummary[] = ['kind' => $kind, 'title' => $title, 'version' => $doc?->version,
                        'present' => $doc !== null, 'review' => in_array($a->id, $pendingReviews) ? 'pending' : ($checks->get('doc:'.$kind)?->decision ?? 'pending')];
                }
                $rows[] = ['id' => $a->id, 'name' => $a->name, 'status' => $a->status, 'eligibility' => $a->eligibility,
                    'participation' => $a->participation, 'merit' => $a->merit, 'exam' => $a->exam, 'final' => $a->final,
                    'selection' => $a->selection, 'position' => $positions[$a->id] ?? null,
                    'tie_pending' => in_array($a->id, $tied) && ! $tieResolved,
                    'published_selected' => in_array($a->id, $selected),
                    'award_active' => in_array($a->id, $selected) && $a->participation === 'active',
                    'areas' => $areas, 'documents' => $filesSummary];
            }
            $covered = $publication ? count(array_filter($rows, fn ($r) => $r['award_active'])) : null;
            $groups[] = ['offer' => $offer, 'rows' => $rows, 'publication' => $publication, 'warning' => $warning,
                'covered' => $covered, 'vacant' => $covered === null || $offer->seats === null ? null : max(0, $offer->seats - $covered)];
        }

        return ['call' => $call, 'groups' => $groups, 'generated_at' => now(), 'institutional_records' => DB::table('institutional_register')->where('call_id', $id)->where('kind', '!=', 'cpd')->orderBy('id')->get(), 'documentary' => app(InstitutionalCatalogue::class)->forCall($id),
            'totals' => ['offers' => $offers->count(), 'applications' => $apps->count(), 'seats' => $offers->sum('seats'), 'offers_with_unspecified_seats' => $offers->whereNull('seats')->count(),
                'enabled' => $apps->where('eligibility', 'enabled')->where('participation', 'active')->count(),
                'disabled' => $apps->where('eligibility', 'disabled')->count(),
                'held' => $offers->sum('held_vacancies')]];
    }
}
