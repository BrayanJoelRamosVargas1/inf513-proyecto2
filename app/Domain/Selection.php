<?php

namespace App\Domain;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class Selection
{
    public static function conditionalCriterion(object $offer, string $key): bool
    {
        // Article 7(e) only concerns students who have completed the curriculum.
        return ($offer->requirement_profile ?? null) === 'asignatura' && $key === 'graduacion_mora';
    }

    public static function criterionSatisfied(object $offer, string $key, ?string $decision): bool
    {
        return $decision === 'meets' || ($decision === 'not_applicable' && self::conditionalCriterion($offer, $key));
    }

    public static function row(string $table, int $id, bool $lock = false): object
    {
        $q = DB::table($table)->where('id', $id);
        if ($lock) {
            $q->lockForUpdate();
        }

        return $q->first() ?? abort(404);
    }

    public static function json(mixed $value): array
    {
        return is_array($value) ? $value : json_decode($value ?? '[]', true, 512, JSON_THROW_ON_ERROR);
    }

    private static function lockedApplication(int $id): array
    {
        $application = self::row('applications', $id);
        $offer = self::row('offers', $application->offer_id);
        // Orden único de bloqueo: convocatoria -> oferta/postulación -> nota.
        // Serializa operaciones sensibles de la misma convocatoria para una DEMO de volumen facultativo.
        $call = self::row('calls', $offer->call_id, true);

        $current = self::row('applications', $id, true);
        Participation::active($current);

        return [$current, $offer, $call];
    }

    public static function audit(User $u, ?int $call, string $action, string $entity, mixed $before = null, mixed $after = null, ?string $reason = null): void
    {
        DB::table('audit_events')->insert(['user_id' => $u->id, 'call_id' => $call, 'action' => $action, 'entity' => $entity, 'before' => $before === null ? null : json_encode($before), 'after' => $after === null ? null : json_encode($after), 'reason' => $reason, 'created_at' => now()]);
    }

    private static function demo(object $call): void
    {
        if (config('portal.experience') !== 'training' || $call->mode !== 'DEMO') {
            Scores::fail('Operación oficial o histórica bloqueada. Requiere validación institucional.');
        }
    }

    private static function receiving(object $call): void
    {
        self::demo($call);
        if (! Workspace::receiving($call)) {
            Scores::fail('La recepción no está abierta. El instante exacto de cierre es inclusivo en DEMO.');
        }
    }

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
            self::audit($u, $id, 'call.created', 'call:'.$id, null, $d);

            return $id;
        });
    }

    public function editCall(User $u, int $callId, array $d): void
    {
        Access::transaction($u, function () use ($u, $callId, $d) {
            $call = self::row('calls', $callId, true);
            Access::require($u, $callId, ['authority']);
            self::demo($call);
            if ($call->status !== 'draft') {
                Scores::fail('Solo se edita el borrador. Una convocatoria publicada requiere nueva versión vinculada.');
            }
            DB::table('calls')->where('id', $callId)->update($d + ['version' => $call->version + 1, 'updated_at' => now()]);
            self::audit($u, $callId, 'call.edited', 'call:'.$callId, $call, $d);
        });
    }

    public function saveOffer(User $u, int $callId, array $d, ?int $offerId = null): int
    {
        if (! isset($d['seats']) || filter_var($d['seats'], FILTER_VALIDATE_INT) === false || (int) $d['seats'] < 0) {
            Scores::fail('Indique una cantidad válida de plazas para una oferta operativa.');
        }
        return Access::transaction($u, function () use ($u, $callId, $d, $offerId) {
            $call = self::row('calls', $callId, true);
            Access::require($u, $callId, ['authority']);
            self::demo($call);
            if ($call->status !== 'draft') {
                Scores::fail('La configuración publicada está congelada. Cree una nueva convocatoria/version vinculada.');
            }
            if (DB::table('offers')->where('call_id', $callId)->where('title', $d['title'])->where('modality', $d['modality'])->when($offerId !== null, fn ($q) => $q->where('id', '!=', $offerId))->exists()) {
                Scores::fail('Ya existe una oferta con ese nombre y modalidad.');
            }
            $category = self::row('categories', (int) $d['category_id']);
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
                $before = self::row('offers', $offerId, true);
                abort_unless($before->call_id === $callId, 403);
                if (DB::table('assignments')->where('offer_id', $offerId)->exists()) {
                    Scores::fail('Edite el esquema antes de asignar evaluadores para preservar su responsabilidad.');
                }
                DB::table('offers')->where('id', $offerId)->update($d + ['version' => $before->version + 1, 'updated_at' => now()]);
                $id = $offerId;
            } else {
                $id = DB::table('offers')->insertGetId($d + ['call_id' => $callId, 'created_at' => now(), 'updated_at' => now()]);
            }
            self::audit($u, $callId, $before ? 'offer.edited' : 'offer.created', 'offer:'.$id, $before, $d);

            return $id;
        });
    }

    public function transition(User $u, int $callId, string $next): void
    {
        Access::transaction($u, function () use ($u, $callId, $next) {
            $call = self::row('calls', $callId, true);
            Access::require($u, $callId, ['authority']);
            self::demo($call);
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
                    Scores::weights(self::json($o->weights));
                    if (! array_key_exists('cpd', self::json($o->documents))) {
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
            self::audit($u, $callId, 'call.transition', 'call:'.$callId, ['status' => $call->status], ['status' => $next]);
        });
    }

    public function apply(User $u, int $offerId): int
    {
        abort_unless($u->active && $u->role === 'student', 403);

        return Access::transaction($u, function () use ($u, $offerId) {
            abort_unless($u->role === 'student', 403);
            $o = self::row('offers', $offerId);
            $call = self::row('calls', $o->call_id, true);
            self::receiving($call);
            if (Access::has($u, $call->id, ['authority', 'reviewer', 'evaluator'])) {
                Scores::fail('Tiene un permiso de gestión en esta convocatoria; no puede postular en ella.');
            }
            if (DB::table('applications')->where('offer_id', $offerId)->where('user_id', $u->id)->exists()) {
                Scores::fail('Ya tiene una postulación a esta oferta.');
            }
            // Misma regla que muestra la interfaz (Workspace::applicationBlock): ámbito y límite de asignaturas.
            $subjects = DB::table('applications')->join('offers', 'offers.id', '=', 'applications.offer_id')->where('applications.user_id', $u->id)->where('offers.call_id', $call->id)->where('offers.is_subject', true)->count();
            if (($block = Workspace::applicationBlock($u, $call, $o, $subjects)) !== null) {
                Scores::fail($block);
            }
            $snapshot = ['offer' => (array) $o, 'call_version' => $call->version, 'scale' => '0..100', 'rounding' => $o->rounding, 'mode' => $call->mode];
            $id = DB::table('applications')->insertGetId(['offer_id' => $offerId, 'user_id' => $u->id, 'snapshot' => json_encode($snapshot), 'created_at' => now(), 'updated_at' => now()]);
            self::audit($u, $call->id, 'application.created', 'application:'.$id);

            return $id;
        });
    }

    public function upload(User $u, int $appId, string $kind, UploadedFile $file): int
    {
        $stored = null;
        try {
            return Access::transaction($u, function () use ($u, $appId, $kind, $file, &$stored) {
                [$app,$offer,$call] = self::lockedApplication($appId);
                abort_unless($u->active && $app->user_id === $u->id, 403);
                self::receiving($call);
                if ($app->status !== 'draft') {
                    Scores::fail('El expediente enviado está bloqueado. Subsanación requiere procedimiento validado.');
                }
                if (! array_key_exists($kind, self::json($offer->documents))) {
                    Scores::fail('Documento no solicitado por esta oferta.');
                }
                validator(['file' => $file], ['file' => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png|extensions:pdf,jpg,jpeg,png'])->validate();
                $mime = $file->getMimeType();
                if (! in_array($mime, ['application/pdf', 'image/png', 'image/jpeg'])) {
                    Scores::fail('Tipo de archivo no permitido.');
                }
                $stored = $file->store('applicant-quarantine', 'local');
                $version = (DB::table('documents')->where('application_id', $appId)->where('kind', $kind)->max('version') ?? 0) + 1;
                $id = DB::table('documents')->insertGetId(['application_id' => $appId, 'kind' => $kind, 'version' => $version, 'path' => $stored, 'name' => basename($file->getClientOriginalName()), 'mime' => $mime, 'sha256' => hash_file('sha256', $file->getRealPath()), 'created_at' => now(), 'updated_at' => now()]);
                app(DocumentProcessor::class)->enqueue($id, 'applicant');
                self::audit($u, $call->id, 'document.upload', 'document:'.$id, null, ['kind' => $kind, 'version' => $version]);

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
            [$app,$offer,$call] = self::lockedApplication($id);
            abort_unless($u->active && $app->user_id === $u->id, 403);
            self::receiving($call);
            if ($app->status !== 'draft') {
                Scores::fail('La postulación ya fue enviada.');
            }
            $present = DB::table('documents')->where('application_id', $id)->pluck('kind')->all();
            if (array_diff(array_keys(self::json($offer->documents)), $present)) {
                Scores::fail('Faltan documentos obligatorios.');
            }
            DB::table('applications')->where('id', $id)->update(['status' => 'submitted', 'updated_at' => now()]);
            Participation::snapshot($id, 1, 'submitted', $u->id);
            self::audit($u, $call->id, 'application.submitted', 'application:'.$id, ['status' => 'draft'], ['status' => 'submitted']);
        });
    }

    public function review(User $u, int $id, array $decisions, string $reason): void
    {
        Access::transaction($u, function () use ($u, $id, $decisions, $reason) {
            [$app,$offer,$call] = self::lockedApplication($id);
            Access::require($u, $call->id, ['reviewer']);
            abort_if($app->user_id === $u->id, 403, 'No puede revisar ni habilitar su propia postulación.');
            Participation::legacyReview($id);
            self::demo($call);
            if ($app->status !== 'submitted' || $app->eligibility !== 'pending' || ! in_array($call->status, ['published', 'closed'])) {
                Scores::fail('La revisión no está abierta.');
            }
            $documentChecks = app(ApplicantDocuments::class)->assertDecisions($id, $decisions);
            $criteria = array_merge(array_fill_keys(array_map(fn ($x) => 'doc:'.$x, array_keys(self::json($offer->documents))), ''), self::json($offer->requirements));
            foreach ($decisions as $key => $decision) {
                if (! array_key_exists($key, $criteria) || ! in_array($decision, ['meets', 'fails', 'observed', 'not_applicable'])) {
                    Scores::fail('Criterio o decisión inválidos.');
                }
                if ($decision === 'not_applicable' && (! self::conditionalCriterion($offer, $key) || mb_strlen(trim($reason)) < 10)) {
                    Scores::fail('No aplica requiere un criterio condicional y fundamento documental.');
                }
                $before = DB::table('reviews')->where('application_id', $id)->where('criterion', $key)->first();
                DB::table('reviews')->updateOrInsert(['application_id' => $id, 'criterion' => $key], ['decision' => $decision, 'reason' => $reason, 'user_id' => $u->id, 'updated_at' => now(), 'created_at' => $before?->created_at ?? now()]);
                self::audit($u, $call->id, 'review.recorded', 'application:'.$id, $before, ['criterion' => $key, 'decision' => $decision, 'document_checks' => $documentChecks], $reason);
            }
        });
    }

    public function eligibility(User $u, int $id, bool $enable, string $reason): void
    {
        Access::transaction($u, function () use ($u, $id, $enable, $reason) {
            [$app,$offer,$call] = self::lockedApplication($id);
            Access::require($u, $call->id, ['reviewer']);
            abort_if($app->user_id === $u->id, 403, 'No puede revisar ni habilitar su propia postulación.');
            Participation::legacyReview($id);
            self::demo($call);
            if ($app->status !== 'submitted' || $app->eligibility !== 'pending' || ! in_array($call->status, ['published', 'closed'])) {
                Scores::fail('Decisión de habilitación no disponible.');
            }
            $documentChecks = [];
            if ($enable) {
                $documentChecks = app(ApplicantDocuments::class)->assertEffective($id);
                $keys = array_merge(array_map(fn ($x) => 'doc:'.$x, array_keys(self::json($offer->documents))), array_keys(self::json($offer->requirements)));
                foreach ($keys as $key) {
                    $decision = DB::table('reviews')->where('application_id', $id)->where('criterion', $key)->value('decision');
                    if (! self::criterionSatisfied($offer, $key, $decision)) {
                        Scores::fail('No se cumplen todos los documentos/requisitos con evidencia.');
                    }
                }
            }
            $next = $enable ? 'enabled' : 'disabled';
            DB::table('applications')->where('id', $id)->update(['eligibility' => $next, 'eligibility_reason' => $reason, 'updated_at' => now()]);
            self::audit($u, $call->id, 'eligibility.decided', 'application:'.$id, ['eligibility' => $app->eligibility], ['eligibility' => $next, 'document_checks' => $documentChecks], $reason);
        });
    }

    public function merit(User $u, int $id, string $score, string $reference): void
    {
        Access::transaction($u, function () use ($u, $id, $score, $reference) {
            [$app,$o,$c] = self::lockedApplication($id);
            Access::require($u, $c->id, ['reviewer']);
            abort_if($app->user_id === $u->id, 403, 'No puede revisar ni habilitar su propia postulación.');
            Participation::noAmendment($id);
            self::demo($c);
            if (! in_array($c->status, ['published', 'closed', 'evaluation']) || $app->status !== 'submitted' || $app->merit !== null) {
                Scores::fail('El mérito confirmado requiere rectificación documentada; esta operación es solo registro inicial.');
            }
            if (! DB::table('documents')->where('application_id', $id)->where('kind', 'cpd')->exists()) {
                Scores::fail('Adjunte el formulario CPD de respaldo.');
            }
            $documentChecks = app(ApplicantDocuments::class)->assertEffective($id);
            $score = Scores::value($score, Scores::MERIT_MAX);
            DB::table('applications')->where('id', $id)->update(['merit' => $score, 'cpd_reference' => $reference, 'merit_version' => 1, 'updated_at' => now()]);
            Rectifications::version($id, null, 1, $score, $u->id);
            self::audit($u, $c->id, 'merit.recorded', 'application:'.$id, null, ['merit' => $score, 'reference' => $reference, 'document_checks' => $documentChecks]);
        });
    }

    public function assign(User $u, int $offerId, int $teacher, string $area): void
    {
        Access::transaction($u, function () use ($u, $offerId, $teacher, $area) {
            $o = self::row('offers', $offerId);
            $c = self::row('calls', $o->call_id, true);
            $o = self::row('offers', $offerId, true);
            Access::require($u, $c->id, ['authority']);
            self::demo($c);
            if (! in_array($c->status, ['draft', 'published', 'closed', 'evaluation'])) {
                Scores::fail('La evaluación está cerrada.');
            }
            if (! array_key_exists($area, self::json($o->weights))) {
                Scores::fail('Área ajena al esquema.');
            }
            Access::require(User::findOrFail($teacher), $c->id, ['evaluator']);
            if (DB::table('assignments')->where('offer_id', $offerId)->where('area', $area)->exists()) {
                Scores::fail('Ya existe responsable. La reasignación requiere procedimiento documentado; no se promedian docentes automáticamente.');
            }
            $id = DB::table('assignments')->insertGetId(['offer_id' => $offerId, 'user_id' => $teacher, 'area' => $area, 'created_at' => now(), 'updated_at' => now()]);
            self::audit($u, $c->id, 'evaluator.assigned', 'assignment:'.$id, null, ['user_id' => $teacher, 'area' => $area]);
        });
    }

    public function grade(User $u, int $appId, int $assignmentId, string $score, bool $confirmed = true): void
    {
        Access::transaction($u, function () use ($u, $appId, $assignmentId, $score, $confirmed) {
            [$a,$o,$c] = self::lockedApplication($appId);
            $as = self::row('assignments', $assignmentId);
            Access::require($u, $c->id, ['evaluator']);
            abort_if($a->user_id === $u->id, 403, 'No puede calificar su propia postulación.');
            self::demo($c);
            abort_unless($as->user_id === $u->id && $as->offer_id === $o->id, 403);
            if ($a->eligibility !== 'enabled' || $c->status !== 'evaluation') {
                Scores::fail('Solo habilitados en evaluación abierta.');
            }
            if (DB::table('publications')->where('offer_id', $o->id)->exists() || DB::table('tie_exams')->where('offer_id', $o->id)->exists()) {
                Scores::fail('La evaluación está congelada por publicación o desempate.');
            }
            $before = DB::table('grades')->where('application_id', $appId)->where('assignment_id', $assignmentId)->first();
            if ($before && $before->status !== 'draft') {
                Scores::fail('Nota confirmada: use rectificación autorizada.');
            }
            $documentChecks = app(ApplicantDocuments::class)->assertEffective($appId);
            $score = Scores::value($score);
            $data = ['user_id' => $u->id, 'score' => $score, 'status' => $confirmed ? 'confirmed' : 'draft', 'updated_at' => now()];
            if ($before) {
                $id = $before->id;
                DB::table('grades')->where('id', $id)->update($data);
            } else {
                $id = DB::table('grades')->insertGetId($data + ['application_id' => $appId, 'assignment_id' => $assignmentId, 'user_id' => $u->id, 'created_at' => now()]);
            }
            if ($confirmed) {
                Rectifications::version($appId, $id, 1, $score, $u->id);
            }
            self::audit($u, $c->id, $confirmed ? 'grade.confirmed' : 'grade.draft_saved', 'grade:'.$id, $before ? ['score' => $before->score, 'status' => $before->status] : null, $data + ['document_checks' => $documentChecks]);
        });
    }

    public function correct(User $u, int $gradeId, string $score, string $reason): void
    {
        Access::transaction($u, function () use ($u, $gradeId, $score, $reason) {
            $g = self::row('grades', $gradeId);
            [$a,$o,$c] = self::lockedApplication($g->application_id);
            $g = self::row('grades', $gradeId, true);
            Access::require($u, $c->id, ['authority']);
            self::demo($c);
            if ($g->status === 'draft') {
                Scores::fail('El borrador solo puede completarlo su evaluador asignado.');
            }
            if ($c->status !== 'evaluation' || DB::table('tie_exams')->where('offer_id', $o->id)->exists() || DB::table('publications')->where('offer_id', $o->id)->exists()) {
                Scores::fail('Hay resultados o desempates iniciados: requiere nueva versión del procedimiento.');
            }
            if (mb_strlen(trim($reason)) < 10) {
                Scores::fail('Explique la autorización y motivo de rectificación.');
            }
            $documentChecks = app(ApplicantDocuments::class)->assertEffective($a->id);
            $score = Scores::value($score);
            DB::table('grades')->where('id', $gradeId)->update(['score' => $score, 'status' => 'corrected', 'version' => $g->version + 1, 'updated_at' => now()]);
            DB::table('applications')->where('id', $a->id)->update(['exam' => null, 'final' => null, 'updated_at' => now()]);
            Rectifications::version($a->id, $gradeId, $g->version + 1, $score, $u->id);
            self::audit($u, $c->id, 'grade.corrected', 'grade:'.$gradeId, ['score' => $g->score], ['score' => $score, 'document_checks' => $documentChecks], $reason);
        });
    }

    public function calculate(User $u, int $id): void
    {
        Access::transaction($u, function () use ($u, $id) {
            [$a,$o,$c] = self::lockedApplication($id);
            Access::require($u, $c->id, ['authority']);
            abort_if($a->user_id === $u->id, 403, 'No puede calcular su propia postulación.');
            Access::assertNotApplicant($u, $c->id);
            self::demo($c);
            if ($a->eligibility !== 'enabled' || $c->status !== 'evaluation' || $a->merit === null) {
                Scores::fail('Requiere habilitación, mérito y etapa de evaluación.');
            }
            if (DB::table('publications')->where('offer_id', $o->id)->exists() || DB::table('tie_exams')->where('offer_id', $o->id)->exists()) {
                Scores::fail('Resultados congelados por publicación/desempate.');
            }
            if (! in_array($o->scheme, Scores::CALCULABLE_SCHEMES) || $o->rounding !== 'HALF_UP_FINAL') {
                Scores::fail('Escala/composición/redondeo no definidos.');
            }
            $documentChecks = app(ApplicantDocuments::class)->assertEffective($id);
            $notes = DB::table('grades')->join('assignments', 'assignments.id', '=', 'grades.assignment_id')->where('grades.application_id', $id)->whereIn('grades.status', ['confirmed', 'corrected'])->pluck('grades.score', 'assignments.area')->all();
            $exam = Scores::exam(self::json($o->weights), $notes);
            $final = bcadd(Scores::value($a->merit, Scores::MERIT_MAX), $exam, 2);
            DB::table('applications')->where('id', $id)->update(['exam' => $exam, 'final' => $final, 'updated_at' => now()]);
            self::audit($u, $c->id, 'result.calculated', 'application:'.$id, ['exam' => $a->exam, 'final' => $a->final], ['notes' => $notes, 'weights' => self::json($o->weights), 'exam' => $exam, 'final' => $final, 'scheme' => $o->scheme, 'rounding' => $o->rounding, 'document_checks' => $documentChecks]);
        });
    }

    public function ranking(int $offerId): array
    {
        $offer = self::row('offers', $offerId);
        if ($offer->seats === null) {
            throw ValidationException::withMessages(['seats' => 'Las plazas de esta oferta documental no están especificadas; no se genera clasificación.']);
        }
        if (self::row('calls', $offer->call_id)->mode === 'HISTORICAL' && DB::table('institutional_call_profiles')->where('call_id', $offer->call_id)->exists()) {
            Scores::fail('La fuente histórica contiene ofertas, no resultados oficiales incorporados. No se infieren ganadores, cobertura ni vacantes.');
        }
        $all = DB::table('applications')->join('users', 'users.id', '=', 'applications.user_id')->where('applications.offer_id', $offerId)->where('applications.status', 'submitted')->where('applications.participation', 'active')->select('applications.*', 'users.name')->orderBy('applications.id')->get();
        if ($all->contains(fn ($a) => $a->eligibility === 'pending' || ($a->eligibility === 'enabled' && $a->final === null))) {
            Scores::fail('Hay revisiones o resultados pendientes en esta oferta.');
        }
        $rank = $all->where('eligibility', 'enabled')->sort(fn ($a, $b) => bccomp($b->final, $a->final, 2) ?: $a->id <=> $b->id)->values()->all();
        $tied = [];
        $cut = $offer->seats - $offer->held_vacancies;
        if ($cut > 0 && count($rank) > $cut && bccomp($rank[$cut - 1]->final, $rank[$cut]->final, 2) === 0) {
            $score = $rank[$cut]->final;
            $tied = array_values(array_map(fn ($a) => $a->id, array_filter($rank, fn ($a) => bccomp($a->final, $score, 2) === 0)));
        }

        return ['rank' => $rank, 'tied' => $tied, 'seats' => $cut, 'held_vacancies' => $offer->held_vacancies, 'vacancies' => max($offer->held_vacancies, $offer->seats - count($rank))];
    }

    public function openTie(User $u, int $offerId, array $d): int
    {
        return Access::transaction($u, function () use ($u, $offerId, $d) {
            $o = self::row('offers', $offerId);
            $c = self::row('calls', $o->call_id, true);
            $o = self::row('offers', $offerId, true);
            Access::require($u, $c->id, ['authority']);
            Access::assertNotApplicant($u, $c->id);
            self::demo($c);
            if ((! in_array($c->status, ['evaluation', 'results']) || ($c->status === 'results' && ! $o->needs_publication_review)) || DB::table('publications')->where('offer_id', $offerId)->where('result_revision', $o->result_revision)->exists() || DB::table('tie_exams')->where('offer_id', $offerId)->where('result_revision', $o->result_revision)->exists()) {
                Scores::fail('No se puede abrir otro desempate en este estado.');
            }
            app(ApplicantDocuments::class)->assertOffer($offerId);
            $r = $this->ranking($offerId);
            if (! $r['tied']) {
                Scores::fail('No hay empate que afecte el corte de plazas.');
            }
            $id = DB::table('tie_exams')->insertGetId(['offer_id' => $offerId, 'result_revision' => $o->result_revision, 'status' => 'scheduled', 'participants' => json_encode($r['tied']), 'commission' => json_encode($d['commission']), 'conditions' => $d['conditions'], 'scheduled_at' => $d['scheduled_at'], 'created_at' => now(), 'updated_at' => now()]);
            self::audit($u, $c->id, 'tie.exam_scheduled', 'tie:'.$id, null, ['participants' => $r['tied'], 'conditions' => $d['conditions'], 'commission' => $d['commission']]);

            return $id;
        });
    }

    public function resolveTie(User $u, int $id, array $results, array $winners, string $minutes): void
    {
        Access::transaction($u, function () use ($u, $id, $results, $winners, $minutes) {
            $t = self::row('tie_exams', $id);
            $o = self::row('offers', $t->offer_id);
            $c = self::row('calls', $o->call_id, true);
            $o = self::row('offers', $o->id, true);
            $t = self::row('tie_exams', $id, true);
            Access::require($u, $c->id, ['authority']);
            Access::assertNotApplicant($u, $c->id);
            self::demo($c);
            if ($t->status !== 'scheduled' || ! in_array($c->status, ['evaluation', 'results']) || $t->result_revision !== $o->result_revision) {
                Scores::fail('El nuevo examen no está pendiente.');
            }
            if (now()->lt($t->scheduled_at)) {
                Scores::fail('El nuevo examen aún no se realizó.');
            }
            app(ApplicantDocuments::class)->assertOffer($o->id);
            $participants = self::json($t->participants);
            $keys = array_map('intval', array_keys($results));
            sort($keys);
            $expected = $participants;
            sort($expected);
            if ($keys !== $expected || array_diff($winners, $participants) || count($winners) !== count(array_unique($winners))) {
                Scores::fail('Solo pueden participar los empatados y todos requieren resultado.');
            }
            foreach ($results as $result) {
                if (! is_string($result) || ! trim($result) || mb_strlen($result) > 1000) {
                    Scores::fail('Registre el resultado de cada aspirante según condiciones de comisión.');
                }
            }
            $r = $this->ranking($o->id);
            $above = count(array_filter($r['rank'], fn ($a) => ! in_array($a->id, $participants) && bccomp($a->final, self::row('applications', $participants[0])->final, 2) > 0));
            if (count($winners) !== max(0, $o->seats - $o->held_vacancies - $above)) {
                Scores::fail('La decisión debe respetar las plazas en disputa. Si persiste empate, mantenga pendiente y registre el procedimiento institucional.');
            }
            DB::table('tie_exams')->where('id', $id)->update(['status' => 'resolved', 'results' => json_encode($results), 'winners' => json_encode(array_map('intval', $winners)), 'minutes' => $minutes, 'updated_at' => now()]);
            self::audit($u, $c->id, 'tie.exam_resolved', 'tie:'.$id, null, ['results' => $results, 'winners' => $winners], $minutes);
        });
    }

    public function continueTie(User $u, int $id, array $results, array $d): int
    {
        return Access::transaction($u, function () use ($u, $id, $results, $d) {
            $t = self::row('tie_exams', $id);
            $o = self::row('offers', $t->offer_id);
            $c = self::row('calls', $o->call_id, true);
            Access::require($u, $c->id, ['authority']);
            Access::assertNotApplicant($u, $c->id);
            self::demo($c);
            if ($t->status !== 'scheduled' || ! in_array($c->status, ['evaluation', 'results']) || $t->result_revision !== $o->result_revision || now()->lt($t->scheduled_at)) {
                Scores::fail('La ronda no admite continuidad en este estado.');
            }
            app(ApplicantDocuments::class)->assertOffer($o->id);
            $participants = self::json($t->participants);
            $expected = $participants;
            sort($expected);
            $keys = array_map('intval', array_keys($results));
            sort($keys);
            if ($keys !== $expected || count(array_filter($results, fn ($v) => ! is_string($v) || ! trim($v) || mb_strlen($v) > 1000))) {
                Scores::fail('Registre el resultado de cada participante.');
            }
            if (mb_strlen(trim($d['minutes'] ?? '')) < 20 || mb_strlen(trim($d['conditions'] ?? '')) < 20 || empty($d['commission']) || CarbonImmutable::parse($d['scheduled_at'])->lte(CarbonImmutable::parse($t->scheduled_at))) {
                Scores::fail('Documente la persistencia, nueva comisión/condiciones y fecha posterior.');
            }
            DB::table('tie_exams')->where('id', $id)->update(['status' => 'continued', 'results' => json_encode($results), 'minutes' => $d['minutes'], 'updated_at' => now()]);
            // La comisión declara persistencia; el sistema no inventa una fórmula ni elimina aspirantes.
            $new = DB::table('tie_exams')->insertGetId(['offer_id' => $o->id, 'result_revision' => $o->result_revision, 'round' => $t->round + 1, 'previous_tie_id' => $id, 'status' => 'scheduled', 'participants' => json_encode($participants), 'commission' => json_encode($d['commission']), 'conditions' => $d['conditions'], 'scheduled_at' => $d['scheduled_at'], 'created_at' => now(), 'updated_at' => now()]);
            self::audit($u, $c->id, 'tie.continued', 'tie:'.$id, ['status' => $t->status], ['next_tie' => $new, 'results' => $results], $d['minutes']);

            return $new;
        });
    }

    public function approve(User $u, int $offerId, string $minutes): void
    {
        Access::transaction($u, function () use ($u, $offerId, $minutes) {
            $o = self::row('offers', $offerId);
            $c = self::row('calls', $o->call_id, true);
            $o = self::row('offers', $offerId, true);
            Access::require($u, $c->id, ['authority']);
            Access::assertNotApplicant($u, $c->id);
            self::demo($c);
            if ((! in_array($c->status, ['evaluation', 'results']) || ($c->status === 'results' && ! $o->needs_publication_review)) || DB::table('publications')->where('offer_id', $offerId)->where('result_revision', $o->result_revision)->exists()) {
                Scores::fail('La oferta no admite otra aprobación silenciosa.');
            }
            if (mb_strlen(trim($minutes)) < 10) {
                Scores::fail('Se requiere acta de aprobación DEMO.');
            }
            if (DB::table('correction_requests')->join('applications', 'applications.id', '=', 'correction_requests.application_id')->where('applications.offer_id', $offerId)->where('correction_requests.status', 'pending')->exists()) {
                Scores::fail('Resuelva las rectificaciones pendientes.');
            }
            if (DB::table('participation_requests')->join('applications', 'applications.id', '=', 'participation_requests.application_id')->where('applications.offer_id', $offerId)->where('participation_requests.status', 'pending')->exists() || DB::table('amendments')->join('applications', 'applications.id', '=', 'amendments.application_id')->where('applications.offer_id', $offerId)->whereIn('amendments.status', ['open', 'submitted'])->exists()) {
                Scores::fail('Resuelva solicitudes y subsanaciones antes de publicar.');
            }
            app(ApplicantDocuments::class)->assertOffer($offerId);
            $r = $this->ranking($offerId);
            $winners = array_map(fn ($a) => $a->id, array_slice($r['rank'], 0, $o->seats - $o->held_vacancies));
            if ($r['tied']) {
                $tie = DB::table('tie_exams')->where('offer_id', $offerId)->where('result_revision', $o->result_revision)->where('status', 'resolved')->first();
                if (! $tie) {
                    Scores::fail('Se requiere nuevo examen de desempate y acta de comisión.');
                }
                $winners = array_values(array_diff($winners, $r['tied']));
                $winners = array_merge($winners, self::json($tie->winners));
            }
            if (count($winners) > $o->seats - $o->held_vacancies) {
                Scores::fail('La selección excede las plazas.');
            }
            foreach ($r['rank'] as $a) {
                DB::table('applications')->where('id', $a->id)->update(['selection' => in_array($a->id, $winners) ? 'selected' : 'not_selected', 'updated_at' => now()]);
            }
            $snapshot = ['mode' => 'DEMO', 'offer' => (array) $o, 'ranking' => $this->ranking($offerId)['rank'], 'selected' => $winners, 'approved_at' => now()->toIso8601String()];
            DB::table('publications')->insert(['offer_id' => $offerId, 'version' => (DB::table('publications')->where('offer_id', $offerId)->max('version') ?? 0) + 1, 'result_revision' => $o->result_revision, 'previous_publication_id' => DB::table('publications')->where('offer_id', $offerId)->orderByDesc('version')->value('id'), 'snapshot' => json_encode($snapshot), 'minutes' => $minutes, 'user_id' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('offers')->where('id', $offerId)->update(['needs_publication_review' => false]);
            self::audit($u, $c->id, 'result.approved', 'offer:'.$offerId, null, ['selected' => $winners], $minutes);
        });
    }
}
