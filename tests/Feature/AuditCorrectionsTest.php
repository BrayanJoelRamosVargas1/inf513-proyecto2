<?php

namespace Tests\Feature;

use App\Domain\Administration;
use App\Domain\InstitutionalRegister;
use App\Domain\ResultRevisions;
use App\Domain\ScanRenewal;
use App\Domain\Scores;
use App\Domain\Selection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
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

    public function test_b10_missing_indexes_exist(): void
    {
        $names = DB::table('pg_indexes')->where('schemaname', 'public')->pluck('indexname')->all();
        foreach (['applications_user_id_index', 'memberships_user_id_index', 'audit_events_call_id_id_index', 'institutional_scan_events_job_id_index', 'participation_requests_application_id_index'] as $index) {
            $this->assertContains($index, $names);
        }
    }

    public function test_b09_secure_responses_add_hsts_only_over_https(): void
    {
        $plain = $this->get('/login');
        $plain->assertHeader('Permissions-Policy');
        $plain->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_b23_csv_cells_neutralize_formula_prefixes(): void
    {
        foreach (['=1+1', '+1', '-1', '@SUM(A1)', "\tcmd", "\rcmd"] as $dangerous) {
            $this->assertSame("'".$dangerous, InstitutionalRegister::csvCell($dangerous));
        }
        $this->assertSame('texto normal', InstitutionalRegister::csvCell('texto normal'));
        $this->assertSame(7, InstitutionalRegister::csvCell(7));
    }

    public function test_b26_mutations_and_uploads_are_rate_limited_but_reads_are_not(): void
    {
        $this->assertNotNull(RateLimiter::limiter('mutations'));
        $this->assertNotNull(RateLimiter::limiter('uploads'));
        $post = app('router')->getRoutes()->match(Request::create('/api/v1/calls', 'POST'));
        $this->assertContains('throttle:mutations', $post->gatherMiddleware());
        $upload = app('router')->getRoutes()->match(Request::create('/api/v1/applications/1/archivo', 'POST'));
        $this->assertContains('throttle:uploads', $upload->gatherMiddleware());
        $this->assertInstanceOf(\Illuminate\Cache\RateLimiting\Unlimited::class, RateLimiter::limiter('mutations')(Request::create('/api/v1/calls', 'GET')));
        $this->assertInstanceOf(\Illuminate\Cache\RateLimiting\Limit::class, RateLimiter::limiter('mutations')(Request::create('/api/v1/calls', 'POST')));
    }

    public function test_b12_web_call_page_uses_the_same_visibility_policy_as_the_api(): void
    {
        $draft = $this->newCall();
        // Un revisor con permiso vigente sobre un borrador lo ve (antes recibía 403 en la vista web).
        DB::table('memberships')->insert(['user_id' => $this->reviewer->id, 'call_id' => $draft, 'role' => 'reviewer']);
        $this->actingAs($this->reviewer)->get('/convocatorias/'.$draft)->assertOk();
        // Un postulante no ve un borrador ajeno, igual que en la API.
        $this->actingAs($this->student)->get('/convocatorias/'.$draft)->assertForbidden();
        // Una convocatoria publicada sigue visible para postulantes.
        $this->actingAs($this->student)->get('/convocatorias/'.$this->call)->assertOk();
        // Personal sin permiso en esa convocatoria no la ve (política de PortalScope).
        $outsider = User::factory()->create(['role' => 'staff']);
        $this->actingAs($outsider)->get('/convocatorias/'.$this->call)->assertForbidden();
    }

    public function test_b30_score_scale_constants_are_consistent(): void
    {
        $this->assertSame('100', bcadd(Scores::MERIT_MAX, Scores::EXAM_MAX, 0));
        $this->assertSame('0.6', Scores::EXAM_FACTOR);
    }

    private function enabledApplication(string $final, string $merit = '20.00'): int
    {
        $u = User::factory()->create();

        return DB::table('applications')->insertGetId(['offer_id' => $this->offer, 'user_id' => $u->id, 'status' => 'submitted', 'participation' => 'active', 'eligibility' => 'enabled', 'merit' => $merit, 'exam' => bcsub($final, $merit, 2), 'final' => $final, 'snapshot' => '{}', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_b08_result_snapshot_keeps_scores_but_no_personal_data(): void
    {
        $first = $this->enabledApplication('70.00');
        $second = $this->enabledApplication('60.00');
        $snapshot = app(ResultRevisions::class)->snapshot($this->offer);
        $this->assertFalse($snapshot['incomplete']);
        $this->assertSame([$first, $second], array_column($snapshot['rank'], 'id'));
        foreach ($snapshot['rank'] as $entry) {
            $this->assertSame(['id', 'merit', 'exam', 'final'], array_keys($entry));
        }
        $this->assertStringNotContainsString(User::find(DB::table('applications')->where('id', $first)->value('user_id'))->name, json_encode($snapshot));
    }

    public function test_b13_incomplete_snapshot_has_the_same_format_and_deterministic_order(): void
    {
        $a = $this->enabledApplication('50.00');
        $b = $this->enabledApplication('50.00');
        // Una postulación enviada sin revisar hace que el ranking completo no exista todavía.
        $u = User::factory()->create();
        $pending = DB::table('applications')->insertGetId(['offer_id' => $this->offer, 'user_id' => $u->id, 'status' => 'submitted', 'participation' => 'active', 'eligibility' => 'pending', 'snapshot' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        $snapshot = app(ResultRevisions::class)->snapshot($this->offer);
        $this->assertTrue($snapshot['incomplete']);
        $this->assertSame([$a, $b], array_column($snapshot['rank'], 'id'));
        $this->assertSame([$pending], $snapshot['pending']);
        $this->assertSame(['rank', 'tied', 'seats', 'incomplete', 'pending'], array_keys($snapshot));
    }

    public function test_b13_changes_report_new_positions_and_scores(): void
    {
        $revisions = app(ResultRevisions::class);
        $before = ['rank' => [['id' => 1, 'final' => '70.00'], ['id' => 2, 'final' => '60.00']]];
        $after = ['rank' => [['id' => 2, 'final' => '75.00'], ['id' => 1, 'final' => '70.00'], ['id' => 3, 'final' => '40.00']]];
        $this->assertSame([
            ['application_id' => 2, 'old_position' => 2, 'new_position' => 1, 'old_final' => '60.00', 'new_final' => '75.00'],
            ['application_id' => 1, 'old_position' => 1, 'new_position' => 2, 'old_final' => '70.00', 'new_final' => '70.00'],
            ['application_id' => 3, 'old_position' => null, 'new_position' => 3, 'old_final' => null, 'new_final' => '40.00'],
        ], $revisions->changes($before, $after));
    }

    private function loginKey(User $u): string
    {
        return 'login:'.mb_strtolower($u->email).'|127.0.0.1';
    }

    public function test_b25_login_is_locked_per_account_after_repeated_failures(): void
    {
        $this->student->forceFill(['password' => bcrypt('Clave-Correcta-123')])->save();
        // Cuatro fallos todavía permiten intentar; el quinto activa el bloqueo de la cuenta.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/login', ['email' => $this->student->email, 'password' => 'incorrecta'])->assertStatus(422);
        }
        $this->assertTrue(RateLimiter::tooManyAttempts($this->loginKey($this->student), 5));
        // Con la clave correcta también se bloquea mientras dure la ventana.
        $this->postJson('/api/v1/login', ['email' => $this->student->email, 'password' => 'Clave-Correcta-123'])->assertStatus(429)->assertJsonPath('errors.email.0', 'Demasiados intentos fallidos. Espere unos minutos.');
    }

    public function test_b25_lock_is_per_account_and_does_not_affect_other_users(): void
    {
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($this->loginKey($this->student), 300);
        }
        $other = User::factory()->create(['password' => bcrypt('Otra-Clave-1234')]);
        $this->postJson('/api/v1/login', ['email' => $other->email, 'password' => 'Otra-Clave-1234'])->assertOk();
    }

    public function test_b25_a_successful_login_clears_failed_attempts(): void
    {
        $this->student->forceFill(['password' => bcrypt('Clave-Correcta-123')])->save();
        for ($i = 0; $i < 4; $i++) {
            RateLimiter::hit($this->loginKey($this->student), 300);
        }
        $this->postJson('/api/v1/login', ['email' => $this->student->email, 'password' => 'Clave-Correcta-123'])->assertOk();
        $this->assertSame(0, RateLimiter::attempts($this->loginKey($this->student)));
    }

    public function test_b21_database_rejects_an_unknown_call_status(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('calls')->where('id', $this->call)->update(['status' => 'inventado']);
    }

    public function test_b21_database_rejects_an_unknown_user_role(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('users')->where('id', $this->student->id)->update(['role' => 'superusuario']);
    }

    public function test_b21_every_status_used_by_the_workflow_is_accepted(): void
    {
        foreach (['draft', 'published', 'closed', 'evaluation', 'results', 'archived'] as $status) {
            DB::table('calls')->where('id', $this->call)->update(['status' => $status]);
        }
        foreach (['pending', 'selected', 'not_selected', 'under_review', 'withdrawn', 'resigned'] as $selection) {
            DB::table('applications')->where('offer_id', $this->offer)->update(['selection' => $selection]);
        }
        $this->assertSame('archived', DB::table('calls')->where('id', $this->call)->value('status'));
    }
}
