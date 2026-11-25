<?php

namespace Tests\Feature;

use App\Domain\Administration;
use App\Domain\ScanRenewal;
use App\Domain\Scores;
use App\Domain\Selection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Regresiones de la auditoría general del 25/11/2026 (Anexo A, backend).
 * Cada prueba reproduce un hallazgo; sin la corrección correspondiente falla.
 */
final class AuditCorrectionsTest extends TestCase
{
    use RefreshDatabase;

    private Selection $s;

    private User $authority;

    private User $reviewer;

    private User $teacher;

    private User $student;

    private int $call;

    private int $offer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->s = app(Selection::class);
        $this->authority = User::factory()->create(['role' => 'authority']);
        $this->reviewer = User::factory()->create(['role' => 'staff']);
        $this->teacher = User::factory()->create(['role' => 'staff']);
        $this->student = User::factory()->create();
        $this->call = $this->newCall();
        foreach ([[$this->reviewer, 'reviewer'], [$this->teacher, 'evaluator']] as [$u, $role]) {
            DB::table('memberships')->insert(['user_id' => $u->id, 'call_id' => $this->call, 'role' => $role]);
        }
        $this->offer = $this->offer($this->call);
        $this->s->transition($this->authority, $this->call, 'published');
    }

    private function newCall(): int
    {
        return $this->s->createCall($this->authority, ['title' => 'Auditoría DEMO', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'exam_at' => now()->addDays(2), 'scope' => 'UAGRM', 'subject_limit' => 4, 'source' => 'Fixture sintético de auditoría', 'management_year' => 2026, 'call_number' => random_int(1000, 999999)]);
    }

    private function offer(int $call, array $override = []): int
    {
        $category = DB::table('categories')->where('name', 'Sala DEMO')->value('id') ?? DB::table('categories')->insertGetId(['name' => 'Sala DEMO', 'regime' => 'docencia', 'active' => true]);

        return $this->s->saveOffer($this->authority, $call, $override + ['category_id' => $category, 'title' => 'Sala A '.random_int(1, 99999), 'seats' => 2, 'is_subject' => false, 'modality' => 'Presencial', 'documents' => ['cpd' => 'Formulario CPD', 'ci' => 'Identidad sintética'], 'requirements' => ['activo' => 'Estudiante activo'], 'weights' => ['Soporte' => '50', 'Redes' => '25', 'Software' => '25'], 'scheme' => 'DEMO_AREAS_100', 'source' => 'Fixture DEMO']);
    }

    private function submitted(?User $u = null): int
    {
        $u ??= $this->student;
        $id = $this->s->apply($u, $this->offer);
        foreach (['cpd', 'ci'] as $kind) {
            $this->s->upload($u, $id, $kind, UploadedFile::fake()->image($kind.'.png'));
            $this->scanApplicantFixtures();
        }
        $this->s->submit($u, $id);

        return $id;
    }

    private function rejects(callable $action, ?string $contains = null): void
    {
        try {
            $action();
            $this->fail('Se esperaba rechazo de dominio.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
            if ($contains !== null) {
                $this->assertStringContainsString($contains, implode(' ', array_merge(...array_values($e->errors()))));
            }
        }
    }

    public function test_b02_evaluation_requires_every_submitted_application_reviewed(): void
    {
        $this->submitted();
        $this->s->transition($this->authority, $this->call, 'closed');
        $this->rejects(fn () => $this->s->transition($this->authority, $this->call, 'evaluation'), 'sin revisar');
        $this->assertSame('closed', DB::table('calls')->where('id', $this->call)->value('status'));
    }

    public function test_b02_evaluation_opens_once_every_review_is_resolved(): void
    {
        $id = $this->submitted();
        $this->s->review($this->reviewer, $id, ['doc:cpd' => 'meets', 'doc:ci' => 'meets', 'activo' => 'meets'], 'Evidencia sintética cotejada en DEMO.');
        $this->s->eligibility($this->reviewer, $id, true, 'Revisión completa documentada en DEMO.');
        $this->s->transition($this->authority, $this->call, 'closed');
        $this->s->transition($this->authority, $this->call, 'evaluation');
        $this->assertSame('evaluation', DB::table('calls')->where('id', $this->call)->value('status'));
    }

    public function test_b03_pending_composition_offer_cannot_be_published(): void
    {
        $call = $this->newCall();
        $this->offer($call, ['scheme' => 'PENDING_COMPOSITION']);
        $this->rejects(fn () => $this->s->transition($this->authority, $call, 'published'), 'composición');
        $this->assertSame('draft', DB::table('calls')->where('id', $call)->value('status'));
    }

    public function test_b05_cannot_grant_permissions_to_an_applicant_of_the_same_call(): void
    {
        $this->submitted();
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (['authority', 'reviewer', 'evaluator'] as $role) {
            $this->rejects(fn () => app(Administration::class)->grant($admin, $this->call, $this->student->id, $role, null, null, 'Permiso sintético de prueba.'), 'postula');
        }
        $this->assertSame(0, DB::table('memberships')->where('user_id', $this->student->id)->count());
    }

    public function test_b05_user_with_permission_cannot_apply_in_the_same_call(): void
    {
        $other = User::factory()->create();
        DB::table('memberships')->insert(['user_id' => $other->id, 'call_id' => $this->call, 'role' => 'reviewer']);
        $this->rejects(fn () => $this->s->apply($other, $this->offer), 'permiso');
    }

    public function test_b05_authority_who_applies_cannot_decide_results(): void
    {
        $id = $this->submitted();
        // Una autoridad con postulación propia no aprueba, calcula ni abre desempates.
        DB::table('memberships')->insert(['user_id' => $this->student->id, 'call_id' => $this->call, 'role' => 'authority']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->s->calculate($this->student, $id);
    }

    public function test_b13_ranking_order_is_deterministic_for_equal_scores(): void
    {
        $ids = [];
        foreach ([0, 1, 2] as $i) {
            $u = User::factory()->create();
            $a = DB::table('applications')->insertGetId(['offer_id' => $this->offer, 'user_id' => $u->id, 'status' => 'submitted', 'participation' => 'active', 'eligibility' => 'enabled', 'merit' => '20.00', 'exam' => '30.00', 'final' => '50.00', 'snapshot' => '{}', 'created_at' => now(), 'updated_at' => now()]);
            $ids[] = $a;
        }
        $order = array_map(fn ($a) => $a->id, $this->s->ranking($this->offer)['rank']);
        $this->assertSame($ids, $order);
    }

    public function test_b29_numeric_area_names_are_valid_weights(): void
    {
        $this->assertSame(['1' => '60.00', '2' => '40.00'], Scores::weights([1 => '60', 2 => '40']));
    }

    private function queuedScans(int $application): int
    {
        return DB::table('institutional_scan_tasks')->whereIn('document_id', DB::table('documents')->where('application_id', $application)->pluck('id'))->where('state', 'queued')->count();
    }

    public function test_b01_fresh_documents_are_not_renewed(): void
    {
        $this->submitted();
        $report = app(ScanRenewal::class)->run(true, 6);
        $this->assertSame(2, $report['inspected']);
        $this->assertSame(0, $report['renewed']);
    }

    public function test_b01_renewal_defaults_to_dry_run_and_never_releases_documents(): void
    {
        $id = $this->submitted();
        $this->travel(19)->hours();
        $dry = app(ScanRenewal::class)->run(false, 6);
        $this->assertSame(2, $dry['renewed']);
        $this->assertSame(2, $dry['by_reason']['expiring']);
        $this->assertSame([], $dry['tasks']);
        $this->assertSame(0, $this->queuedScans($id));
        $this->artisan('ficct:scan-renew')->assertExitCode(0);
        $this->assertSame(0, $this->queuedScans($id));
    }

    public function test_b01_expiring_and_expired_documents_are_requeued_once(): void
    {
        $id = $this->submitted();
        $this->travel(19)->hours();
        $this->assertSame(2, app(ScanRenewal::class)->run(true, 6)['renewed']);
        $this->assertSame(2, $this->queuedScans($id));
        // Segunda ejecución: ya hay solicitud pendiente; no se duplica ni se vuelve a contar.
        $again = app(ScanRenewal::class)->run(true, 6);
        $this->assertSame(0, $again['renewed']);
        $this->assertSame(2, $this->queuedScans($id));
    }

    public function test_b01_expired_documents_become_clean_again_after_the_worker_runs(): void
    {
        $id = $this->submitted();
        $this->travel(25)->hours();
        $report = app(ScanRenewal::class)->run(true, 6);
        $this->assertSame(2, $report['by_reason']['expired']);
        $this->useApplicantFixtureScanner();
        foreach ($report['tasks'] as $task) {
            app(\App\Domain\DocumentProcessor::class)->runOne($task);
        }
        $this->assertSame(0, app(ScanRenewal::class)->run(true, 6)['renewed']);
        app(\App\Domain\ApplicantDocuments::class)->assertEffective($id);
    }

    public function test_b01_renew_command_rejects_invalid_margin(): void
    {
        $this->artisan('ficct:scan-renew --margin-hours=99')->assertExitCode(1);
    }

    public function test_b04_database_session_uses_the_application_timezone(): void
    {
        $this->assertSame(config('app.timezone'), config('database.connections.pgsql.timezone'));
        $this->assertSame(config('app.timezone'), DB::selectOne('show timezone')->TimeZone);
    }
}
