<?php

namespace App\Domain;

use App\Domain\Concerns\ParticipationContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Solicitudes de retiro de la postulación y renuncia a la plaza adjudicada, con decisión separada. */
final class ParticipationRequests
{
    use ParticipationContext;

    public function request(User $u, int $id, string $kind, string $reason, string $evidence): int
    {
        return Access::transaction($u, function () use ($u, $id, $kind, $reason, $evidence) {
            [$a,$o,$c] = $this->context($id);
            Participation::active($a);
            abort_unless($a->user_id === $u->id, 403);
            $this->evidence($reason, $evidence);
            if ($kind !== (Participation::award($a) ? 'renunciation' : 'withdrawal')) {
                Scores::fail('Distinga retiro de postulación y renuncia a una plaza adjudicada vigente.');
            }
            if (DB::table('participation_requests')->where('application_id', $id)->where('status', 'pending')->exists()) {
                Scores::fail('Ya existe una solicitud pendiente.');
            }
            $request = DB::table('participation_requests')->insertGetId([
                'application_id' => $id,
                'kind' => $kind,
                'requester_id' => $u->id,
                'reason' => $reason,
                'evidence' => $evidence,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            Selection::audit($u, $c->id, 'participation.requested', 'participation:'.$request, null, ['kind' => $kind], $reason);

            return $request;
        });
    }

    public function decide(User $u, int $id, bool $approve, string $reason): void
    {
        Access::transaction($u, function () use ($u, $id, $approve, $reason) {
            $r = Selection::row('participation_requests', $id);
            [$a,$o,$c] = $this->context($r->application_id);
            Access::require($u, $c->id, ['authority']);
            if ($r->status !== 'pending' || $r->requester_id === $u->id || mb_strlen(trim($reason)) < 10) {
                Scores::fail('Se requiere solicitud pendiente, otra autoridad y motivo de decisión.');
            }
            if ($approve) {
                Participation::active($a);
                $award = Participation::award($a);
                if ($r->kind !== ($award ? 'renunciation' : 'withdrawal')) {
                    Scores::fail('La adjudicación cambió; rechace y presente una solicitud actualizada.');
                }
                if (DB::table('correction_requests')->where('application_id', $a->id)->where('status', 'pending')->exists()) {
                    Scores::fail('Resuelva antes las rectificaciones pendientes del expediente.');
                }
                $revisions = app(ResultRevisions::class);
                $before = $revisions->snapshot($o->id);
                DB::table('applications')->where('id', $a->id)->update([
                    'participation' => $award ? 'resigned' : 'withdrawn',
                    'selection' => $award ? 'resigned' : 'withdrawn',
                    'updated_at' => now(),
                ]);
                foreach (DB::table('amendments')->where('application_id', $a->id)->whereIn('status', ['open', 'submitted'])->get() as $m) {
                    DB::table('amendments')->where('id', $m->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
                    Selection::audit($u, $c->id, 'amendment.cancelled', 'amendment:'.$m->id, ['status' => $m->status], ['participation_request' => $id], $reason);
                }
                if ($award) {
                    DB::table('offers')->where('id', $o->id)->increment('held_vacancies');
                    DB::table('vacancy_events')->insert([
                        'offer_id' => $o->id,
                        'application_id' => $a->id,
                        'participation_request_id' => $id,
                        'publication_id' => $award->id,
                        'reason' => $reason,
                        'created_at' => now(),
                    ]);
                }
                if (in_array($c->status, ['evaluation', 'results']) || $award) {
                    DB::table('applications')->where('offer_id', $o->id)->where('participation', 'active')->where('eligibility', 'enabled')->update(['selection' => 'under_review']);
                    $after = $revisions->snapshot($o->id);
                    $changes = $revisions->changes($before, $after);
                    $changes[] = [
                        'application_id' => $a->id,
                        'old_position' => null,
                        'new_position' => null,
                        'old_final' => $a->final,
                        'new_final' => $a->final,
                        'participation' => $award ? 'resigned' : 'withdrawn',
                    ];
                    DB::table('result_revisions')->insert([
                        'offer_id' => $o->id,
                        'revision' => $o->result_revision + 1,
                        'participation_request_id' => $id,
                        'before' => json_encode($before),
                        'after' => json_encode($after),
                        'changes' => json_encode($changes),
                        'created_at' => now(),
                    ]);
                    DB::table('offers')->where('id', $o->id)->update([
                        'result_revision' => $o->result_revision + 1,
                        'needs_publication_review' => true,
                    ]);
                }
            }
            DB::table('participation_requests')->where('id', $id)->update(['status' => $approve ? 'approved' : 'rejected', 'updated_at' => now()]);
            DB::table('participation_decisions')->insert([
                'participation_request_id' => $id,
                'user_id' => $u->id,
                'approved' => $approve,
                'reason' => $reason,
                'created_at' => now(),
            ]);
            Selection::audit($u, $c->id, $approve ? 'participation.approved' : 'participation.rejected', 'participation:'.$id, ['status' => 'pending'], ['kind' => $r->kind, 'approved' => $approve], $reason);
        });
    }
}
