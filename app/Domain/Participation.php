<?php

namespace App\Domain;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Flujos técnicos DEMO. Plazos y compatibilidades institucionales no se infieren. */
final class Participation
{
    private function context(int $id): array
    {
        $a = Selection::row('applications', $id);
        $o = Selection::row('offers', $a->offer_id);
        $c = Selection::row('calls', $o->call_id, true);
        if ($c->mode !== 'DEMO' || ! in_array($c->status, ['published', 'closed', 'evaluation', 'results'])) {
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

    public static function active(object $a): void
    {
        if ($a->participation !== 'active') {
            Scores::fail('Postulación retirada o plaza renunciada: operación bloqueada.');
        }
    }

    public static function noAmendment(int $id): void
    {
        if (DB::table('amendments')->where('application_id', $id)->whereIn('status', ['open', 'submitted'])->exists()) {
            Scores::fail('Complete el circuito de subsanación y nueva revisión del expediente.');
        }
    }

    public static function legacyReview(int $id): void
    {
        if (DB::table('amendments')->where('application_id', $id)->exists()) {
            Scores::fail('Este expediente usa subsanaciones versionadas; complete su revisión desde Procesos.');
        }
    }

    public static function snapshot(int $id, int $version, string $phase, ?int $actor, ?array $documents = null): void
    {
        $a = Selection::row('applications', $id);
        $documents ??= DB::table('documents')->where('application_id', $id)->whereNull('amendment_id')->orderByDesc('version')->get()->unique('kind')->values()->map(fn ($d) => ['id' => $d->id, 'kind' => $d->kind, 'version' => $d->version, 'sha256' => $d->sha256])->all();
        DB::table('dossier_snapshots')->insert(['application_id' => $id, 'version' => $version, 'phase' => $phase, 'snapshot' => json_encode(['documents' => $documents, 'reviews' => DB::table('reviews')->where('application_id', $id)->get()->all(), 'eligibility' => $a->eligibility, 'eligibility_reason' => $a->eligibility_reason]), 'user_id' => $actor, 'created_at' => now()]);
    }

    public function open(User $u, int $app, array $kinds, string $deadline, string $reason, string $evidence): int
    {
        return Access::transaction($u, function () use ($u, $app, $kinds, $deadline, $reason, $evidence) {
            [$a,$o,$c] = $this->context($app);
            Access::require($u, $c->id, ['reviewer', 'authority']);
            abort_if($a->user_id === $u->id, 403);
            self::active($a);
            self::noAmendment($app);
            $this->evidence($reason, $evidence);
            $deadline = CarbonImmutable::parse($deadline, config('app.timezone'));
            if ($a->status !== 'submitted' || ! in_array($a->eligibility, ['pending', 'disabled']) || ! in_array($c->status, ['published', 'closed']) || $deadline->lte(now())) {
                Scores::fail('Requiere expediente observado/no habilitado, antes de evaluar y un plazo futuro explícito DEMO.');
            }
            $observed = DB::table('reviews')->where('application_id', $app)->whereIn('decision', ['observed', 'fails'])->exists();
            if (! $observed || ! $kinds || array_diff($kinds, array_keys(Selection::json($o->documents)))) {
                Scores::fail('Seleccione documentos requeridos; debe existir una observación registrada.');
            }
            if (! DB::table('dossier_snapshots')->where('application_id', $app)->where('version', $a->dossier_version)->where('phase', 'pre_amendment_review')->exists()) {
                $prior = DB::table('dossier_snapshots')->where('application_id', $app)->orderByDesc('id')->first();
                if (! $prior) {
                    Scores::fail('Falta la versión original del expediente.');
                }
                self::snapshot($app, $a->dossier_version, 'pre_amendment_review', $u->id, Selection::json($prior->snapshot)['documents']);
            }
            $id = DB::table('amendments')->insertGetId(['application_id' => $app, 'base_version' => $a->dossier_version, 'allowed_kinds' => json_encode(array_values(array_unique($kinds))), 'deadline' => $deadline->toIso8601String(), 'opened_by' => $u->id, 'reason' => $reason, 'evidence' => $evidence, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('applications')->where('id', $app)->update(['eligibility' => 'pending', 'eligibility_reason' => 'Subsanación DEMO pendiente; no constituye habilitación.']);
            Selection::audit($u, $c->id, 'amendment.opened', 'amendment:'.$id, null, ['kinds' => $kinds, 'deadline' => $deadline->toIso8601String(), 'base_version' => $a->dossier_version], $reason);

            return $id;
        });
    }

    public function upload(User $u, int $id, string $kind, UploadedFile $file): int
    {
        $stored = null;
        try {
            return Access::transaction($u, function () use ($u, $id, $kind, $file, &$stored) {
                $m = Selection::row('amendments', $id);
                [$a,$o,$c] = $this->context($m->application_id);
                self::active($a);
                abort_unless($a->user_id === $u->id, 403);
                if ($m->status !== 'open' || now()->gt($m->deadline) || $a->dossier_version !== $m->base_version || ! in_array($kind, Selection::json($m->allowed_kinds))) {
                    Scores::fail('Subsanación cerrada, vencida, obsoleta o documento ajeno a lo autorizado.');
                }
                validator(['file' => $file], ['file' => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png|extensions:pdf,jpg,jpeg,png'])->validate();
                if (! in_array($file->getMimeType(), ['application/pdf', 'image/png', 'image/jpeg'])) {
                    Scores::fail('Formato no permitido.');
                }
                $stored = $file->store('expedientes', 'local');
                $version = (DB::table('documents')->where('application_id', $a->id)->where('kind', $kind)->max('version') ?? 0) + 1;
                $doc = DB::table('documents')->insertGetId(['application_id' => $a->id, 'amendment_id' => $id, 'kind' => $kind, 'version' => $version, 'path' => $stored, 'name' => basename($file->getClientOriginalName()), 'mime' => $file->getMimeType(), 'sha256' => hash_file('sha256', $file->getRealPath()), 'created_at' => now(), 'updated_at' => now()]);
                Selection::audit($u, $c->id, 'amendment.document_uploaded', 'document:'.$doc, null, ['amendment' => $id, 'version' => $version, 'kind' => $kind]);

                return $doc;
            });
        } catch (\Throwable $e) {
            if ($stored) {
                Storage::disk('local')->delete($stored);
            }throw $e;
        }
    }

    public function submit(User $u, int $id): void
    {
        Access::transaction($u, function () use ($u, $id) {
            $m = Selection::row('amendments', $id);
            [$a,$o,$c] = $this->context($m->application_id);
            self::active($a);
            abort_unless($a->user_id === $u->id, 403);
            if ($m->status !== 'open' || now()->gt($m->deadline) || $a->dossier_version !== $m->base_version) {
                Scores::fail('Envío cerrado, vencido o versión obsoleta.');
            }
            $new = DB::table('documents')->where('amendment_id', $id)->orderByDesc('version')->get()->unique('kind')->keyBy('kind');
            if (array_diff(Selection::json($m->allowed_kinds), $new->keys()->all())) {
                Scores::fail('Falta subsanar alguno de los documentos autorizados.');
            }
            $previous = DB::table('dossier_snapshots')->where('application_id', $a->id)->orderByDesc('id')->first();
            if (! $previous) {
                Scores::fail('Falta snapshot del expediente anterior.');
            }
            $docs = collect(Selection::json($previous->snapshot)['documents'])->keyBy('kind');
            foreach ($new as $kind => $d) {
                $docs[$kind] = ['id' => $d->id, 'kind' => $kind, 'version' => $d->version, 'sha256' => $d->sha256];
            }
            DB::table('applications')->where('id', $a->id)->update(['dossier_version' => $a->dossier_version + 1]);
            DB::table('amendments')->where('id', $id)->update(['status' => 'submitted', 'submitted_at' => now(), 'updated_at' => now()]);
            self::snapshot($a->id, $a->dossier_version + 1, 'submitted', $u->id, $docs->values()->all());
            Selection::audit($u, $c->id, 'amendment.submitted', 'amendment:'.$id, ['version' => $a->dossier_version], ['version' => $a->dossier_version + 1]);
        });
    }

    public function review(User $u, int $id, array $decisions, string $reason): void
    {
        Access::transaction($u, function () use ($u, $id, $decisions, $reason) {
            $m = Selection::row('amendments', $id);
            [$a,$o,$c] = $this->context($m->application_id);
            Access::require($u, $c->id, ['reviewer']);
            abort_if($a->user_id === $u->id, 403);
            self::active($a);
            if ($m->status !== 'submitted' || $a->dossier_version !== $m->base_version + 1 || mb_strlen(trim($reason)) < 10) {
                Scores::fail('Revisión no disponible o sin motivo.');
            }
            $keys = array_merge(array_map(fn ($k) => 'doc:'.$k, array_keys(Selection::json($o->documents))), array_keys(Selection::json($o->requirements)));
            if (array_diff($keys, array_keys($decisions)) || array_diff(array_keys($decisions), $keys) || array_diff(array_values($decisions), ['meets', 'fails', 'observed'])) {
                Scores::fail('Repita la revisión completa de documentos y requisitos.');
            }
            $before = DB::table('reviews')->where('application_id', $a->id)->get();
            foreach ($decisions as $key => $value) {
                DB::table('reviews')->updateOrInsert(['application_id' => $a->id, 'criterion' => $key], ['decision' => $value, 'reason' => $reason, 'user_id' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
            }
            $enabled = ! array_diff(array_values($decisions), ['meets']);
            DB::table('applications')->where('id', $a->id)->update(['eligibility' => $enabled ? 'enabled' : 'disabled', 'eligibility_reason' => $reason]);
            DB::table('amendments')->where('id', $id)->update(['status' => 'reviewed', 'updated_at' => now()]);
            $snap = DB::table('dossier_snapshots')->where('application_id', $a->id)->orderByDesc('id')->first();
            self::snapshot($a->id, $a->dossier_version, 'reviewed', $u->id, Selection::json($snap->snapshot)['documents']);
            Selection::audit($u, $c->id, 'amendment.reviewed', 'amendment:'.$id, $before, ['decisions' => $decisions, 'eligibility' => $enabled ? 'enabled' : 'disabled', 'version' => $a->dossier_version], $reason);
        });
    }

    public function expire(User $u, int $id, string $reason): void
    {
        Access::transaction($u, function () use ($u, $id, $reason) {
            $m = Selection::row('amendments', $id);
            [$a,$o,$c] = $this->context($m->application_id);
            Access::require($u, $c->id, ['reviewer', 'authority']);
            abort_if($a->user_id === $u->id, 403, 'No puede decidir sobre su propia subsanación.');
            if ($m->status !== 'open' || now()->lte($m->deadline) || mb_strlen(trim($reason)) < 10) {
                Scores::fail('Solo una ventana vencida y no enviada puede cerrarse por vencimiento.');
            }
            DB::table('amendments')->where('id', $id)->update(['status' => 'expired', 'updated_at' => now()]);
            DB::table('applications')->where('id', $a->id)->update(['eligibility' => 'disabled', 'eligibility_reason' => $reason]);
            $previous = DB::table('dossier_snapshots')->where('application_id', $a->id)->orderByDesc('id')->first();
            self::snapshot($a->id, $a->dossier_version, 'expired_window_'.$id, $u->id, Selection::json($previous->snapshot)['documents']);
            Selection::audit($u, $c->id, 'amendment.expired', 'amendment:'.$id, ['status' => 'open'], ['status' => 'expired'], $reason);
        });
    }

    public static function award(object $a): ?object
    {
        $pub = DB::table('publications')->where('offer_id', $a->offer_id)->orderByDesc('version')->first();

        return $pub && $a->participation === 'active' && in_array($a->id, Selection::json($pub->snapshot)['selected']) ? $pub : null;
    }

    public function request(User $u, int $id, string $kind, string $reason, string $evidence): int
    {
        return Access::transaction($u, function () use ($u, $id, $kind, $reason, $evidence) {
            [$a,$o,$c] = $this->context($id);
            self::active($a);
            abort_unless($a->user_id === $u->id, 403);
            $this->evidence($reason, $evidence);
            if ($kind !== (self::award($a) ? 'renunciation' : 'withdrawal')) {
                Scores::fail('Distinga retiro de postulación y renuncia a una plaza adjudicada vigente.');
            }
            if (DB::table('participation_requests')->where('application_id', $id)->where('status', 'pending')->exists()) {
                Scores::fail('Ya existe una solicitud pendiente.');
            }
            $request = DB::table('participation_requests')->insertGetId(['application_id' => $id, 'kind' => $kind, 'requester_id' => $u->id, 'reason' => $reason, 'evidence' => $evidence, 'created_at' => now(), 'updated_at' => now()]);
            Selection::audit($u, $c->id, 'participation.requested', 'participation:'.$request, null, ['kind' => $kind], $reason);

            return $request;
        });
    }

    private function rankingSnapshot(int $offer): array
    {
        try {
            return app(Selection::class)->ranking($offer);
        } catch (ValidationException $e) {
            return ['incomplete' => true, 'applications' => DB::table('applications')->where('offer_id', $offer)->get()->all(), 'rank' => [], 'tied' => []];
        }
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
                self::active($a);
                $award = self::award($a);
                if ($r->kind !== ($award ? 'renunciation' : 'withdrawal')) {
                    Scores::fail('La adjudicación cambió; rechace y presente una solicitud actualizada.');
                }
                if (DB::table('correction_requests')->where('application_id', $a->id)->where('status', 'pending')->exists()) {
                    Scores::fail('Resuelva antes las rectificaciones pendientes del expediente.');
                }
                $before = $this->rankingSnapshot($o->id);
                DB::table('applications')->where('id', $a->id)->update(['participation' => $award ? 'resigned' : 'withdrawn', 'selection' => $award ? 'resigned' : 'withdrawn', 'updated_at' => now()]);
                foreach (DB::table('amendments')->where('application_id', $a->id)->whereIn('status', ['open', 'submitted'])->get() as $m) {
                    DB::table('amendments')->where('id', $m->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
                    Selection::audit($u, $c->id, 'amendment.cancelled', 'amendment:'.$m->id, ['status' => $m->status], ['participation_request' => $id], $reason);
                }
                if ($award) {
                    DB::table('offers')->where('id', $o->id)->increment('held_vacancies');
                    DB::table('vacancy_events')->insert(['offer_id' => $o->id, 'application_id' => $a->id, 'participation_request_id' => $id, 'publication_id' => $award->id, 'reason' => $reason, 'created_at' => now()]);
                }
                if (in_array($c->status, ['evaluation', 'results']) || $award) {
                    DB::table('applications')->where('offer_id', $o->id)->where('participation', 'active')->where('eligibility', 'enabled')->update(['selection' => 'under_review']);
                    $after = $this->rankingSnapshot($o->id);
                    $changes = [];
                    foreach ($after['rank'] as $i => $entry) {
                        $j = array_search($entry->id, array_column($before['rank'], 'id'), true);
                        $changes[] = ['application_id' => $entry->id, 'old_position' => $j === false ? null : $j + 1, 'new_position' => $i + 1, 'old_final' => $j === false ? null : $before['rank'][$j]->final, 'new_final' => $entry->final];
                    }
                    $changes[] = ['application_id' => $a->id, 'old_position' => null, 'new_position' => null, 'old_final' => $a->final, 'new_final' => $a->final, 'participation' => $award ? 'resigned' : 'withdrawn'];
                    DB::table('result_revisions')->insert(['offer_id' => $o->id, 'revision' => $o->result_revision + 1, 'participation_request_id' => $id, 'before' => json_encode($before), 'after' => json_encode($after), 'changes' => json_encode($changes), 'created_at' => now()]);
                    DB::table('offers')->where('id', $o->id)->update(['result_revision' => $o->result_revision + 1, 'needs_publication_review' => true]);
                }
            }
            DB::table('participation_requests')->where('id', $id)->update(['status' => $approve ? 'approved' : 'rejected', 'updated_at' => now()]);
            DB::table('participation_decisions')->insert(['participation_request_id' => $id, 'user_id' => $u->id, 'approved' => $approve, 'reason' => $reason, 'created_at' => now()]);
            Selection::audit($u, $c->id, $approve ? 'participation.approved' : 'participation.rejected', 'participation:'.$id, ['status' => 'pending'], ['kind' => $r->kind, 'approved' => $approve], $reason);
        });
    }

    public function choose(User $u, int $id, int $publication, string $choice, int $version, string $reason, string $evidence): void
    {
        Access::transaction($u, function () use ($u, $id, $publication, $choice, $version, $reason, $evidence) {
            [$a,$o,$c] = $this->context($id);
            abort_unless($a->user_id === $u->id, 403);
            $this->evidence($reason, $evidence);
            $pub = self::award($a);
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
            DB::table('award_choices')->insert(['application_id' => $id, 'publication_id' => $publication, 'version' => $version + 1, 'user_id' => $u->id, 'choice' => $choice, 'reason' => $reason, 'evidence' => $evidence, 'created_at' => now()]);
            Selection::audit($u, $c->id, 'award.choice_recorded', 'application:'.$id, ['version' => $version], ['publication' => $publication, 'version' => $version + 1, 'choice' => $choice, 'compatibility' => 'PENDIENTE_VALIDACION_INSTITUCIONAL'], $reason);
        });
    }
}
