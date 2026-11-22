<?php

namespace Tests\Feature;

use App\Domain\DocumentProcessor;
use App\Domain\DocumentSafety;
use App\Domain\DocumentScanner;
use App\Domain\InstitutionalPreparation;
use App\Domain\Selection;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class DocumentProcessorTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private User $reviewer;

    private int $dossier;

    private int $source;

    private string $url;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['document_safety.binary' => '', 'document_safety.database' => '']);
        $this->author = User::factory()->create(['role' => 'authority']);
        $this->reviewer = User::factory()->create(['role' => 'authority']);
        $call = app(Selection::class)->createCall($this->author, ['title' => 'Cuarentena sintética', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'exam_at' => now()->addDays(3), 'scope' => 'UAGRM', 'source' => 'Fixture sin valor institucional']);
        DB::table('memberships')->insert(['call_id' => $call, 'user_id' => $this->reviewer->id, 'role' => 'authority']);
        $this->dossier = app(InstitutionalPreparation::class)->create($this->author, ['call_id' => $call, 'reviewer_id' => $this->reviewer->id, 'title' => 'Fixture']);
        $this->url = '/api/v1/institutional/dossiers/'.$this->dossier;
        $this->source = $this->actingAs($this->author)->post($this->url.'/sources', ['expected_version' => 1, 'reference' => 'MUESTRA', 'gate' => 'G02', 'title' => 'No es normativa real', 'issued_on' => '2026-11-21', 'pages' => '1', 'classification' => 'historical_reference', 'notes' => 'Solo prueba de seguridad.', 'scan_state' => 'clean', 'file' => UploadedFile::fake()->createWithContent('muestra.pdf', file_get_contents(resource_path('demo-documents/cpd.pdf')))], ['Accept' => 'application/json'])->assertCreated()->json('id');
    }

    private function processor(): DocumentProcessor
    {
        return app(DocumentProcessor::class);
    }

    private function task(): object
    {
        return DB::table('institutional_scan_tasks')->where('source_id', $this->source)->first();
    }

    private function scanResult(string $state = 'clean'): array
    {
        return ['state' => $state, 'engine' => 'TEST-ONLY', 'reason' => 'fixture'];
    }

    private function scanner(string $state): void
    {
        $this->app->instance(DocumentScanner::class, new class($state) implements DocumentScanner
        {
            public function __construct(private string $state) {}

            public function scan(string $path): array
            {
                return ['state' => $this->state, 'engine' => 'TEST-ONLY', 'reason' => 'fixture'];
            }
        });
    }

    private function workspace(int $job): string
    {
        $path = Storage::disk('local')->path('institutional-scan-work/job-'.$job);
        if (! is_dir($path)) {
            mkdir($path, 0700, true);
        }
        file_put_contents($path.'/.lock', '');
        file_put_contents($path.'/payload.bin', 'temporary fixture');

        return $path;
    }

    public function test_p01_upload_durably_enqueues_once_and_preserves_quarantine(): void
    {
        $p = $this->processor();
        $task = $this->task();
        $this->assertSame('queued', $task->state);
        $this->assertEquals($task->id, $p->enqueue($this->source));
        $this->assertDatabaseCount('institutional_scan_tasks', 1);
        $this->assertDatabaseCount('institutional_scan_jobs', 1);
        $this->assertDatabaseCount('institutional_scan_events', 1);
        $this->getJson('/api/v1/institutional/sources/'.$this->source.'/download')->assertStatus(423);
        $this->getJson($this->url)->assertOk()->assertJsonPath('readiness.official_activation_allowed', false);
    }

    public function test_p02_current_lease_completes_clean_and_new_request_immediately_blocks(): void
    {
        $this->scanner('clean');
        $p = $this->processor();
        $this->assertSame('clean', $p->runOne()['state']);
        $this->assertSame('completed', $this->task()->state);
        $this->assertSame('clean', app(DocumentSafety::class)->status($this->source)['state']);
        $this->getJson('/api/v1/institutional/sources/'.$this->source.'/download')->assertOk();
        $p->enqueue($this->source);
        $this->assertSame('pending', app(DocumentSafety::class)->status($this->source)['state']);
        $this->assertDatabaseCount('institutional_scan_results', 1);
        $this->assertDatabaseCount('institutional_scan_tasks', 2);
    }

    public function test_p03_errors_retry_with_backoff_then_stop_without_rewriting_attempts(): void
    {
        $p = $this->processor();
        $this->scanner('error');
        for ($i = 1; $i <= 3; $i++) {
            $r = $p->runOne();
            $this->assertSame('error', $r['state']);
            $this->assertSame($i < 3 ? 'queued' : 'failed', $r['task_state']);
            $this->assertEquals($i, $this->task()->attempts);
            $this->assertDatabaseCount('institutional_scan_results', $i);
            if ($i < 3) {
                $this->assertNull($p->claim());
                $this->travel(30 * (2 ** ($i - 1)) + 1)->seconds();
            }
        }
        $this->assertNull($p->claim());
        $this->assertDatabaseCount('institutional_scan_jobs', 3);
        $this->assertEquals(2, DB::table('institutional_scan_events')->where('action', 'retry_scheduled')->count());
        $this->assertSame('error', app(DocumentSafety::class)->status($this->source)['state']);
    }

    public function test_p04_detection_stops_without_automatic_retry(): void
    {
        $this->scanner('blocked');
        $r = $this->processor()->runOne();
        $this->assertSame('blocked', $r['state']);
        $this->assertSame('completed', $r['task_state']);
        $this->assertNull($this->processor()->claim());
        $this->getJson('/api/v1/institutional/sources/'.$this->source.'/download')->assertStatus(423);
    }

    public function test_p05_expired_worker_cannot_release_before_or_after_recovery_and_retry(): void
    {
        $p = $this->processor();
        $old = $p->claim();
        $this->travel(121)->seconds();
        $this->assertSame('ignored', $p->finish($old, $this->scanResult())['state']);
        $this->assertDatabaseCount('institutional_scan_results', 0);
        $this->assertSame('pending', app(DocumentSafety::class)->status($this->source)['state']);
        $this->assertSame(1, $p->recover(true)['recovered']);
        $this->assertDatabaseHas('institutional_scan_results', ['job_id' => $old->job_id, 'state' => 'error', 'reason' => 'worker_abandoned']);
        $this->travel(31)->seconds();
        $new = $p->claim();
        $this->assertNotEquals($old->job_id, $new->job_id);
        $this->assertSame('ignored', $p->finish($old, $this->scanResult())['state']);
        $this->assertSame('pending', app(DocumentSafety::class)->status($this->source)['state']);
        $this->assertSame('clean', $p->finish($new, $this->scanResult())['state']);
        $this->assertEquals(2, DB::table('institutional_scan_events')->where('action', 'late_completion_ignored')->count());
        $this->assertDatabaseCount('institutional_scan_results', 2);
    }

    public function test_p06_recovery_default_is_read_only_and_valid_lease_is_preserved(): void
    {
        $p = $this->processor();
        $lease = $p->claim();
        $this->assertSame(0, $p->recover(true)['recovered']);
        $this->travel(121)->seconds();
        $this->assertSame(1, $p->recover()['expired_leases']);
        $this->assertSame('leased', $this->task()->state);
        $this->assertDatabaseCount('institutional_scan_results', 0);
        $this->assertDatabaseCount('institutional_scan_events', 2);
        $p->recover(true);
        $this->assertSame('queued', $this->task()->state);
        $this->assertNull($this->task()->lease_token);
    }

    public function test_p07_same_task_has_only_one_lease_and_token_fences_completion(): void
    {
        $p = $this->processor();
        $lease = $p->claim();
        $this->assertNull($p->claim($lease->id));
        $wrong = clone $lease;
        $wrong->lease_token = 'another-worker';
        $this->assertSame('ignored', $p->finish($wrong, $this->scanResult())['state']);
        $this->assertDatabaseCount('institutional_scan_results', 0);
        $this->assertSame('clean', $p->finish($lease, $this->scanResult())['state']);
    }

    public function test_p08_cleanup_removes_only_old_unlocked_payload_and_preserves_original_and_marker(): void
    {
        $p = $this->processor();
        $lease = $p->claim();
        $p->finish($lease, $this->scanResult('error'));
        $path = $this->workspace($lease->job_id);
        $source = DB::table('institutional_sources')->find($this->source);
        $hash = hash('sha256', Storage::disk('local')->get($source->path));
        $this->travel(3601)->seconds();
        $this->assertSame(1, $p->cleanup()['eligible']);
        $this->assertFileExists($path.'/payload.bin');
        $this->assertSame(1, $p->cleanup(true)['removed']);
        $this->assertFileDoesNotExist($path.'/payload.bin');
        $this->assertFileExists($path.'/.lock');
        $this->assertSame($hash, hash('sha256', Storage::disk('local')->get($source->path)));
        $this->assertDatabaseHas('institutional_scan_events', ['action' => 'temporary_payload_cleaned']);
        $this->assertSame(0, $p->cleanup(true)['removed']);
    }

    public function test_p09_cleanup_skips_locked_workspace_and_unknown_files(): void
    {
        $p = $this->processor();
        $lease = $p->claim();
        $p->finish($lease, $this->scanResult('error'));
        $path = $this->workspace($lease->job_id);
        $this->travel(3601)->seconds();
        $lock = fopen($path.'/.lock', 'r+b');
        flock($lock, LOCK_EX | LOCK_NB);
        try {
            $this->assertSame(0, $p->cleanup(true)['removed']);
            $this->assertFileExists($path.'/payload.bin');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        file_put_contents($path.'/unknown.txt', 'must preserve');
        $this->assertSame(0, $p->cleanup(true)['removed']);
        $this->assertFileExists($path.'/unknown.txt');
        $this->assertFileExists($path.'/payload.bin');
    }

    public function test_p10_cleanup_never_removes_active_or_unfinished_attempt(): void
    {
        config(['document_processor.cleanup_seconds' => 1]);
        $lease = $this->processor()->claim();
        $path = $this->workspace($lease->job_id);
        $this->travel(2)->seconds();
        $this->assertSame(0, $this->processor()->cleanup(true)['removed']);
        $this->assertFileExists($path.'/payload.bin');
        $this->travel(121)->seconds();
        $this->assertSame(0, $this->processor()->cleanup(true)['removed']);
    }

    public function test_p11_unmanaged_interruption_is_recovered_without_rewriting_history(): void
    {
        $source = DB::table('institutional_sources')->find($this->source);
        $job = DB::table('institutional_scan_jobs')->insertGetId(['source_id' => $source->id, 'sha256' => $source->sha256, 'policy_hash' => DocumentSafety::policyHash(), 'created_at' => now()]);
        $path = $this->workspace($job);
        $this->travel(301)->seconds();
        $p = $this->processor();
        $this->assertSame(1, $p->recoverUnmanaged()['unmanaged_pending']);
        $this->assertDatabaseCount('institutional_scan_results', 0);
        $lock = fopen($path.'/.lock', 'r+b');
        flock($lock, LOCK_EX | LOCK_NB);
        try {
            $this->assertSame(0, $p->recoverUnmanaged(true)['recovered']);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        $this->assertSame(1, $p->recoverUnmanaged(true)['recovered']);
        $this->assertDatabaseHas('institutional_scan_results', ['job_id' => $job, 'state' => 'error']);
        $this->assertDatabaseCount('institutional_scan_jobs', 2);
        $this->assertDatabaseCount('institutional_scan_tasks', 1);
        $lease = $p->claim();
        $this->assertNotEquals($job, $lease->job_id);
        $this->assertSame('pending', app(DocumentSafety::class)->status($this->source)['state']);
    }

    public function test_p12_events_are_append_only_and_never_expose_lease_tokens(): void
    {
        $lease = $this->processor()->claim();
        $this->assertStringNotContainsString($lease->lease_token, DB::table('institutional_scan_events')->get()->toJson());
        foreach (['update', 'delete'] as $operation) {
            try {
                DB::transaction(function () use ($operation) {
                    $q = DB::table('institutional_scan_events');
                    $operation === 'update' ? $q->update(['action' => 'tampered']) : $q->delete();
                });
                $this->fail('Immutable evidence was changed');
            } catch (QueryException $e) {
                $this->assertStringContainsString('append-only', strtolower($e->getMessage()));
            }
        }
        $this->assertDatabaseCount('institutional_scan_events', 2);
    }

    public function test_p13_invalid_worker_lease_is_rejected_before_claim(): void
    {
        config(['document_processor.lease_seconds' => 60]);
        $this->expectException(\LogicException::class);
        $this->processor()->claim();
    }

    public function test_p14_cli_maintenance_defaults_to_simulation_and_status_is_aggregate(): void
    {
        $this->artisan('ficct:scan-maintain')->expectsOutputToContain('"apply":false')->assertSuccessful();
        $this->artisan('ficct:scan-queue-status')->expectsOutputToContain('"queued"')->assertSuccessful();
        $this->assertDatabaseCount('institutional_scan_results', 0);
        $this->assertDatabaseCount('institutional_scan_events', 1);
    }

    private function supersededLegacy(string $laterState): array
    {
        $this->scanner('clean');
        $this->processor()->runOne();
        $source = DB::table('institutional_sources')->find($this->source);
        $old = DB::table('institutional_scan_jobs')->insertGetId(['source_id' => $source->id, 'sha256' => $source->sha256, 'policy_hash' => DocumentSafety::policyHash(), 'created_at' => now()]);
        $this->travel(301)->seconds();
        $new = DB::table('institutional_scan_jobs')->insertGetId(['source_id' => $source->id, 'sha256' => $source->sha256, 'policy_hash' => DocumentSafety::policyHash(), 'created_at' => now()]);
        if ($laterState !== 'pending') {
            DB::table('institutional_scan_results')->insert(['job_id' => $new, 'state' => $laterState, 'engine' => 'TEST-ONLY', 'reason' => 'fixture', 'finished_at' => now()]);
        }

        return [$old, $new];
    }

    public function test_p15_superseded_legacy_attempt_does_not_invalidate_a_newer_clean_scan(): void
    {
        [$old, $new] = $this->supersededLegacy('clean');
        $before = DB::table('institutional_scan_results')->where('job_id', $new)->first();
        $jobs = DB::table('institutional_scan_jobs')->count();
        $this->processor()->recoverUnmanaged(true);
        $this->assertSame('clean', app(DocumentSafety::class)->status($this->source)['state']);
        $this->assertDatabaseCount('institutional_scan_jobs', $jobs);
        $this->assertDatabaseCount('institutional_scan_tasks', 1);
        $this->assertEquals($before, DB::table('institutional_scan_results')->where('job_id', $new)->first());
        $this->assertDatabaseHas('institutional_scan_results', ['job_id' => $old, 'state' => 'error', 'reason' => 'unmanaged_attempt_superseded']);
        $this->assertDatabaseHas('institutional_scan_events', ['job_id' => $old, 'action' => 'unmanaged_superseded']);
        $this->getJson('/api/v1/institutional/sources/'.$this->source.'/download')->assertOk();
    }

    public function test_p16_superseded_legacy_attempt_preserves_a_newer_blocked_result(): void
    {
        [$old, $new] = $this->supersededLegacy('blocked');
        $r = $this->processor()->recoverUnmanaged(true);
        $this->assertSame(0, $r['recovered']);
        $this->assertSame(1, $r['superseded']);
        $this->assertSame('blocked', app(DocumentSafety::class)->status($this->source)['state']);
        $this->assertEquals($new, app(DocumentSafety::class)->status($this->source)['job_id']);
        $this->assertDatabaseCount('institutional_scan_jobs', 3);
        $this->getJson('/api/v1/institutional/sources/'.$this->source.'/download')->assertStatus(423);
    }

    public function test_p17_superseded_legacy_attempt_does_not_duplicate_a_newer_pending_job(): void
    {
        [$old, $new] = $this->supersededLegacy('pending');
        $this->processor()->recoverUnmanaged(true);
        $this->assertSame('pending', app(DocumentSafety::class)->status($this->source)['state']);
        $this->assertEquals($new, app(DocumentSafety::class)->status($this->source)['job_id']);
        $this->assertDatabaseCount('institutional_scan_jobs', 3);
        $this->assertDatabaseCount('institutional_scan_results', 2);
        $this->assertDatabaseCount('institutional_scan_tasks', 1);
    }

    public function test_p18_superseded_recovery_dry_run_is_read_only_and_application_is_idempotent(): void
    {
        $this->supersededLegacy('clean');
        $before = DB::table('institutional_scan_events')->get()->toJson();
        $p = $this->processor();
        $this->assertSame(1, $p->recoverUnmanaged()['unmanaged_pending']);
        $this->assertSame($before, DB::table('institutional_scan_events')->get()->toJson());
        $this->assertDatabaseCount('institutional_scan_results', 2);
        $this->assertSame(1, $p->recoverUnmanaged(true)['superseded']);
        $this->assertSame(0, $p->recoverUnmanaged(true)['superseded']);
        $this->assertDatabaseCount('institutional_scan_events', 4);
        $this->assertDatabaseCount('institutional_scan_results', 3);
    }

    public function test_p19_queue_health_distinguishes_overdue_ready_from_scheduled_retries_without_private_data(): void
    {
        $p = $this->processor();
        $this->travel(301)->seconds();
        $s = $p->queueStatus();
        $this->assertSame(1, $s['ready']);
        $this->assertSame(0, $s['delayed']);
        $this->assertGreaterThanOrEqual(301, $s['oldest_ready_seconds']);
        $this->assertContains('QUEUE_OVERDUE', $s['warnings']);
        $lease = $p->claim();
        $this->assertNotContains('QUEUE_OVERDUE', $p->queueStatus()['warnings']);
        $this->travel(121)->seconds();
        $this->assertContains('LEASES_EXPIRED', $p->queueStatus()['warnings']);
        $p->recover(true);
        $s = $p->queueStatus();
        $this->assertSame(1, $s['delayed']);
        $this->assertSame(0, $s['oldest_ready_seconds']);
        $this->assertSame('ok', $s['health']);
        $json = json_encode($s);
        foreach ([$lease->lease_token, $this->author->email, $this->author->name, 'institutional-quarantine/', 'sha256'] as $private) {
            $this->assertStringNotContainsString($private, $json);
        }
    }

    public function test_p20_queue_health_does_not_keep_alerting_about_superseded_historical_failures(): void
    {
        $this->scanner('error');
        $p = $this->processor();
        for ($i = 0; $i < 3; $i++) {
            $p->runOne();
            $this->travel(61)->seconds();
        }
        $this->assertSame(1, $p->queueStatus()['failed_current']);
        $this->assertContains('CURRENT_TASKS_FAILED', $p->queueStatus()['warnings']);
        $p->enqueue($this->source);
        $this->assertSame(0, $p->queueStatus()['failed_current']);
        $this->assertSame('ok', $p->queueStatus()['health']);
        $this->assertDatabaseHas('institutional_scan_tasks', ['state' => 'failed']);
        $this->scanner('clean');
        $p->runOne();
        $this->assertSame('ok', $p->queueStatus()['health']);
        $this->assertDatabaseCount('institutional_scan_results', 4);
    }

    public function test_p21_unmanaged_health_ignores_obsolete_pending_attempts_but_reports_a_current_abandoned_one(): void
    {
        $this->supersededLegacy('pending');
        $p = $this->processor();
        $this->assertSame(0, $p->queueStatus()['unmanaged_current']);
        $this->travel(301)->seconds();
        $this->assertSame(1, $p->queueStatus()['unmanaged_current']);
        $this->assertContains('UNMANAGED_CURRENT_PENDING', $p->queueStatus()['warnings']);
        $p->recoverUnmanaged(true);
        $this->assertSame(0, $p->queueStatus()['unmanaged_current']);
        $this->assertDatabaseCount('institutional_scan_jobs', 4);
    }

    public function test_p22_queue_health_cli_check_signals_attention_without_changing_queue_or_evidence(): void
    {
        $this->artisan('ficct:scan-queue-status --check')->expectsOutputToContain('"health":"ok"')->assertSuccessful();
        $this->travel(301)->seconds();
        $before = DB::table('institutional_scan_tasks')->get()->toJson();
        $this->artisan('ficct:scan-queue-status --check')->expectsOutputToContain('QUEUE_OVERDUE')->assertExitCode(1);
        $this->artisan('ficct:scan-queue-status')->expectsOutputToContain('QUEUE_OVERDUE')->assertSuccessful();
        $this->assertSame($before, DB::table('institutional_scan_tasks')->get()->toJson());
        $this->assertDatabaseCount('institutional_scan_jobs', 1);
        $this->assertDatabaseCount('institutional_scan_results', 0);
        $this->assertDatabaseCount('institutional_scan_events', 1);
    }
}
