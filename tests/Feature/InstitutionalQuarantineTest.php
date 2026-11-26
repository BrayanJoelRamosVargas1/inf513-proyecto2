<?php

namespace Tests\Feature;

use App\Domain\ClamAvScanner;
use App\Domain\DocumentSafety;
use App\Domain\DocumentScanner;
use App\Domain\InstitutionalPreparation;
use App\Domain\Selection;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class InstitutionalQuarantineTest extends TestCase
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
        $call = app(Selection::class)->createCall($this->author, [
            'title' => 'Cuarentena sintética',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'exam_at' => now()->addDays(3),
            'scope' => 'UAGRM',
            'source' => 'Fixture sin valor institucional',
        ]);
        DB::table('memberships')->insert(['call_id' => $call, 'user_id' => $this->reviewer->id, 'role' => 'authority']);
        $this->dossier = app(InstitutionalPreparation::class)->create($this->author, [
            'call_id' => $call,
            'reviewer_id' => $this->reviewer->id,
            'title' => 'Fixture',
        ]);
        $this->url = '/api/v1/institutional/dossiers/'.$this->dossier;
        $this->source = $this->actingAs($this->author)->post($this->url.'/sources', [
            'expected_version' => 1,
            'reference' => 'MUESTRA',
            'gate' => 'G02',
            'title' => 'No es normativa real',
            'issued_on' => '2026-11-21',
            'pages' => '1',
            'classification' => 'historical_reference',
            'notes' => 'Solo prueba de seguridad.',
            'scan_state' => 'clean',
            'file' => UploadedFile::fake()->createWithContent('muestra.pdf', file_get_contents(resource_path('demo-documents/cpd.pdf'))),
        ], ['Accept' => 'application/json'])->assertCreated()->json('id');
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

    private function download(int $expected)
    {
        return $this->getJson('/api/v1/institutional/sources/'.$this->source.'/download')->assertStatus($expected);
    }

    public function test_q01_pending_file_cannot_be_downloaded_or_submitted_and_web_cannot_release_it(): void
    {
        $this->download(423);
        $this->postJson($this->url.'/submission', ['expected_version' => 2])->assertUnprocessable();
        $this->getJson($this->url)->assertOk()->assertJsonPath('readiness.sources.0.security.state', 'pending')->assertJsonPath('readiness.official_activation_allowed', false);
        $this->postJson('/api/v1/institutional/sources/'.$this->source.'/scan', ['state' => 'clean'])->assertNotFound();
        $this->assertDatabaseCount('institutional_scan_results', 0);
        $source = DB::table('institutional_sources')->find($this->source);
        $this->assertStringStartsWith('institutional-quarantine/', $source->path);
    }

    public function test_q02_clean_fixture_releases_only_the_exact_hashed_bytes_and_not_official_gates(): void
    {
        $this->scanner('clean');
        $this->assertSame('clean', app(DocumentSafety::class)->scan($this->source)['state']);
        $r = $this->download(200);
        $this->assertSame(file_get_contents(resource_path('demo-documents/cpd.pdf')), $r->getContent());
        $this->getJson($this->url)->assertOk()->assertJsonPath('readiness.gates.1.state', 'DOCUMENTED_UNVERIFIED')->assertJsonPath('readiness.official_activation_allowed', false);
        $other = User::factory()->create();
        $this->actingAs($other);
        $this->download(403);
    }

    public function test_q03_blocked_and_error_rescans_override_previous_clean_result(): void
    {
        foreach (['clean', 'blocked', 'error'] as $state) {
            $this->scanner($state);
            $this->assertSame($state, app(DocumentSafety::class)->scan($this->source)['state']);
            $this->download($state === 'clean' ? 200 : 423);
        }
        $this->assertDatabaseCount('institutional_scan_jobs', 4); // Includes durable upload request.
        $this->assertDatabaseCount('institutional_scan_results', 3);
    }

    public function test_q04_a_slow_old_clean_completion_cannot_override_a_new_pending_or_blocked_job(): void
    {
        $source = DB::table('institutional_sources')->find($this->source);
        $fields = ['source_id' => $this->source, 'sha256' => $source->sha256, 'policy_hash' => DocumentSafety::policyHash(), 'created_at' => now()];
        $old = DB::table('institutional_scan_jobs')->insertGetId($fields);
        $new = DB::table('institutional_scan_jobs')->insertGetId($fields);
        DB::table('institutional_scan_results')->insert([
            'job_id' => $old,
            'state' => 'clean',
            'engine' => 'TEST-ONLY',
            'reason' => 'fixture',
            'finished_at' => now(),
        ]);
        $this->download(423);
        $this->assertSame('pending', app(DocumentSafety::class)->status($this->source)['state']);
        DB::table('institutional_scan_results')->insert([
            'job_id' => $new,
            'state' => 'blocked',
            'engine' => 'TEST-ONLY',
            'reason' => 'fixture',
            'finished_at' => now(),
        ]);
        $this->download(423);
        $this->assertSame('blocked', app(DocumentSafety::class)->status($this->source)['state']);
    }

    public function test_q05_scan_records_are_append_only(): void
    {
        app(DocumentSafety::class)->scan($this->source);
        foreach (['institutional_scan_jobs', 'institutional_scan_results'] as $table) {
            foreach (['update', 'delete'] as $operation) {
                try {
                    DB::transaction(fn () => $operation === 'delete' ? DB::table($table)->delete() : DB::table($table)->update([
                        $table === 'institutional_scan_jobs' ? 'sha256' : 'state' => 'altered',
                    ]));
                    $this->fail('Evidencia de análisis modificada.');
                } catch (QueryException $e) {
                    $this->assertStringContainsString('append-only', $e->getMessage());
                }
            }
        }
    }

    public function test_q06_tampering_during_scan_is_detected_and_temporary_bytes_are_removed(): void
    {
        $source = DB::table('institutional_sources')->find($this->source);
        $this->app->instance(DocumentScanner::class, new class($source->path) implements DocumentScanner
        {
            public function __construct(private string $original) {}

            public function scan(string $path): array
            {
                Storage::disk('local')->put($this->original, 'Documento alterado durante análisis');

                return ['state' => 'clean', 'engine' => 'TEST-ONLY', 'reason' => 'fixture'];
            }
        });
        $this->assertSame('error', app(DocumentSafety::class)->scan($this->source)['state']);
        $this->download(423);
        $this->assertSame([], Storage::disk('local')->files('institutional-scan-work'));
    }

    public function test_q07_cli_records_missing_scanner_without_releasing_or_leaking_paths(): void
    {
        $this->artisan('ficct:scan-institutional-source', ['source_id' => $this->source])->assertExitCode(1);
        $this->assertSame('error', app(DocumentSafety::class)->status($this->source)['state']);
        $this->assertDatabaseHas('institutional_scan_results', ['reason' => 'scanner_not_configured']);
        $this->download(423);
        $this->artisan('ficct:scan-institutional-source', ['source_id' => '-1'])->assertExitCode(1);
    }

    public function test_q08_expiration_and_policy_changes_require_new_scans(): void
    {
        $this->scanner('clean');
        app(DocumentSafety::class)->scan($this->source);
        config(['document_safety.max_database_age_days' => 1]);
        $this->download(423);
        app(DocumentSafety::class)->scan($this->source);
        $this->download(200);
        $this->travel(25)->hours();
        $this->download(423);
        $this->assertSame('expired', app(DocumentSafety::class)->status($this->source)['state']);
    }

    public function test_q09_review_completion_requires_current_scans_but_an_observation_is_still_allowed(): void
    {
        $this->scanner('clean');
        app(DocumentSafety::class)->scan($this->source);
        $this->postJson($this->url.'/submission', ['expected_version' => 2])->assertCreated();
        $this->scanner('error');
        app(DocumentSafety::class)->scan($this->source);
        $this->actingAs($this->reviewer)->postJson($this->url.'/review', [
            'expected_version' => 3,
            'decision' => 'reviewed',
            'reason' => 'No debe completar esta revisión.',
        ])->assertUnprocessable();
        $this->postJson($this->url.'/review', [
            'expected_version' => 3,
            'decision' => 'observed',
            'reason' => 'Fuente bloqueada por análisis pendiente.',
        ])->assertOk();
        $this->assertDatabaseCount('institutional_reviews', 1);
    }

    public function test_q10_clamav_adapter_requires_completed_scan_and_applies_safe_limits(): void
    {
        config(['document_safety.binary' => 'clamscan-test-only', 'document_safety.database' => 'trusted-signatures']);
        Process::fake(fn ($p) => Process::result(in_array('--version', $p->command, true) ? 'ClamAV 1.4.3/12345/date' : 'scanner completed without scanning file'));
        $this->assertSame('error', app(ClamAvScanner::class)->scan('private-test.pdf')['state']);
        Process::fake(fn ($p) => Process::result(in_array('--version', $p->command, true) ? 'ClamAV 1.4.3/12345/date' : 'private-test.pdf: OK'));
        $this->assertSame('clean', app(ClamAvScanner::class)->scan('private-test.pdf')['state']);
        Process::assertRan(fn ($p) => is_array($p->command) && in_array('--alert-exceeds-max=yes', $p->command, true) && in_array('--alert-encrypted=yes', $p->command, true) && in_array('--fail-if-cvd-older-than=2', $p->command, true) && ! in_array('--remove', $p->command, true));
        Process::fake(fn ($p) => in_array('--version', $p->command, true) ? Process::result('ClamAV 1.4.3/12345/date') : Process::result('', '', 1));
        $this->assertSame('blocked', app(ClamAvScanner::class)->scan('private-test.pdf')['state']);
    }

    public function test_q11_submission_rejects_missing_and_modified_bytes_without_changing_history(): void
    {
        $this->scanner('clean');
        app(DocumentSafety::class)->scan($this->source);
        $row = DB::table('institutional_sources')->find($this->source);
        $disk = Storage::disk('local');
        $original = $disk->get($row->path);
        $audit = DB::table('audit_events')->orderBy('id')->get()->toJson();
        foreach (['missing', 'modified'] as $case) {
            $case === 'missing' ? $disk->delete($row->path) : $disk->put($row->path, 'Bytes alterados');
            $this->postJson($this->url.'/submission', ['expected_version' => 2])->assertConflict();
            $this->assertDatabaseCount('institutional_submissions', 0);
            $this->assertDatabaseCount('institutional_reviews', 0);
            $this->assertDatabaseHas('institutional_dossiers', ['id' => $this->dossier, 'version' => 2, 'status' => 'draft']);
            $this->assertSame($audit, DB::table('audit_events')->orderBy('id')->get()->toJson());
            $this->assertEquals($row, DB::table('institutional_sources')->find($this->source));
            $disk->put($row->path, $original);
        }
        $id = $this->postJson($this->url.'/submission', ['expected_version' => 2])->assertCreated()->json('id');
        $snapshot = json_decode(json_decode(DB::table('institutional_submissions')->find($id)->snapshot, true)['serialized'], true);
        $this->assertSame($row->sha256, $snapshot['integrity_checks'][0]['sha256']);
        $this->assertSame($this->source, $snapshot['integrity_checks'][0]['source_id']);
    }

    public function test_q12_review_rejects_missing_and_modified_bytes_and_preserves_the_frozen_submission(): void
    {
        $this->scanner('clean');
        app(DocumentSafety::class)->scan($this->source);
        $this->postJson($this->url.'/submission', ['expected_version' => 2])->assertCreated();
        $before = DB::table('institutional_submissions')->get()->toJson();
        $audit = DB::table('audit_events')->orderBy('id')->get()->toJson();
        $row = DB::table('institutional_sources')->find($this->source);
        $disk = Storage::disk('local');
        $original = $disk->get($row->path);
        $this->actingAs($this->reviewer);
        foreach (['missing', 'modified'] as $case) {
            $case === 'missing' ? $disk->delete($row->path) : $disk->put($row->path, 'Bytes alterados');
            $this->postJson($this->url.'/review', [
                'expected_version' => 3,
                'decision' => 'reviewed',
                'reason' => 'No debe completarse con fuentes inválidas.',
            ])->assertConflict();
            $this->assertDatabaseCount('institutional_reviews', 0);
            $this->assertDatabaseHas('institutional_dossiers', ['id' => $this->dossier, 'version' => 3, 'status' => 'under_review']);
            $this->assertSame($before, DB::table('institutional_submissions')->get()->toJson());
            $this->assertSame($audit, DB::table('audit_events')->orderBy('id')->get()->toJson());
            $disk->put($row->path, $original);
        }
        $this->postJson($this->url.'/review', [
            'expected_version' => 3,
            'decision' => 'reviewed',
            'reason' => 'Se cotejaron los bytes actuales correctamente.',
        ])->assertOk();
        $this->assertSame($before, DB::table('institutional_submissions')->get()->toJson());
    }

    private function startScanWhileReading(): void
    {
        $disk = Storage::disk('local');
        $source = DB::table('institutional_sources')->find($this->source);
        $proxy = new class($disk, $source)
        {
            private bool $started = false;

            public function __construct(private $disk, private $source) {}

            public function exists($path)
            {
                return $this->disk->exists($path);
            }

            public function readStream($path)
            {
                if (! $this->started) {
                    $this->started = true;
                    DB::table('institutional_scan_jobs')->insert([
                        'source_id' => $this->source->id,
                        'sha256' => $this->source->sha256,
                        'policy_hash' => DocumentSafety::policyHash(),
                        'created_at' => now(),
                    ]);
                }

                return $this->disk->readStream($path);
            }
        };
        Storage::shouldReceive('disk')->with('local')->andReturn($proxy);
    }

    public function test_q13_submission_rechecks_scan_identity_after_reading_bytes(): void
    {
        $this->scanner('clean');
        app(DocumentSafety::class)->scan($this->source);
        $this->startScanWhileReading();
        $this->postJson($this->url.'/submission', ['expected_version' => 2])->assertUnprocessable();
        $this->assertDatabaseCount('institutional_submissions', 0);
        $this->assertDatabaseHas('institutional_dossiers', ['id' => $this->dossier, 'status' => 'draft', 'version' => 2]);
    }

    public function test_q14_review_rechecks_scan_identity_without_changing_historical_records(): void
    {
        $this->scanner('clean');
        app(DocumentSafety::class)->scan($this->source);
        $this->postJson($this->url.'/submission', ['expected_version' => 2])->assertCreated();
        $before = DB::table('institutional_submissions')->get()->toJson();
        $this->startScanWhileReading();
        $this->actingAs($this->reviewer)->postJson($this->url.'/review', [
            'expected_version' => 3,
            'decision' => 'reviewed',
            'reason' => 'El analizador cambió mientras se leía la fuente.',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('institutional_reviews', 0);
        $this->assertSame($before, DB::table('institutional_submissions')->get()->toJson());
    }

    public function test_q21_rescan_started_while_reading_source_blocks_the_download(): void
    {
        $this->scanner('clean');
        app(DocumentSafety::class)->scan($this->source);
        $source = DB::table('institutional_sources')->find($this->source);
        $disk = Storage::disk('local');
        $proxy = \Mockery::mock($disk);
        $proxy->shouldReceive('readStream')->with($source->path)->once()->andReturnUsing(function () use ($disk, $source) {
            DB::table('institutional_scan_jobs')->insert([
                'source_id' => $source->id,
                'sha256' => $source->sha256,
                'policy_hash' => DocumentSafety::policyHash(),
                'created_at' => now(),
            ]);

            return $disk->readStream($source->path);
        });
        Storage::shouldReceive('disk')->with('local')->andReturn($proxy);
        $this->download(423);
        $this->assertSame('pending', app(DocumentSafety::class)->status($this->source)['state']);
        $this->assertDatabaseCount('institutional_scan_results', 1);
    }
}
