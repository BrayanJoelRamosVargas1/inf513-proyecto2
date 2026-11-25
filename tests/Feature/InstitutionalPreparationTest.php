<?php

namespace Tests\Feature;

use App\Domain\InstitutionalPreparation;
use App\Domain\Selection;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class InstitutionalPreparationTest extends TestCase
{
    use RefreshDatabase;

    private User $author;
    private User $reviewer;
    private User $student;
    private int $call;
    private int $dossier;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        // Contract fixture only: never bind this scanner in a running installation.
        $this->app->instance(\App\Domain\DocumentScanner::class, new class implements \App\Domain\DocumentScanner { public function scan(string $path): array { return ['state' => 'clean', 'engine' => 'TEST-ONLY', 'reason' => 'fixture']; } });
        $this->author = User::factory()->create(['role' => 'authority']);
        $this->reviewer = User::factory()->create(['role' => 'authority']);
        $this->student = User::factory()->create(['role' => 'student']);
        $this->call = app(Selection::class)->createCall($this->author, ['title' => 'Referencia sintética, no oficial', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'exam_at' => now()->addDays(3), 'scope' => 'UAGRM', 'source' => 'Solo fixture']);
        DB::table('memberships')->insert(['call_id' => $this->call, 'user_id' => $this->reviewer->id, 'role' => 'authority']);
        $this->dossier = app(InstitutionalPreparation::class)->create($this->author, ['call_id' => $this->call, 'reviewer_id' => $this->reviewer->id, 'title' => 'Expediente sintético de preparación']);
    }

    private function endpoint(string $suffix = ''): string
    {
        return '/api/v1/institutional/dossiers/'.$this->dossier.$suffix;
    }

    private function source(int $expected = 1, string $gate = 'G02'): int
    {
        $file = UploadedFile::fake()->createWithContent('norma.pdf', file_get_contents(resource_path('demo-documents/cpd.pdf')));
        $id = $this->actingAs($this->author)->post($this->endpoint('/sources'), ['expected_version' => $expected, 'reference' => 'RV-021-2023', 'gate' => $gate, 'title' => 'Fuente sintética', 'issued_on' => '2023-01-01', 'pages' => '10, artículo 18', 'classification' => 'historical_reference', 'notes' => 'Esta muestra no es normativa auténtica.', 'file' => $file], ['Accept' => 'application/json'])->assertCreated()->json('id');
        app(\App\Domain\DocumentSafety::class)->scan($id);
        return $id;
    }

    public function test_i01_unrelated_accounts_and_members_without_assignment_cannot_read_or_create(): void
    {
        foreach ([$this->student, User::factory()->create(['role' => 'admin']), User::factory()->create(['role' => 'authority'])] as $user) {
            $this->actingAs($user)->getJson($this->endpoint())->assertForbidden();
            $this->postJson('/api/v1/institutional/dossiers', ['call_id' => $this->call, 'reviewer_id' => $this->reviewer->id, 'title' => 'Intento indebido'])->assertForbidden();
        }
        $third = User::factory()->create(['role' => 'authority']);
        DB::table('memberships')->insert(['call_id' => $this->call, 'user_id' => $third->id, 'role' => 'authority']);
        $this->actingAs($third)->getJson($this->endpoint())->assertForbidden();
        $this->getJson('/api/v1/institutional/dossiers')->assertOk()->assertJsonCount(0, 'dossiers');
    }

    public function test_i02_self_review_and_out_of_scope_reviewer_are_rejected(): void
    {
        $this->actingAs($this->author)->postJson('/api/v1/institutional/dossiers', ['call_id' => $this->call, 'reviewer_id' => $this->author->id, 'title' => 'Auto revisión'])->assertUnprocessable();
        $other = User::factory()->create(['role' => 'authority']);
        $this->postJson('/api/v1/institutional/dossiers', ['call_id' => $this->call, 'reviewer_id' => $other->id, 'title' => 'Sin ámbito'])->assertForbidden();
        $this->assertDatabaseCount('institutional_dossiers', 1);
    }

    public function test_i03_sources_keep_versions_and_private_downloads_verify_hashes(): void
    {
        $first = $this->source();
        $second = $this->source(2);
        $r = $this->actingAs($this->author)->getJson($this->endpoint())->assertOk()->assertJsonPath('readiness.official_activation_allowed', false)->assertJsonCount(2, 'readiness.sources');
        $this->assertSame([$second], $r->json('readiness.latest_source_ids'));
        $this->assertArrayNotHasKey('path', $r->json('readiness.sources.0'));
        $this->assertSame([1, 2], DB::table('institutional_sources')->orderBy('id')->pluck('version')->all());
        $source = DB::table('institutional_sources')->find($first);
        $this->assertSame(hash('sha256', file_get_contents(resource_path('demo-documents/cpd.pdf'))), $source->sha256);
        $this->get('/api/v1/institutional/sources/'.$first.'/download')->assertOk()->assertDownload('fuente-'.$first.'-v1.pdf');
        $this->actingAs($this->student)->getJson('/api/v1/institutional/sources/'.$second.'/download')->assertForbidden();
        Storage::disk('local')->put($source->path, 'Contenido alterado');
        $this->actingAs($this->reviewer)->getJson('/api/v1/institutional/sources/'.$first.'/download')->assertConflict();
    }

    public function test_i04_stale_upload_and_executable_are_rejected_without_extra_files(): void
    {
        $this->source();
        $count = count(Storage::disk('local')->allFiles());
        $payload = ['expected_version' => 1, 'reference' => 'TEST', 'gate' => 'G02', 'title' => 'Muestra', 'issued_on' => '2023-01-01', 'pages' => '1', 'classification' => 'proposed_rule', 'notes' => 'Muestra sintética.', 'file' => UploadedFile::fake()->createWithContent('muestra.pdf', file_get_contents(resource_path('demo-documents/cpd.pdf')))];
        $this->actingAs($this->author)->post($this->endpoint('/sources'), $payload, ['Accept' => 'application/json'])->assertConflict();
        $payload['expected_version'] = 2;
        $payload['file'] = UploadedFile::fake()->createWithContent('ejecutar.php', '<?php echo 123;');
        $this->post($this->endpoint('/sources'), $payload, ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertSame($count, count(Storage::disk('local')->allFiles()));
        $this->assertDatabaseCount('institutional_sources', 1);
    }

    public function test_i05_observation_and_resubmission_preserve_the_original_snapshot(): void
    {
        $this->source();
        $submission = $this->actingAs($this->author)->postJson($this->endpoint('/submission'), ['expected_version' => 2])->assertCreated()->json('id');
        $before = DB::table('institutional_submissions')->find($submission);
        $serialized = json_decode($before->snapshot, true)['serialized'];
        $this->assertSame(hash('sha256', $serialized), $before->sha256);
        $this->postJson($this->endpoint('/review'), ['expected_version' => 3, 'decision' => 'reviewed', 'reason' => 'Intento de revisión propia.'])->assertForbidden();
        $this->actingAs($this->reviewer)->postJson($this->endpoint('/review'), ['expected_version' => 3, 'decision' => 'observed', 'reason' => 'Falta confirmar vigencia de las fuentes.'])->assertOk();
        $this->source(4);
        $this->postJson($this->endpoint('/submission'), ['expected_version' => 5])->assertCreated();
        $this->assertEquals($before, DB::table('institutional_submissions')->find($submission));
        $this->assertDatabaseCount('institutional_submissions', 2);
        $this->assertDatabaseCount('institutional_reviews', 1);
    }

    public function test_i06_completed_technical_review_does_not_enable_official_operations_even_with_flag(): void
    {
        $before = DB::table('calls')->find($this->call);
        $this->source();
        $this->postJson($this->endpoint('/submission'), ['expected_version' => 2])->assertCreated();
        $this->actingAs($this->reviewer)->postJson($this->endpoint('/review'), ['expected_version' => 3, 'decision' => 'reviewed', 'reason' => 'Se cotejaron metadatos sintéticos, sin aprobación institucional.'])->assertOk()->assertJsonPath('official_activation_allowed', false);
        config(['ficct.official_enabled' => true]);
        $this->postJson($this->endpoint('/activation'), ['mode' => 'OFICIAL'])->assertConflict()->assertJsonPath('decision', 'NO_GO_INSTITUCIONAL');
        $r = $this->getJson($this->endpoint())->assertOk()->assertJsonPath('dossier.status', 'technically_reviewed')->assertJsonPath('readiness.official_activation_allowed', false);
        $this->assertSame('DOCUMENTED_UNVERIFIED', collect($r->json('readiness.gates'))->firstWhere('id', 'G02')['state']);
        $this->assertEquals($before, DB::table('calls')->find($this->call));
        $this->actingAs($this->author)->postJson($this->endpoint('/submission'), ['expected_version' => 4])->assertUnprocessable();
    }

    public function test_i07_all_sources_uploaded_still_leave_all_ten_conditions_unverified(): void
    {
        foreach (array_keys(InstitutionalPreparation::GATES) as $i => $gate) {
            // Different reference per gate so each remains the current version.
            $file = UploadedFile::fake()->createWithContent('muestra.pdf', file_get_contents(resource_path('demo-documents/cpd.pdf')));
            $this->actingAs($this->author)->post($this->endpoint('/sources'), ['expected_version' => 1 + $i, 'reference' => $gate, 'gate' => $gate, 'title' => 'Evidencia sintética', 'issued_on' => '2026-11-21', 'pages' => '1', 'classification' => 'proposed_rule', 'notes' => 'No existe validación institucional.', 'file' => $file], ['Accept' => 'application/json'])->assertCreated();
        }
        $r = $this->getJson($this->endpoint())->assertOk()->assertJsonPath('readiness.official_activation_allowed', false);
        $this->assertSame(['DOCUMENTED_UNVERIFIED'], array_values(array_unique(array_column($r->json('readiness.gates'), 'state'))));
        $this->assertCount(10, $r->json('readiness.gates'));
    }

    public function test_i08_revocation_is_rechecked_at_review_and_download(): void
    {
        $source = $this->source();
        $this->postJson($this->endpoint('/submission'), ['expected_version' => 2])->assertCreated();
        DB::table('memberships')->where('user_id', $this->reviewer->id)->update(['revoked_at' => now()]);
        $this->actingAs($this->reviewer)->postJson($this->endpoint('/review'), ['expected_version' => 3, 'decision' => 'reviewed', 'reason' => 'No debería poder revisar.'])->assertForbidden();
        $this->getJson('/api/v1/institutional/sources/'.$source.'/download')->assertForbidden();
        $this->assertDatabaseCount('institutional_reviews', 0);
    }

    public function test_i09_database_blocks_direct_source_snapshot_and_review_mutation(): void
    {
        $this->source();
        $this->postJson($this->endpoint('/submission'), ['expected_version' => 2])->assertCreated();
        $this->actingAs($this->reviewer)->postJson($this->endpoint('/review'), ['expected_version' => 3, 'decision' => 'reviewed', 'reason' => 'Revisión técnica de prueba.'])->assertOk();
        foreach (['institutional_sources', 'institutional_submissions', 'institutional_reviews'] as $table) {
            foreach (['update', 'delete'] as $action) {
                try {
                    DB::transaction(fn () => $action === 'update' ? DB::table($table)->update(['created_by' => $this->student->id]) : DB::table($table)->delete());
                    $this->fail('Se alteró evidencia inmutable: '.$table);
                } catch (QueryException $e) {
                    $this->assertStringContainsString('append-only', $e->getMessage());
                }
            }
            $this->assertDatabaseCount($table, 1);
        }
    }

    public function test_i10_empty_submission_reviewer_upload_and_duplicate_review_are_rejected(): void
    {
        $this->actingAs($this->author)->postJson($this->endpoint('/submission'), ['expected_version' => 1])->assertUnprocessable();
        $this->source();
        $this->postJson($this->endpoint('/submission'), ['expected_version' => 2])->assertCreated();
        $this->postJson($this->endpoint('/submission'), ['expected_version' => 2])->assertConflict();
        $this->actingAs($this->reviewer)->postJson($this->endpoint('/review'), ['expected_version' => 3, 'decision' => 'reviewed', 'reason' => 'Revisión técnica de prueba.'])->assertOk();
        $this->postJson($this->endpoint('/review'), ['expected_version' => 3, 'decision' => 'reviewed', 'reason' => 'Solicitud obsoleta repetida.'])->assertConflict();
        $this->assertDatabaseCount('institutional_reviews', 1);
        $this->getJson($this->endpoint())->assertOk()->assertJsonPath('can_edit', false)->assertJsonPath('can_review', false)->assertJsonStructure(['submissions' => [['snapshot' => ['serialized']]]]);
    }
}
