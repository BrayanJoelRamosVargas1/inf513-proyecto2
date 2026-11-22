<?php

namespace Tests\Feature;

use App\Domain\DocumentProcessor;
use App\Domain\DocumentSafety;
use App\Domain\DocumentScanner;
use App\Domain\InstitutionalPreparation;
use App\Domain\Participation;
use App\Domain\Rectifications;
use App\Domain\Selection;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class ApplicantDocumentSafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $authority;

    private User $reviewer;

    private User $evaluator;

    private int $call;

    private int $offer;

    private int $application;

    private int $document;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->student = User::factory()->create();
        $this->authority = User::factory()->create(['role' => 'authority']);
        $this->reviewer = User::factory()->create(['role' => 'staff']);
        $this->evaluator = User::factory()->create(['role' => 'staff']);
        $s = app(Selection::class);
        $this->call = $s->createCall($this->authority, ['title' => 'Documentos legibles', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'exam_at' => now()->addDays(2), 'scope' => 'UAGRM', 'source' => 'Fixture sintético']);
        foreach ([[$this->reviewer, 'reviewer'], [$this->evaluator, 'evaluator']] as [$u, $role]) {
            DB::table('memberships')->insert(['user_id' => $u->id, 'call_id' => $this->call, 'role' => $role]);
        }
        $category = DB::table('categories')->insertGetId(['name' => 'Tipo documental', 'regime' => 'docencia', 'active' => true]);
        $this->offer = $s->saveOffer($this->authority, $this->call, ['category_id' => $category, 'title' => 'Oferta documental', 'seats' => 1, 'is_subject' => false, 'modality' => 'Presencial', 'documents' => ['cpd' => 'CPD'], 'requirements' => ['activo' => 'Activo'], 'weights' => ['Software' => '100'], 'scheme' => 'DEMO_AREAS_100', 'source' => 'Fixture sintético']);
        $s->transition($this->authority, $this->call, 'published');
        $this->application = $s->apply($this->student, $this->offer);
        $this->document = $s->upload($this->student, $this->application, 'cpd', new UploadedFile(resource_path('demo-documents/cpd.pdf'), 'respaldo.pdf', 'application/pdf', null, true));
    }

    private function scanner(string $state = 'clean'): void
    {
        $this->app->instance(DocumentScanner::class, new class($state) implements DocumentScanner
        {
            public function __construct(private string $state) {}

            public function scan(string $path): array
            {
                return ['state' => $this->state, 'engine' => 'TEST-APPLICANT', 'reason' => 'synthetic_fixture'];
            }
        });
    }

    private function runScan(string $state = 'clean'): array
    {
        $this->scanner($state);

        return app(DocumentProcessor::class)->runOne();
    }

    private function scanStatus(?int $id = null): array
    {
        return app(DocumentSafety::class)->status($id ?? $this->document, 'applicant');
    }

    private function submit(): void
    {
        app(Selection::class)->submit($this->student, $this->application);
    }

    private function reviewResponse(int $expected = 422): void
    {
        $this->actingAs($this->reviewer)->postJson('/api/v1/applications/'.$this->application.'/revision', ['decisions' => ['doc:cpd' => 'meets', 'activo' => 'meets'], 'reason' => 'Revisión de un expediente sintético completo.'])->assertStatus($expected);
    }

    public function test_d01_student_can_submit_pending_but_cannot_download_or_approve(): void
    {
        $this->assertSame('pending', $this->scanStatus()['state']);
        $this->assertDatabaseHas('institutional_scan_tasks', ['document_id' => $this->document, 'source_id' => null, 'state' => 'queued']);
        $this->submit();
        $this->reviewResponse();
        $this->assertDatabaseCount('reviews', 0);
        $this->getJson('/api/v1/documents/'.$this->document)->assertStatus(423);
        $this->actingAs($this->student)->get('/documentos/'.$this->document.'/detalle')->assertOk()->assertSee('Documento en cuarentena')->assertDontSee('Descargar original');
        $this->assertDatabaseHas('applications', ['id' => $this->application, 'status' => 'submitted', 'eligibility' => 'pending']);
    }

    public function test_d02_clean_scan_allows_exact_original_only_for_owner_and_scoped_reviewer(): void
    {
        $this->assertSame('clean', $this->runScan()['state']);
        foreach ([$this->student, $this->reviewer] as $u) {
            $r = $this->actingAs($u)->getJson('/api/v1/documents/'.$this->document)->assertOk();
            $this->assertSame(file_get_contents(resource_path('demo-documents/cpd.pdf')), $r->getContent());
        }
        foreach ([$this->authority, $this->evaluator, User::factory()->create(['role' => 'admin']), User::factory()->create()] as $u) {
            $this->actingAs($u)->getJson('/api/v1/documents/'.$this->document)->assertForbidden();
        }
        $this->submit();
        $this->reviewResponse(200);
        app(Selection::class)->eligibility($this->reviewer, $this->application, true, 'Documentación sintética comprobada.');
        $this->assertDatabaseHas('applications', ['id' => $this->application, 'eligibility' => 'enabled']);
    }

    public function test_d03_detection_blocks_without_destroying_file_or_history(): void
    {
        $this->runScan('blocked');
        $this->submit();
        $this->reviewResponse();
        $doc = DB::table('documents')->find($this->document);
        $this->assertSame($doc->sha256, hash('sha256', Storage::disk('local')->get($doc->path)));
        $this->assertDatabaseHas('institutional_scan_results', ['state' => 'blocked']);
        $this->actingAs($this->student)->get('/documentos/'.$doc->id)->assertStatus(423);
        $this->assertDatabaseCount('documents', 1);
    }

    public function test_d04_scanner_failure_retries_then_fails_closed(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->runScan('error');
            $this->travel(61)->seconds();
        }
        $this->assertSame('error', $this->scanStatus()['state']);
        $this->assertDatabaseHas('institutional_scan_tasks', ['document_id' => $this->document, 'state' => 'failed', 'attempts' => 3]);
        $this->actingAs($this->student)->getJson('/api/v1/documents/'.$this->document)->assertStatus(423);
        $this->assertDatabaseCount('institutional_scan_results', 3);
    }

    public function test_d05_tampering_after_clean_is_detected_again_at_review_and_download(): void
    {
        $this->runScan();
        $this->submit();
        Storage::disk('local')->put(DB::table('documents')->find($this->document)->path, 'changed fixture');
        $this->actingAs($this->student)->getJson('/api/v1/documents/'.$this->document)->assertStatus(409);
        $this->reviewResponse(409);
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_d06_missing_after_clean_cannot_be_used_for_a_new_approval(): void
    {
        $this->runScan();
        $this->submit();
        Storage::disk('local')->delete(DB::table('documents')->find($this->document)->path);
        $this->actingAs($this->student)->get('/documentos/'.$this->document)->assertNotFound();
        $this->reviewResponse(409);
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_d07_expired_scan_and_changed_policy_return_to_quarantine(): void
    {
        $this->runScan();
        $this->travel(25)->hours();
        $this->assertSame('expired', $this->scanStatus()['state']);
        $this->actingAs($this->student)->get('/documentos/'.$this->document)->assertStatus(423);
        config(['document_safety.timeout_seconds' => 61]);
        $this->assertSame('pending', $this->scanStatus()['state']);
    }

    public function test_d08_new_version_is_pending_and_does_not_reuse_old_clean_scan(): void
    {
        $this->runScan();
        $old = DB::table('documents')->find($this->document);
        $new = app(Selection::class)->upload($this->student, $this->application, 'cpd', new UploadedFile(resource_path('demo-documents/cpd.pdf'), 'segunda.pdf', null, null, true));
        $this->assertSame('clean', $this->scanStatus()['state']);
        $this->assertSame('pending', $this->scanStatus($new)['state']);
        $this->submit();
        $this->reviewResponse();
        $this->assertEquals($old, DB::table('documents')->find($this->document));
        $this->assertDatabaseCount('documents', 2);
        $this->assertDatabaseCount('institutional_scan_tasks', 2);
    }

    private function amendment(): int
    {
        $this->runScan();
        $this->submit();
        app(Selection::class)->review($this->reviewer, $this->application, ['doc:cpd' => 'observed', 'activo' => 'meets'], 'Observación sintética que requiere otra versión.');

        return app(Participation::class)->open($this->reviewer, $this->application, ['cpd'], now()->addHours(2)->toIso8601String(), 'Nueva versión autorizada en DEMO.', 'Referencia sintética de autorización.');
    }

    public function test_d09_amendment_waits_for_its_own_scan_and_preserves_submitted_snapshot(): void
    {
        $m = $this->amendment();
        $s = app(Participation::class);
        $before = DB::table('dossier_snapshots')->orderBy('id')->get()->toJson();
        $new = $s->upload($this->student, $m, 'cpd', new UploadedFile(resource_path('demo-documents/cpd.pdf'), 'correccion.pdf', null, null, true));
        $s->submit($this->student, $m);
        $data = ['decisions' => ['doc:cpd' => 'meets', 'activo' => 'meets'], 'reason' => 'Revisión nueva de la versión corregida.'];
        $this->actingAs($this->reviewer)->postJson('/api/v1/amendments/'.$m.'/review', $data)->assertStatus(422);
        $this->assertDatabaseHas('amendments', ['id' => $m, 'status' => 'submitted']);
        $this->assertSame('pending', $this->scanStatus($new)['state']);
        $this->runScan();
        $this->postJson('/api/v1/amendments/'.$m.'/review', $data)->assertOk();
        $this->assertDatabaseHas('applications', ['id' => $this->application, 'eligibility' => 'enabled']);
        $this->assertSame($before, DB::table('dossier_snapshots')->orderBy('id')->limit(count(json_decode($before)))->get()->toJson());
    }

    public function test_d10_foreign_upload_and_forged_scan_state_cannot_release_a_document(): void
    {
        $data = ['kind' => 'cpd', 'file' => new UploadedFile(resource_path('demo-documents/cpd.pdf'), 'muestra.pdf', null, null, true), 'scan_state' => 'clean'];
        $this->actingAs(User::factory()->create())->post('/api/v1/applications/'.$this->application.'/archivo', $data, ['Accept' => 'application/json'])->assertForbidden();
        $this->actingAs($this->student)->post('/api/v1/applications/'.$this->application.'/archivo', $data, ['Accept' => 'application/json'])->assertOk();
        $new = DB::table('documents')->max('id');
        $this->assertSame('pending', $this->scanStatus($new)['state']);
        $this->postJson('/api/v1/documents/'.$new.'/scan', ['state' => 'clean'])->assertNotFound();
        $this->assertDatabaseCount('institutional_scan_results', 0);
    }

    public function test_d11_overlapping_institutional_and_applicant_ids_do_not_share_scan_results(): void
    {
        $r = User::factory()->create(['role' => 'authority']);
        DB::table('memberships')->insert(['call_id' => $this->call, 'user_id' => $r->id, 'role' => 'authority']);
        $s = app(InstitutionalPreparation::class);
        $d = $s->create($this->authority, ['call_id' => $this->call, 'reviewer_id' => $r->id, 'title' => 'Fuente sintética']);
        DB::select("SELECT setval(pg_get_serial_sequence('institutional_sources', 'id'), ?, false)", [$this->document]);
        $source = $s->source($this->authority, $d, ['expected_version' => 1, 'reference' => 'MUESTRA', 'gate' => 'G02', 'title' => 'Sin valor normativo', 'issued_on' => '2026-11-22', 'pages' => '1', 'classification' => 'historical_reference', 'notes' => 'Fuente sintética.'], new UploadedFile(resource_path('demo-documents/cpd.pdf'), 'fuente.pdf', null, null, true));
        $this->assertEquals($source, $this->document);
        $this->scanner();
        app(DocumentSafety::class)->scan($this->document, 'applicant');
        $this->assertSame('clean', $this->scanStatus()['state']);
        $this->assertSame('pending', app(DocumentSafety::class)->status($source)['state']);
        $this->scanner('blocked');
        app(DocumentSafety::class)->scan($source);
        $this->assertSame('clean', $this->scanStatus()['state']);
        $this->assertSame('blocked', app(DocumentSafety::class)->status($source)['state']);
    }

    public function test_d12_expired_applicant_lease_rejects_late_clean_result_and_recovers(): void
    {
        $p = app(DocumentProcessor::class);
        $old = $p->claim();
        $this->travel(121)->seconds();
        $this->assertSame('ignored', $p->finish($old, ['state' => 'clean', 'engine' => 'TEST', 'reason' => 'fixture'])['state']);
        $this->assertSame('pending', $this->scanStatus()['state']);
        $p->recover(true);
        $this->travel(31)->seconds();
        $this->assertSame('clean', $this->runScan()['state']);
        $this->assertDatabaseCount('institutional_scan_results', 2);
    }

    public function test_d13_superseded_legacy_applicant_attempt_does_not_invalidate_later_clean(): void
    {
        $this->runScan();
        $doc = DB::table('documents')->find($this->document);
        $old = DB::table('institutional_scan_jobs')->insertGetId(['document_id' => $doc->id, 'sha256' => $doc->sha256, 'policy_hash' => DocumentSafety::policyHash(), 'created_at' => now()]);
        $this->travel(301)->seconds();
        $this->scanner();
        app(DocumentSafety::class)->scan($doc->id, 'applicant');
        $r = app(DocumentProcessor::class)->recoverUnmanaged(true);
        $this->assertSame(1, $r['superseded']);
        $this->assertSame('clean', $this->scanStatus()['state']);
        $this->assertDatabaseCount('institutional_scan_jobs', 3);
        $this->assertDatabaseHas('institutional_scan_events', ['document_id' => $doc->id, 'job_id' => $old, 'action' => 'unmanaged_superseded']);
    }

    public function test_d14_document_versions_cannot_be_overwritten_or_deleted(): void
    {
        foreach (['update', 'delete'] as $operation) {
            try {
                DB::transaction(function () use ($operation) {
                    $q = DB::table('documents')->where('id', $this->document);
                    $operation === 'update' ? $q->update(['version' => 99]) : $q->delete();
                });
                $this->fail('Document history changed');
            } catch (QueryException $e) {
                $this->assertStringContainsString('append-only', $e->getMessage());
            }
        }
        $this->assertDatabaseCount('documents', 1);
    }

    public function test_d15_scan_ledger_requires_exactly_one_kind_of_target(): void
    {
        $doc = DB::table('documents')->find($this->document);
        try {
            DB::transaction(fn () => DB::table('institutional_scan_jobs')->insert(['sha256' => $doc->sha256, 'policy_hash' => DocumentSafety::policyHash(), 'created_at' => now()]));
            $this->fail('Targetless scan accepted');
        } catch (QueryException $e) {
            $this->assertStringContainsString('one_target', $e->getMessage());
        }
        $this->assertDatabaseCount('institutional_scan_jobs', 1);
    }

    public function test_d16_legacy_document_without_scan_is_blocked_and_can_be_explicitly_enqueued(): void
    {
        Storage::disk('local')->put('legacy/muestra.pdf', file_get_contents(resource_path('demo-documents/cpd.pdf')));
        $id = DB::table('documents')->insertGetId(['application_id' => $this->application, 'kind' => 'cpd', 'version' => 2, 'path' => 'legacy/muestra.pdf', 'name' => 'anterior.pdf', 'mime' => 'application/pdf', 'sha256' => hash_file('sha256', resource_path('demo-documents/cpd.pdf')), 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($this->student)->get('/documentos/'.$id)->assertStatus(423);
        $task = app(DocumentProcessor::class)->enqueue($id, 'applicant');
        $this->scanner();
        app(DocumentProcessor::class)->runOne($task);
        $this->get('/documentos/'.$id)->assertOk();
        $this->assertDatabaseHas('documents', ['id' => $id, 'path' => 'legacy/muestra.pdf']);
    }

    public function test_d17_api_exposes_security_summary_only_to_existing_private_document_roles(): void
    {
        $r = $this->actingAs($this->student)->getJson('/api/v1/applications/'.$this->application)->assertOk();
        $security = $r->json('documents.0.security');
        $this->assertSame('pending', $security['state']);
        $this->assertEqualsCanonicalizing(['state', 'download_allowed', 'finished_at'], array_keys($security));
        foreach (['sha256', 'path', 'lease_token', 'job_id'] as $key) {
            $this->assertArrayNotHasKey($key, $r->json('documents.0'));
        }
        $this->actingAs($this->authority)->getJson('/api/v1/applications/'.$this->application)->assertOk()->assertJsonCount(0, 'documents');
    }

    public function test_d18_clean_scan_does_not_restore_revoked_reviewer_access(): void
    {
        $this->runScan();
        DB::table('memberships')->where('call_id', $this->call)->where('user_id', $this->reviewer->id)->update(['revoked_at' => now()]);
        $this->actingAs($this->reviewer)->getJson('/api/v1/documents/'.$this->document)->assertForbidden();
        $this->get('/documentos/'.$this->document.'/detalle')->assertForbidden();
    }

    public function test_d19_new_scan_while_reading_prevents_review_commit(): void
    {
        $this->runScan();
        $this->submit();
        $disk = Storage::disk('local');
        $id = $this->document;
        $proxy = new class($disk, $id)
        {
            private bool $started = false;

            public function __construct(private $disk, private $id) {}

            public function exists($path)
            {
                return $this->disk->exists($path);
            }

            public function readStream($path)
            {
                if (! $this->started) {
                    $this->started = true;
                    app(DocumentProcessor::class)->enqueue($this->id, 'applicant');
                }

                return $this->disk->readStream($path);
            }
        };
        Storage::shouldReceive('disk')->with('local')->andReturn($proxy);
        $this->reviewResponse();
        $this->assertDatabaseCount('reviews', 0);
        $this->assertDatabaseHas('applications', ['id' => $this->application, 'eligibility' => 'pending']);
    }

    private function enabledForEvaluation(): int
    {
        $this->runScan();
        $this->submit();
        $s = app(Selection::class);
        $s->review($this->reviewer, $this->application, ['doc:cpd' => 'meets', 'activo' => 'meets'], 'Revisión sintética completa del expediente.');
        $s->eligibility($this->reviewer, $this->application, true, 'Habilitación académica sintética registrada.');
        $s->merit($this->reviewer, $this->application, '20', 'CPD sintético');
        $s->assign($this->authority, $this->offer, $this->evaluator->id, 'Software');
        $s->transition($this->authority, $this->call, 'closed');
        $s->transition($this->authority, $this->call, 'evaluation');

        return DB::table('assignments')->where('offer_id', $this->offer)->value('id');
    }

    public function test_d21_rescan_after_enable_blocks_new_grade_without_revoking_academic_decision(): void
    {
        $assignment = $this->enabledForEvaluation();
        app(DocumentProcessor::class)->enqueue($this->document, 'applicant');
        $this->actingAs($this->evaluator)->postJson('/api/v1/applications/'.$this->application.'/nota', ['assignment_id' => $assignment, 'score' => '80', 'intent' => 'confirm'])->assertStatus(422);
        $this->assertDatabaseCount('grades', 0);
        $this->assertDatabaseHas('applications', ['id' => $this->application, 'eligibility' => 'enabled']);
    }

    public function test_d22_rescan_after_grading_blocks_calculation_and_preserves_confirmed_grade(): void
    {
        $assignment = $this->enabledForEvaluation();
        app(Selection::class)->grade($this->evaluator, $this->application, $assignment, '80');
        $before = DB::table('grades')->orderBy('id')->get()->toJson();
        app(DocumentProcessor::class)->enqueue($this->document, 'applicant');
        $this->actingAs($this->authority)->postJson('/api/v1/applications/'.$this->application.'/calcular')->assertStatus(422);
        $this->assertSame($before, DB::table('grades')->orderBy('id')->get()->toJson());
        $this->assertDatabaseHas('applications', ['id' => $this->application, 'eligibility' => 'enabled', 'final' => null]);
    }

    public function test_d20_prior_academic_review_cannot_enable_after_a_new_scan_is_requested(): void
    {
        $this->runScan();
        $this->submit();
        $this->reviewResponse(200);
        app(DocumentProcessor::class)->enqueue($this->document, 'applicant');
        $this->actingAs($this->reviewer)->postJson('/api/v1/applications/'.$this->application.'/habilitacion', ['enabled' => '1', 'reason' => 'Habilitación solicitada en la prueba.'])->assertStatus(422);
        $this->assertDatabaseHas('applications', ['id' => $this->application, 'eligibility' => 'pending']);
        $this->assertDatabaseHas('reviews', ['application_id' => $this->application, 'criterion' => 'doc:cpd', 'decision' => 'meets']);
    }

    public static function unavailableValidation(): array
    {
        return array_combine(['pending', 'blocked', 'error', 'expired', 'policy', 'missing', 'tampered'], array_map(fn ($state) => [$state], ['pending', 'blocked', 'error', 'expired', 'policy', 'missing', 'tampered']));
    }

    #[DataProvider('unavailableValidation')]
    public function test_d23_unavailable_validation_blocks_draft_confirmation_and_calculation(string $state): void
    {
        $assignment = $this->enabledForEvaluation();
        app(Selection::class)->grade($this->evaluator, $this->application, $assignment, '70', false);
        $before = DB::table('applications')->find($this->application);
        $history = DB::table('grades')->get()->toJson();
        if (in_array($state, ['pending', 'blocked', 'error'])) {
            app(DocumentProcessor::class)->enqueue($this->document, 'applicant');
            if ($state !== 'pending') {
                $this->runScan($state);
            }
        } elseif ($state === 'expired') {
            $this->travel(25)->hours();
        } elseif ($state === 'policy') {
            config(['document_safety.timeout_seconds' => 61]);
        } else {
            $path = DB::table('documents')->find($this->document)->path;
            $state === 'missing' ? Storage::disk('local')->delete($path) : Storage::disk('local')->put($path, 'alteración sintética');
        }
        $code = in_array($state, ['missing', 'tampered']) ? 409 : 422;
        $this->actingAs($this->evaluator)->postJson('/api/v1/applications/'.$this->application.'/nota', ['assignment_id' => $assignment, 'score' => '80', 'intent' => 'confirm'])->assertStatus($code);
        $this->actingAs($this->authority)->postJson('/api/v1/applications/'.$this->application.'/calcular')->assertStatus($code)->assertJsonPath('message', $state === 'missing' ? 'La fuente no está disponible para comprobar su integridad.' : ($state === 'tampered' ? 'La fuente no coincide con su huella registrada.' : 'Documento en cuarentena: falta un análisis válido antes de aprobarlo.'));
        $this->assertEquals($before, DB::table('applications')->find($this->application));
        $this->assertSame($history, DB::table('grades')->get()->toJson());
        foreach ([$this->evaluator, $this->authority, $this->student] as $user) {
            $r = $this->actingAs($user)->getJson('/api/v1/applications/'.$this->application)->assertOk()->assertJsonPath('document_validation', ['state' => 'pending', 'ready' => false])->assertJsonPath('can_grade', false)->assertJsonPath('can_calculate', false);
            $this->assertSame('enabled', $r->json('application.eligibility'));
        }
    }

    public function test_d24_new_clean_scan_restores_technical_readiness_without_new_academic_decision(): void
    {
        $assignment = $this->enabledForEvaluation();
        $reviews = DB::table('reviews')->get()->toJson();
        app(DocumentProcessor::class)->enqueue($this->document, 'applicant');
        $this->runScan();
        $this->actingAs($this->evaluator)->getJson('/api/v1/applications/'.$this->application)->assertOk()->assertJsonPath('document_validation.ready', true)->assertJsonPath('can_grade', true)->assertJsonCount(0, 'documents')->assertJsonCount(0, 'reviews');
        $this->actingAs($this->authority)->getJson('/api/v1/applications/'.$this->application)->assertOk()->assertJsonPath('can_grade', false)->assertJsonCount(0, 'documents');
        app(Selection::class)->grade($this->evaluator, $this->application, $assignment, '80');
        app(Selection::class)->calculate($this->authority, $this->application);
        $this->assertDatabaseHas('applications', ['id' => $this->application, 'eligibility' => 'enabled', 'final' => '68.00']);
        $this->assertSame($reviews, DB::table('reviews')->get()->toJson());
        $this->assertDatabaseCount('institutional_scan_results', 2);
        $audit = DB::table('audit_events')->where('action', 'result.calculated')->orderByDesc('id')->first();
        $this->assertNotEmpty(Selection::json($audit->after)['document_checks']);
    }

    public function test_d25_rescan_blocks_new_publication_without_changing_existing_result(): void
    {
        $assignment = $this->enabledForEvaluation();
        $s = app(Selection::class);
        $s->grade($this->evaluator, $this->application, $assignment, '80');
        $s->calculate($this->authority, $this->application);
        $before = DB::table('applications')->find($this->application);
        app(DocumentProcessor::class)->enqueue($this->document, 'applicant');
        try {
            $s->approve($this->authority, $this->offer, 'Acta sintética de aprobación.');
            $this->fail('Publicación con validación pendiente.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertEquals($before, DB::table('applications')->find($this->application));
        $this->assertDatabaseCount('publications', 0);
        $this->assertCount(1, $s->ranking($this->offer)['rank']);
    }

    public function test_d26_rescan_does_not_rewrite_a_historical_publication_or_enable_unauthorized_access(): void
    {
        $assignment = $this->enabledForEvaluation();
        $s = app(Selection::class);
        $s->grade($this->evaluator, $this->application, $assignment, '80');
        $s->calculate($this->authority, $this->application);
        $s->approve($this->authority, $this->offer, 'Acta sintética de aprobación previa.');
        $before = DB::table('publications')->get()->toJson();
        $app = DB::table('applications')->find($this->application);
        app(DocumentProcessor::class)->enqueue($this->document, 'applicant');
        $this->runScan('blocked');
        $this->assertEquals($app, DB::table('applications')->find($this->application));
        $this->assertSame($before, DB::table('publications')->get()->toJson());
        $this->actingAs(User::factory()->create())->getJson('/api/v1/applications/'.$this->application)->assertForbidden();
        $this->actingAs($this->authority)->get('/postulaciones/'.$this->application)->assertOk()->assertSee('Validación documental pendiente')->assertSee('resultados históricos se conservan');
    }

    public function test_d27_rectification_approval_cannot_bypass_document_validation_but_rejection_is_allowed(): void
    {
        $assignment = $this->enabledForEvaluation();
        app(Selection::class)->grade($this->evaluator, $this->application, $assignment, '80');
        $grade = DB::table('grades')->first();
        $r = app(Rectifications::class);
        $id = $r->request($this->evaluator, $this->application, $grade->id, '85', 'Propuesta sintética de rectificación.', 'Evidencia sintética de rectificación.');
        app(DocumentProcessor::class)->enqueue($this->document, 'applicant');
        try {
            $r->decide($this->authority, $id, true, 'Decisión sintética de rectificación.');
            $this->fail('Rectificación aprobada durante cuarentena.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertEquals($grade, DB::table('grades')->first());
        $this->assertDatabaseHas('correction_requests', ['id' => $id, 'status' => 'pending']);
        $r->decide($this->authority, $id, false, 'Rechazo documentado sin alterar notas.');
        $this->assertDatabaseHas('correction_requests', ['id' => $id, 'status' => 'rejected']);
    }

    public function test_d28_merit_registration_requires_current_documents_even_before_enable(): void
    {
        $this->runScan();
        $this->submit();
        app(DocumentProcessor::class)->enqueue($this->document, 'applicant');
        $this->actingAs($this->reviewer)->postJson('/api/v1/applications/'.$this->application.'/meritos', ['score' => '20', 'reference' => 'CPD sintético'])->assertStatus(422);
        $this->assertDatabaseHas('applications', ['id' => $this->application, 'merit' => null]);
        $this->assertDatabaseCount('score_versions', 0);
    }

    public function test_d29_rescan_during_grade_byte_check_rolls_back_new_grade(): void
    {
        $assignment = $this->enabledForEvaluation();
        $disk = Storage::disk('local');
        $id = $this->document;
        $proxy = new class($disk, $id)
        {
            private bool $started = false;

            public function __construct(private $disk, private $id) {}

            public function exists($path)
            {
                return $this->disk->exists($path);
            }

            public function readStream($path)
            {
                if (! $this->started) {
                    $this->started = true;
                    app(DocumentProcessor::class)->enqueue($this->id, 'applicant');
                }

                return $this->disk->readStream($path);
            }
        };
        Storage::shouldReceive('disk')->with('local')->andReturn($proxy);
        $this->actingAs($this->evaluator)->postJson('/api/v1/applications/'.$this->application.'/nota', ['assignment_id' => $assignment, 'score' => '80', 'intent' => 'confirm'])->assertStatus(422)->assertJsonPath('message', 'El análisis cambió durante la revisión.');
        $this->assertDatabaseCount('grades', 0);
        $this->assertDatabaseHas('applications', ['id' => $this->application, 'eligibility' => 'enabled']);
    }
}
