<?php

namespace App\Domain;

use App\Domain\Concerns\ParticipationContext;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Subsanaciones versionadas del expediente: abrir ventana, cargar, enviar, revisar y vencer. */
final class Amendments
{
    use ParticipationContext;

    public function open(User $u, int $app, array $kinds, string $deadline, string $reason, string $evidence): int
    {
        return Access::transaction($u, function () use ($u, $app, $kinds, $deadline, $reason, $evidence) {
            [$a,$o,$c] = $this->context($app);
            Access::require($u, $c->id, ['reviewer']);
            abort_if($a->user_id === $u->id, 403);
            Participation::active($a);
            Participation::noAmendment($app);
            $this->evidence($reason, $evidence);
            $deadline = CarbonImmutable::parse($deadline, config('app.timezone'));
            if ($a->status !== 'submitted' || ! in_array($a->eligibility, ['pending', 'disabled']) || ! in_array($c->status, ['published', 'closed']) || $deadline->lte(now())) {
                Scores::fail('Requiere expediente observado/no habilitado, antes de evaluar y un plazo futuro explícito.');
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
                Participation::snapshot($app, $a->dossier_version, 'pre_amendment_review', $u->id, Selection::json($prior->snapshot)['documents']);
            }
            $id = DB::table('amendments')->insertGetId([
                'application_id' => $app,
                'base_version' => $a->dossier_version,
                'allowed_kinds' => json_encode(array_values(array_unique($kinds))),
                'deadline' => $deadline->toIso8601String(),
                'opened_by' => $u->id,
                'reason' => $reason,
                'evidence' => $evidence,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('applications')->where('id', $app)->update([
                'eligibility' => 'pending',
                'eligibility_reason' => 'Subsanación pendiente; no constituye habilitación.',
            ]);
            Selection::audit($u, $c->id, 'amendment.opened', 'amendment:'.$id, null, [
                'kinds' => $kinds,
                'deadline' => $deadline->toIso8601String(),
                'base_version' => $a->dossier_version,
            ], $reason);

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
                Participation::active($a);
                abort_unless($a->user_id === $u->id, 403);
                if ($m->status !== 'open' || now()->gt($m->deadline) || $a->dossier_version !== $m->base_version || ! in_array($kind, Selection::json($m->allowed_kinds))) {
                    Scores::fail('Subsanación cerrada, vencida, obsoleta o documento ajeno a lo autorizado.');
                }
                validator(['file' => $file], ['file' => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png|extensions:pdf,jpg,jpeg,png'])->validate();
                if (! in_array($file->getMimeType(), ['application/pdf', 'image/png', 'image/jpeg'])) {
                    Scores::fail('Formato no permitido.');
                }
                $stored = $file->store('applicant-quarantine', 'local');
                $version = (DB::table('documents')->where('application_id', $a->id)->where('kind', $kind)->max('version') ?? 0) + 1;
                $doc = DB::table('documents')->insertGetId([
                    'application_id' => $a->id,
                    'amendment_id' => $id,
                    'kind' => $kind,
                    'version' => $version,
                    'path' => $stored,
                    'name' => basename($file->getClientOriginalName()),
                    'mime' => $file->getMimeType(),
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                app(DocumentProcessor::class)->enqueue($doc, 'applicant');
                Selection::audit($u, $c->id, 'amendment.document_uploaded', 'document:'.$doc, null, [
                    'amendment' => $id,
                    'version' => $version,
                    'kind' => $kind,
                ]);

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
            Participation::active($a);
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
            Participation::snapshot($a->id, $a->dossier_version + 1, 'submitted', $u->id, $docs->values()->all());
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
            Participation::active($a);
            if ($m->status !== 'submitted' || $a->dossier_version !== $m->base_version + 1 || mb_strlen(trim($reason)) < 10) {
                Scores::fail('Revisión no disponible o sin motivo.');
            }
            $keys = array_merge(array_map(fn ($k) => 'doc:'.$k, array_keys(Selection::json($o->documents))), array_keys(Selection::json($o->requirements)));
            if (array_diff($keys, array_keys($decisions)) || array_diff(array_keys($decisions), $keys) || array_diff(array_values($decisions), ['meets', 'fails', 'observed', 'not_applicable'])) {
                Scores::fail('Repita la revisión completa de documentos y requisitos.');
            }
            $documentChecks = app(ApplicantDocuments::class)->assertDecisions($a->id, $decisions);
            $before = DB::table('reviews')->where('application_id', $a->id)->get();
            foreach ($decisions as $key => $value) {
                if ($value === 'not_applicable' && ! Selection::conditionalCriterion($o, $key)) {
                    Scores::fail('Este criterio obligatorio no admite No aplica.');
                }
                DB::table('reviews')->updateOrInsert(['application_id' => $a->id, 'criterion' => $key], [
                    'decision' => $value,
                    'reason' => $reason,
                    'user_id' => $u->id,
                    'created_at' => $before->firstWhere('criterion', $key)?->created_at ?? now(),
                    'updated_at' => now(),
                ]);
            }
            $enabled = collect($decisions)->every(fn ($value, $key) => Selection::criterionSatisfied($o, $key, $value));
            DB::table('applications')->where('id', $a->id)->update([
                'eligibility' => $enabled ? 'enabled' : 'disabled',
                'eligibility_reason' => $reason,
            ]);
            DB::table('amendments')->where('id', $id)->update(['status' => 'reviewed', 'updated_at' => now()]);
            $snap = DB::table('dossier_snapshots')->where('application_id', $a->id)->orderByDesc('id')->first();
            Participation::snapshot($a->id, $a->dossier_version, 'reviewed', $u->id, Selection::json($snap->snapshot)['documents']);
            Selection::audit($u, $c->id, 'amendment.reviewed', 'amendment:'.$id, $before, [
                'decisions' => $decisions,
                'eligibility' => $enabled ? 'enabled' : 'disabled',
                'version' => $a->dossier_version,
                'document_checks' => $documentChecks,
            ], $reason);
        });
    }

    public function expire(User $u, int $id, string $reason): void
    {
        Access::transaction($u, function () use ($u, $id, $reason) {
            $m = Selection::row('amendments', $id);
            [$a,$o,$c] = $this->context($m->application_id);
            Access::require($u, $c->id, ['reviewer']);
            abort_if($a->user_id === $u->id, 403, 'No puede decidir sobre su propia subsanación.');
            if ($m->status !== 'open' || now()->lte($m->deadline) || mb_strlen(trim($reason)) < 10) {
                Scores::fail('Solo una ventana vencida y no enviada puede cerrarse por vencimiento.');
            }
            DB::table('amendments')->where('id', $id)->update(['status' => 'expired', 'updated_at' => now()]);
            DB::table('applications')->where('id', $a->id)->update(['eligibility' => 'disabled', 'eligibility_reason' => $reason]);
            $previous = DB::table('dossier_snapshots')->where('application_id', $a->id)->orderByDesc('id')->first();
            Participation::snapshot($a->id, $a->dossier_version, 'expired_window_'.$id, $u->id, Selection::json($previous->snapshot)['documents']);
            Selection::audit($u, $c->id, 'amendment.expired', 'amendment:'.$id, ['status' => 'open'], ['status' => 'expired'], $reason);
        });
    }
}
