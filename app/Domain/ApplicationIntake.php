<?php

namespace App\Domain;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Recepción de postulaciones: postular, cargar documentos y enviar el expediente. Reglas y bloqueos idénticos a los de Selection, que expone la misma API. */
final class ApplicationIntake
{
    public function apply(User $u, int $offerId): int
    {
        abort_unless($u->active && $u->role === 'student', 403);

        return Access::transaction($u, function () use ($u, $offerId) {
            abort_unless($u->role === 'student', 403);
            $o = Selection::row('offers', $offerId);
            $call = Selection::row('calls', $o->call_id, true);
            Selection::receiving($call);
            if (Access::has($u, $call->id, ['authority', 'reviewer', 'evaluator'])) {
                Scores::fail('Tiene un permiso de gestión en esta convocatoria; no puede postular en ella.');
            }
            if (DB::table('applications')->where('offer_id', $offerId)->where('user_id', $u->id)->exists()) {
                Scores::fail('Ya tiene una postulación a esta oferta.');
            }
            // Misma regla que muestra la interfaz (Workspace::applicationBlock): ámbito y límite de asignaturas.
            $subjects = DB::table('applications')->join('offers', 'offers.id', '=', 'applications.offer_id')->where('applications.user_id', $u->id)->where('offers.call_id', $call->id)->where('offers.is_subject', true)->where('applications.participation', 'active')->count();
            if (($block = Workspace::applicationBlock($u, $call, $o, $subjects)) !== null) {
                Scores::fail($block);
            }
            $snapshot = [
                'offer' => (array) $o,
                'call_version' => $call->version,
                'scale' => '0..100',
                'rounding' => $o->rounding,
                'mode' => $call->mode,
            ];
            $id = DB::table('applications')->insertGetId([
                'offer_id' => $offerId,
                'user_id' => $u->id,
                'snapshot' => json_encode($snapshot),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            Selection::audit($u, $call->id, 'application.created', 'application:'.$id);

            return $id;
        });
    }

    public function upload(User $u, int $appId, string $kind, UploadedFile $file): int
    {
        $stored = null;
        try {
            return Access::transaction($u, function () use ($u, $appId, $kind, $file, &$stored) {
                [$app,$offer,$call] = Selection::lockedApplication($appId);
                abort_unless($u->active && $app->user_id === $u->id, 403);
                Selection::receiving($call);
                if ($app->status !== 'draft') {
                    Scores::fail('El expediente enviado está bloqueado. Subsanación requiere procedimiento validado.');
                }
                if (! array_key_exists($kind, Selection::json($offer->documents))) {
                    Scores::fail('Documento no solicitado por esta oferta.');
                }
                validator(['file' => $file], ['file' => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png|extensions:pdf,jpg,jpeg,png'])->validate();
                $mime = $file->getMimeType();
                if (! in_array($mime, ['application/pdf', 'image/png', 'image/jpeg'])) {
                    Scores::fail('Tipo de archivo no permitido.');
                }
                $stored = $file->store('applicant-quarantine', 'local');
                $version = (DB::table('documents')->where('application_id', $appId)->where('kind', $kind)->max('version') ?? 0) + 1;
                $id = DB::table('documents')->insertGetId([
                    'application_id' => $appId,
                    'kind' => $kind,
                    'version' => $version,
                    'path' => $stored,
                    'name' => basename($file->getClientOriginalName()),
                    'mime' => $mime,
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                app(DocumentProcessor::class)->enqueue($id, 'applicant');
                Selection::audit($u, $call->id, 'document.upload', 'document:'.$id, null, ['kind' => $kind, 'version' => $version]);

                return $id;
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
            [$app,$offer,$call] = Selection::lockedApplication($id);
            abort_unless($u->active && $app->user_id === $u->id, 403);
            Selection::receiving($call);
            if ($app->status !== 'draft') {
                Scores::fail('La postulación ya fue enviada.');
            }
            $present = DB::table('documents')->where('application_id', $id)->pluck('kind')->all();
            if (array_diff(array_keys(Selection::json($offer->documents)), $present)) {
                Scores::fail('Faltan documentos obligatorios.');
            }
            DB::table('applications')->where('id', $id)->update(['status' => 'submitted', 'updated_at' => now()]);
            Participation::snapshot($id, 1, 'submitted', $u->id);
            Selection::audit($u, $call->id, 'application.submitted', 'application:'.$id, ['status' => 'draft'], ['status' => 'submitted']);
        });
    }
}
