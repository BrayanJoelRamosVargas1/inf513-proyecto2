<?php

namespace App\Domain;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Ciclo de vida de la convocatoria: creación, edición, ofertas y transición de etapas. Reglas y bloqueos idénticos a los de Selection, que expone la misma API. */
final class CallLifecycle
{
    public function createCall(User $u, array $d): int
    {
        abort_unless(config('portal.experience') === 'training', 422, 'Las operaciones de entrenamiento requieren una instalación separada.');
        abort_unless($u->active && $u->role === 'authority', 403);

        return Access::transaction($u, function () use ($u, $d) {
            abort_unless($u->role === 'authority', 403);
            $id = DB::table('calls')->insertGetId(array_merge($d, ['mode' => 'DEMO', 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]));
            if ($u->role === 'authority') {
                DB::table('memberships')->insert(['call_id' => $id, 'user_id' => $u->id, 'role' => 'authority']);
            }
            Selection::audit($u, $id, 'call.created', 'call:'.$id, null, $d);

            return $id;
        });
    }

    public function editCall(User $u, int $callId, array $d): void
    {
        Access::transaction($u, function () use ($u, $callId, $d) {
            $call = Selection::row('calls', $callId, true);
            Access::require($u, $callId, ['authority']);
            Selection::demo($call);
            if ($call->status !== 'draft') {
                Scores::fail('Solo se edita el borrador. Una convocatoria publicada requiere nueva versión vinculada.');
            }
            DB::table('calls')->where('id', $callId)->update($d + ['version' => $call->version + 1, 'updated_at' => now()]);
            Selection::audit($u, $callId, 'call.edited', 'call:'.$callId, $call, $d);
        });
    }

    public function saveOffer(User $u, int $callId, array $d, ?int $offerId = null): int
    {
        if (! isset($d['seats']) || filter_var($d['seats'], FILTER_VALIDATE_INT) === false || (int) $d['seats'] < 0) {
            Scores::fail('Indique una cantidad válida de plazas para una oferta operativa.');
        }
        return Access::transaction($u, function () use ($u, $callId, $d, $offerId) {
            $call = Selection::row('calls', $callId, true);
            Access::require($u, $callId, ['authority']);
            Selection::demo($call);
            if ($call->status !== 'draft') {
                Scores::fail('La configuración publicada está congelada. Cree una nueva convocatoria/version vinculada.');
            }
            if (DB::table('offers')->where('call_id', $callId)->where('title', $d['title'])->where('modality', $d['modality'])->when($offerId !== null, fn ($q) => $q->where('id', '!=', $offerId))->exists()) {
                Scores::fail('Ya existe una oferta con ese nombre y modalidad.');
            }
            $category = Selection::row('categories', (int) $d['category_id']);
            if (! $category->active) {
                Scores::fail('Tipo de auxiliar no habilitado.');
            }
            if (! empty($d['requirement_profile'])) {
                $modality = app(InstitutionalCatalogue::class)->modality((object) ['is_subject' => ! empty($d['is_subject']), 'category_id' => $d['category_id']]);
                abort_unless($d['requirement_profile'] === $modality, 422, 'El perfil de requisitos no corresponde al régimen y tipo de oferta.');
                $profile = app(InstitutionalCatalogue::class)->definitions()['profiles']['convocatoria-01']['modalities'][$modality];
                unset($d['requirements']['activo'], $d['requirements']['regimen']);
                $d['requirements'] = $profile['requirements'] + $d['requirements'];
                $d['documents'] = $profile['documents'] + $d['documents'];
            }
            $d['weights'] = json_encode(Scores::weights($d['weights']));
            foreach (['documents', 'requirements'] as $key) {
                if (! $d[$key]) {
                    Scores::fail('Defina documentos y requisitos.');
                }$d[$key] = json_encode($d[$key]);
            }
            $before = null;
            if ($offerId !== null) {
                $before = Selection::row('offers', $offerId, true);
                abort_unless($before->call_id === $callId, 403);
                if (DB::table('assignments')->where('offer_id', $offerId)->exists()) {
                    Scores::fail('Edite el esquema antes de asignar evaluadores para preservar su responsabilidad.');
                }
                DB::table('offers')->where('id', $offerId)->update($d + ['version' => $before->version + 1, 'updated_at' => now()]);
                $id = $offerId;
            } else {
                $id = DB::table('offers')->insertGetId($d + ['call_id' => $callId, 'created_at' => now(), 'updated_at' => now()]);
            }
            Selection::audit($u, $callId, $before ? 'offer.edited' : 'offer.created', 'offer:'.$id, $before, $d);

            return $id;
        });
    }

    public function transition(User $u, int $callId, string $next): void
    {
        Access::transaction($u, function () use ($u, $callId, $next) {
            $call = Selection::row('calls', $callId, true);
            Access::require($u, $callId, ['authority']);
            Selection::demo($call);
            $allowed = ['draft' => 'published', 'published' => 'closed', 'closed' => 'evaluation', 'evaluation' => 'results', 'results' => 'archived'];
            if (($allowed[$call->status] ?? null) !== $next) {
                Scores::fail('Transición de convocatoria inválida.');
            }
            if (in_array($next, ['evaluation', 'results', 'archived']) && (DB::table('amendments')->join('applications', 'applications.id', '=', 'amendments.application_id')->join('offers', 'offers.id', '=', 'applications.offer_id')->where('offers.call_id', $callId)->whereIn('amendments.status', ['open', 'submitted'])->exists() || DB::table('participation_requests')->join('applications', 'applications.id', '=', 'participation_requests.application_id')->join('offers', 'offers.id', '=', 'applications.offer_id')->where('offers.call_id', $callId)->where('participation_requests.status', 'pending')->exists())) {
                Scores::fail('Resuelva subsanaciones y solicitudes de participación antes de cerrar la etapa.');
            }
            if ($next === 'evaluation') {
                $pending = DB::table('applications')->join('offers', 'offers.id', '=', 'applications.offer_id')->where('offers.call_id', $callId)->where('applications.status', 'submitted')->where('applications.participation', 'active')->where('applications.eligibility', 'pending')->count();
                if ($pending > 0) {
                    Scores::fail('Hay '.$pending.' postulación(es) enviada(s) sin revisar. Revíselas y habilítelas o inhabilítelas antes de abrir la evaluación: después ya no se pueden revisar.');
                }
            }
            if ($next === 'published') {
                if (CarbonImmutable::parse($call->closes_at)->lte(CarbonImmutable::parse($call->opens_at)) || CarbonImmutable::parse($call->exam_at)->lte(CarbonImmutable::parse($call->closes_at))) {
                    Scores::fail('Calendario incoherente. Se conserva la fuente sin corregir automáticamente.');
                }
                $offers = DB::table('offers')->where('call_id', $callId)->get();
                if ($offers->isEmpty()) {
                    Scores::fail('Agregue al menos una oferta.');
                }
                foreach ($offers as $o) {
                    if (! in_array($o->scheme, Scores::CALCULABLE_SCHEMES) || $o->rounding !== 'HALF_UP_FINAL') {
                        Scores::fail('La oferta «'.$o->title.'» tiene una composición de nota pendiente o no calculable. Defina el esquema antes de publicar: una oferta publicada no puede editarse.');
                    }
                    Scores::weights(Selection::json($o->weights));
                    if (! array_key_exists('cpd', Selection::json($o->documents))) {
                        Scores::fail('La oferta «'.$o->title.'» debe solicitar el respaldo de méritos con la clave cpd. Este sistema lo exige para registrar méritos; corrija el borrador antes de publicar.');
                    }
                }
            }
            if (in_array($next, ['results', 'archived']) && DB::table('offers')->where('call_id', $callId)->where('needs_publication_review', true)->exists()) {
                Scores::fail('Existen revisiones pendientes de publicación.');
            }
            if ($next === 'results') {
                foreach (DB::table('offers')->where('call_id', $callId)->get() as $o) {
                    if (! DB::table('publications')->where('offer_id', $o->id)->exists()) {
                        Scores::fail('Cada oferta necesita resultado aprobado antes del cierre.');
                    }
                }
            }
            DB::table('calls')->where('id', $callId)->update(['status' => $next, 'updated_at' => now()]);
            Selection::audit($u, $callId, 'call.transition', 'call:'.$callId, ['status' => $call->status], ['status' => $next]);
        });
    }
}
