<?php

namespace Tests\Feature;

use App\Domain\Selection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AcademicApiTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $reviewer;

    private User $authority;

    private User $evaluator;

    private int $call;

    private int $offer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->student = User::factory()->create();
        $this->reviewer = User::factory()->create(['role' => 'staff']);
        $this->authority = User::factory()->create(['role' => 'authority']);
        $this->evaluator = User::factory()->create(['role' => 'staff']);
        $s = app(Selection::class);
        $this->call = $s->createCall($this->authority, ['title' => 'Convocatoria REST de prueba', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'exam_at' => now()->addDays(2), 'scope' => 'UAGRM', 'source' => 'Fixture sintético de API']);
        foreach ([[$this->reviewer, 'reviewer'], [$this->evaluator, 'evaluator']] as [$u, $role]) {
            DB::table('memberships')->insert(['user_id' => $u->id, 'call_id' => $this->call, 'role' => $role]);
        }
        $category = DB::table('categories')->insertGetId(['name' => 'Software API', 'regime' => 'docencia', 'active' => true]);
        $this->offer = $s->saveOffer($this->authority, $this->call, ['category_id' => $category, 'title' => 'Oferta REST', 'seats' => 1, 'is_subject' => false, 'modality' => 'Presencial', 'documents' => ['cpd' => 'CPD'], 'requirements' => ['activo' => 'Activo'], 'weights' => ['Software' => '100'], 'scheme' => 'DEMO_AREAS_100', 'source' => 'Fixture sintético de API']);
        $s->assign($this->authority, $this->offer, $this->evaluator->id, 'Software');
        $s->transition($this->authority, $this->call, 'published');
    }

    public function test_a01_api_login_logout_and_failed_access_are_logged_without_passwords(): void
    {
        $this->postJson('/api/v1/login', ['email' => $this->student->email, 'password' => 'incorrecta'])->assertUnprocessable()->assertJsonPath('message', 'Credenciales incorrectas o cuenta desactivada.');
        $this->assertDatabaseHas('access_events', ['event' => 'login.failed', 'user_id' => null, 'ip' => '127.0.0.1']);
        $this->student->password = 'Clave-Sintetica-2026!';
        $this->student->save();
        $this->postJson('/api/v1/login', ['email' => $this->student->email, 'password' => 'Clave-Sintetica-2026!'])->assertOk()->assertJsonPath('user.id', $this->student->id);
        $this->getJson('/api/v1/me')->assertOk();
        $this->assertDatabaseHas('access_events', ['event' => 'login.success', 'user_id' => $this->student->id]);
        $this->postJson('/api/v1/logout')->assertNoContent();
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->assertStringNotContainsString('Clave-Sintetica', DB::table('access_events')->get()->toJson());
    }

    public function test_a02_navigation_comes_from_database_and_respects_effective_roles(): void
    {
        $this->actingAs($this->reviewer)->getJson('/api/v1/me/navigation')->assertOk()->assertJsonMissing(['path' => '/postulaciones'])->assertJsonFragment(['path' => '/convocatorias']);
        DB::table('navigation_items')->where('path', '/convocatorias')->update(['label' => 'Título cambiado desde PostgreSQL']);
        $this->getJson('/api/v1/me/navigation')->assertJsonFragment(['label' => 'Título cambiado desde PostgreSQL']);
        DB::table('memberships')->where('user_id', $this->reviewer->id)->update(['revoked_at' => now()]);
        $this->getJson('/api/v1/me/navigation')->assertJsonMissing(['path' => '/convocatorias']);
    }

    public function test_a03_visitor_cannot_use_private_api_and_revoked_session_is_json_401(): void
    {
        $this->getJson('/api/v1/applications')->assertUnauthorized();
        $this->actingAs($this->student)->withSession(['security_version' => -1])->getJson('/api/v1/me')->assertUnauthorized()->assertJsonPath('message', 'Los permisos cambiaron. Inicie sesión nuevamente.');
    }

    public function test_a04_api_application_workflow_reuses_domain_and_preserves_private_fields(): void
    {
        $id = $this->actingAs($this->student)->postJson('/api/v1/offers/'.$this->offer.'/applications')->assertCreated()->json('id');
        $this->postJson('/api/v1/applications/'.$id.'/enviar')->assertUnprocessable();
        $this->post('/api/v1/applications/'.$id.'/archivo', ['kind' => 'cpd', 'file' => UploadedFile::fake()->image('cpd.png')], ['Accept' => 'application/json'])->assertOk();
        $this->postJson('/api/v1/applications/'.$id.'/enviar')->assertOk();
        $r = $this->getJson('/api/v1/applications/'.$id)->assertOk()->assertJsonPath('can_upload', false);
        $this->assertArrayNotHasKey('path', $r->json('documents.0'));
        $this->actingAs($this->reviewer)->postJson('/api/v1/applications/'.$id.'/revision', ['decisions' => ['doc:cpd' => 'meets', 'activo' => 'meets'], 'reason' => 'Evidencia sintética cotejada'])->assertOk();
        $this->postJson('/api/v1/applications/'.$id.'/habilitacion', ['enabled' => '1', 'reason' => 'Motivo reservado del revisor'])->assertOk();
        $this->postJson('/api/v1/applications/'.$id.'/meritos', ['score' => '20', 'reference' => 'CPD-DEMO'])->assertOk();
        $this->actingAs($this->evaluator)->getJson('/api/v1/applications/'.$id)->assertOk()->assertJsonPath('documents', [])->assertJsonPath('criteria', [])->assertJsonMissing(['eligibility_reason' => 'Motivo reservado del revisor']);
        $this->actingAs($this->authority)->postJson('/api/v1/calls/'.$this->call.'/transition', ['status' => 'closed'])->assertOk();
        $this->postJson('/api/v1/calls/'.$this->call.'/transition', ['status' => 'evaluation'])->assertOk();
        $assignment = DB::table('assignments')->value('id');
        $this->actingAs($this->evaluator)->postJson('/api/v1/applications/'.$id.'/nota', ['assignment_id' => $assignment, 'score' => '80', 'intent' => 'confirm'])->assertOk();
        $this->actingAs($this->authority)->postJson('/api/v1/applications/'.$id.'/calcular')->assertOk();
        $this->assertDatabaseHas('applications', ['id' => $id, 'final' => '68.00']);
    }

    public function test_a05_unrelated_user_cannot_read_or_modify_application(): void
    {
        $id = app(Selection::class)->apply($this->student, $this->offer);
        $other = User::factory()->create();
        $this->actingAs($other)->getJson('/api/v1/applications/'.$id)->assertForbidden();
        $this->postJson('/api/v1/applications/'.$id.'/enviar')->assertForbidden();
        $this->getJson('/api/v1/applications')->assertJsonCount(0, 'applications');
        $this->postJson('/api/v1/calls/'.$this->call.'/transition', ['status' => 'closed'])->assertForbidden();
    }

    public function test_a06_search_does_not_leak_drafts_or_private_students(): void
    {
        DB::table('calls')->where('id', $this->call)->update(['status' => 'draft']);
        $this->actingAs($this->student)->getJson('/api/v1/search?q=REST')->assertOk()->assertJsonCount(0, 'calls.data')->assertJsonCount(0, 'offers');
        $this->actingAs($this->authority)->getJson('/api/v1/search?q=REST')->assertJsonCount(1, 'calls.data');
        $this->getJson('/api/v1/search?q=%25%27%20OR%201%3D1')->assertOk()->assertJsonCount(0, 'calls.data');
    }

    public function test_a07_preference_combinations_persist_and_reject_invalid_values(): void
    {
        foreach (['ninos', 'jovenes', 'adultos'] as $theme) {
            foreach (['dia', 'noche', 'auto'] as $mode) {
                $this->actingAs($this->student)->putJson('/api/v1/me/preferences', ['theme' => $theme, 'mode' => $mode, 'font' => 150, 'contrast' => true])->assertOk()->assertJsonPath('preferences.theme', $theme)->assertJsonPath('preferences.mode', $mode);
            }
        }
        $this->putJson('/api/v1/me/preferences', ['theme' => 'otro', 'mode' => 'noche', 'font' => 999, 'contrast' => true])->assertUnprocessable();
        $this->actingAs($this->reviewer)->getJson('/api/v1/me/preferences')->assertJsonPath('preferences.theme', 'adultos');
    }

    public function test_a08_visits_are_persistent_idempotent_and_do_not_count_assets(): void
    {
        $key = (string) Str::uuid();
        $this->actingAs($this->student)->postJson('/api/v1/visits', ['path' => '/convocatorias', 'key' => $key])->assertOk()->assertJsonPath('total', 1);
        $this->postJson('/api/v1/visits', ['path' => '/convocatorias', 'key' => $key])->assertJsonPath('total', 1);
        $this->postJson('/api/v1/visits', ['path' => '/convocatorias', 'key' => (string) Str::uuid()])->assertJsonPath('total', 2);
        $this->postJson('/api/v1/visits', ['path' => '/cuenta', 'key' => $key])->assertConflict();
        $this->postJson('/api/v1/visits', ['path' => '/build/app.js', 'key' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseHas('page_visits', ['path' => '/convocatorias', 'total' => 2]);
    }

    public function test_a09_payment_is_synthetic_idempotent_and_rejects_foreign_method(): void
    {
        $before = DB::table('applications')->count();
        $method = $this->actingAs($this->student)->postJson('/api/v1/sandbox/methods', ['label' => 'Método de práctica A'])->assertCreated()->json('id');
        $d = ['method_id' => $method, 'amount' => '10.25', 'outcome' => 'paid', 'key' => (string) Str::uuid(), 'confirmed' => true];
        $id = $this->postJson('/api/v1/sandbox/payments', $d)->assertCreated()->json('id');
        $this->postJson('/api/v1/sandbox/payments', $d)->assertJsonPath('id', $id);
        $this->postJson('/api/v1/sandbox/payments', array_replace($d, ['amount' => '11']))->assertConflict();
        $this->postJson('/api/v1/sandbox/methods', ['label' => 'Método de práctica A', 'card_number' => '4111111111111111'])->assertUnprocessable();
        $other = User::factory()->create();
        $this->actingAs($other)->postJson('/api/v1/sandbox/payments', array_replace($d, ['key' => (string) Str::uuid()]))->assertForbidden();
        $this->getJson('/api/v1/sandbox')->assertJsonCount(0, 'payments');
        $this->assertDatabaseCount('sandbox_payments', 1);
        $this->assertDatabaseCount('applications', $before);
    }

    public function test_a10_plan_installments_sum_exactly_and_paid_installment_cannot_repeat(): void
    {
        $method = $this->actingAs($this->student)->postJson('/api/v1/sandbox/methods', ['label' => 'Método de práctica A'])->json('id');
        $plan = ['total' => '10.00', 'count' => 3, 'description' => 'Plan de práctica', 'key' => (string) Str::uuid()];
        $id = $this->postJson('/api/v1/sandbox/plans', $plan)->assertCreated()->json('id');
        $this->postJson('/api/v1/sandbox/plans', $plan)->assertJsonPath('id', $id);
        $this->assertSame('10.00', number_format((float) DB::table('sandbox_installments')->where('plan_id', $id)->sum('amount'), 2, '.', ''));
        $part = DB::table('sandbox_installments')->first();
        $d = ['method_id' => $method, 'installment_id' => $part->id, 'amount' => $part->amount, 'outcome' => 'paid', 'confirmed' => true, 'key' => (string) Str::uuid()];
        $this->postJson('/api/v1/sandbox/payments', $d)->assertCreated();
        $this->postJson('/api/v1/sandbox/payments', array_replace($d, ['key' => (string) Str::uuid()]))->assertConflict();
        $this->assertDatabaseCount('sandbox_payments', 1);
    }

    public function test_a11_failed_payment_can_retry_and_disabled_method_is_rejected(): void
    {
        $method = $this->actingAs($this->student)->postJson('/api/v1/sandbox/methods', ['label' => 'Método de práctica B'])->json('id');
        $d = ['method_id' => $method, 'amount' => '1.00', 'outcome' => 'failed', 'confirmed' => true, 'key' => (string) Str::uuid()];
        $this->postJson('/api/v1/sandbox/payments', $d)->assertCreated();
        $this->postJson('/api/v1/sandbox/payments', array_replace($d, ['outcome' => 'paid', 'key' => (string) Str::uuid()]))->assertCreated();
        $this->deleteJson('/api/v1/sandbox/methods/'.$method)->assertNoContent();
        $this->postJson('/api/v1/sandbox/payments', array_replace($d, ['key' => (string) Str::uuid()]))->assertUnprocessable();
    }

    public function test_a12_metrics_are_scoped_to_own_or_authorized_applications(): void
    {
        app(Selection::class)->apply($this->student, $this->offer);
        $this->actingAs($this->student)->getJson('/api/v1/metrics')->assertJsonPath('applications', 1)->assertJsonPath('traffic_scope', 'Mi actividad');
        $this->actingAs(User::factory()->create())->getJson('/api/v1/metrics')->assertJsonPath('applications', 0);
        $this->actingAs($this->reviewer)->getJson('/api/v1/metrics')->assertJsonPath('applications', 1);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/api/v1/metrics')->assertJsonPath('applications', 0)->assertJsonPath('traffic_scope', 'Instalación completa');
    }

    public function test_a13_spanish_validation_and_legacy_csp_remains_strict(): void
    {
        $this->postJson('/api/v1/login', [])->assertUnprocessable()->assertJsonPath('errors.email.0', 'El campo correo es obligatorio.');
        $r = $this->get('/p2')->assertOk();
        $this->assertStringContainsString("script-src 'self'", $r->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("script-src 'none'", $this->get('/login')->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('unsafe-inline', $r->headers->get('Content-Security-Policy'));
    }

    public function test_a14_configuration_api_enforces_authority_and_keeps_published_offer_frozen(): void
    {
        $data = ['title' => 'Nueva convocatoria API', 'opens_at' => now()->subHour()->toDateTimeString(), 'closes_at' => now()->addDay()->toDateTimeString(), 'exam_at' => now()->addDays(2)->toDateTimeString(), 'scope' => 'UAGRM', 'source' => 'Configuración sintética verificada'];
        $this->actingAs($this->student)->postJson('/api/v1/calls', $data)->assertForbidden();
        $id = $this->actingAs($this->authority)->postJson('/api/v1/calls', $data)->assertCreated()->json('id');
        $this->assertIsInt($id);
        $data['title'] = 'Borrador editado por API';
        $this->postJson('/api/v1/calls/'.$id.'/configuration', $data)->assertOk();
        $offer = ['title' => 'Oferta API nueva', 'category_id' => DB::table('categories')->value('id'), 'seats' => 1, 'modality' => 'Presencial', 'is_subject' => false, 'documents' => 'cpd=CPD', 'requirements' => 'activo=Activo', 'weights' => 'Software=100', 'scheme' => 'DEMO_AREAS_100', 'source' => 'Fuente sintética de API'];
        $this->postJson('/api/v1/calls/'.$id.'/offers', $offer)->assertCreated();
        $this->getJson('/api/v1/calls/'.$id.'/configuration')->assertOk()->assertJsonPath('call.title', $data['title'])->assertJsonCount(1, 'offers');
        $this->postJson('/api/v1/calls/'.$id.'/transition', ['status' => 'published'])->assertOk();
        $this->postJson('/api/v1/calls/'.$id.'/offers', $offer)->assertUnprocessable();
        $this->actingAs($this->reviewer)->getJson('/api/v1/calls/'.$id.'/configuration')->assertForbidden();
    }

    public function test_a15_administration_adapter_requires_admin_and_profile_version(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->authority)->getJson('/api/v1/management')->assertForbidden();
        $this->actingAs($admin)->getJson('/api/v1/management')->assertOk()->assertJsonMissingPath('users.0.password');
        $this->postJson('/api/v1/categories', ['name' => 'Tipo REST nuevo', 'regime' => 'laboratorio', 'active' => true])->assertCreated();
        $data = ['name' => 'Postulante API actualizado', 'email' => $this->student->email, 'faculty' => 'FICCT', 'career' => 'Informática', 'role' => 'student', 'active' => true, 'version' => $this->student->fresh()->profile_version, 'reason' => 'Cambio sintético autorizado'];
        $this->postJson('/api/v1/users/'.$this->student->id.'/profile', $data)->assertOk();
        $this->postJson('/api/v1/users/'.$this->student->id.'/profile', $data)->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $this->student->id, 'name' => $data['name']]);
    }

    public function test_a16_amendment_adapter_versions_documents_and_rejects_foreign_owner(): void
    {
        $s = app(Selection::class);
        $id = $s->apply($this->student, $this->offer);
        $s->upload($this->student, $id, 'cpd', UploadedFile::fake()->image('original.png'));
        $s->submit($this->student, $id);
        $s->review($this->reviewer, $id, ['doc:cpd' => 'observed', 'activo' => 'meets'], 'CPD observado en prueba sintética');
        $data = ['kinds' => ['cpd'], 'deadline' => now()->addHours(2)->toDateTimeString(), 'reason' => 'Subsanación DEMO autorizada', 'evidence' => 'Acta sintética para la prueba'];
        $this->actingAs($this->student)->postJson('/api/v1/applications/'.$id.'/amendments', $data)->assertForbidden();
        $this->actingAs($this->reviewer)->postJson('/api/v1/applications/'.$id.'/amendments', $data)->assertCreated();
        $window = DB::table('amendments')->value('id');
        $this->actingAs(User::factory()->create())->post('/api/v1/amendments/'.$window.'/documents', ['kind' => 'cpd', 'file' => UploadedFile::fake()->image('ajeno.png')], ['Accept' => 'application/json'])->assertForbidden();
        $this->actingAs($this->student)->post('/api/v1/amendments/'.$window.'/documents', ['kind' => 'cpd', 'file' => UploadedFile::fake()->image('corregido.png')], ['Accept' => 'application/json'])->assertOk();
        $this->postJson('/api/v1/amendments/'.$window.'/submission')->assertOk();
        $this->actingAs($this->reviewer)->postJson('/api/v1/amendments/'.$window.'/review', ['decisions' => ['doc:cpd' => 'meets', 'activo' => 'meets'], 'reason' => 'Nueva revisión completa sintética'])->assertOk();
        $response = $this->getJson('/api/v1/applications/'.$id.'/participation')->assertOk()->assertJsonPath('app.dossier_version', 2)->assertJsonCount(2, 'documents');
        $this->assertArrayNotHasKey('path', $response->json('documents.0'));
        $this->assertArrayNotHasKey('reviews', $response->json('snapshots.0.snapshot'));
        $this->actingAs($this->evaluator)->getJson('/api/v1/applications/'.$id.'/participation')->assertForbidden();
    }

    public function test_a17_withdrawal_requires_scoped_decision_and_preserves_draft(): void
    {
        $id = app(Selection::class)->apply($this->student, $this->offer);
        $data = ['kind' => 'withdrawal', 'reason' => 'Retiro sintético solicitado', 'evidence' => 'Evidencia sintética de retiro'];
        $this->actingAs($this->student)->postJson('/api/v1/applications/'.$id.'/participation-requests', $data)->assertCreated();
        $request = DB::table('participation_requests')->value('id');
        $decision = ['approved' => true, 'reason' => 'Decisión sintética fundamentada'];
        $this->postJson('/api/v1/participation-requests/'.$request.'/decision', $decision)->assertForbidden();
        $this->actingAs($this->authority)->postJson('/api/v1/participation-requests/'.$request.'/decision', $decision)->assertOk();
        $this->assertDatabaseHas('applications', ['id' => $id, 'participation' => 'withdrawn', 'status' => 'draft']);
        $this->assertDatabaseCount('publications', 0);
        $this->assertDatabaseCount('participation_decisions', 1);
    }

    public function test_a18_publication_choice_and_correction_preserve_original_publication(): void
    {
        $s = app(Selection::class);
        $id = $s->apply($this->student, $this->offer);
        $s->upload($this->student, $id, 'cpd', UploadedFile::fake()->image('cpd.png'));
        $s->submit($this->student, $id);
        $s->review($this->reviewer, $id, ['doc:cpd' => 'meets', 'activo' => 'meets'], 'Revisión sintética satisfactoria');
        $s->eligibility($this->reviewer, $id, true, 'Todos los requisitos cumplen');
        $s->merit($this->reviewer, $id, '20', 'CPD sintético');
        $s->transition($this->authority, $this->call, 'closed');
        $s->transition($this->authority, $this->call, 'evaluation');
        $s->grade($this->evaluator, $id, DB::table('assignments')->value('id'), '80');
        $s->calculate($this->authority, $id);
        $this->actingAs($this->authority)->getJson('/api/v1/offers/'.$this->offer.'/ranking')->assertOk()->assertJsonPath('ranking.rank.0.final', '68.00');
        $this->postJson('/api/v1/offers/'.$this->offer.'/publications', ['minutes' => 'Acta sintética de aprobación de resultados'])->assertOk();
        $publication = DB::table('publications')->first();
        $this->actingAs($this->student)->getJson('/api/v1/awards')->assertOk()->assertJsonCount(1, 'awards');
        $this->postJson('/api/v1/applications/'.$id.'/choices', ['publication_id' => $publication->id, 'version' => 0, 'choice' => 'accept', 'reason' => 'Manifestación DEMO del interesado', 'evidence' => 'Referencia sintética de la manifestación'])->assertOk();
        $this->actingAs($this->reviewer)->postJson('/api/v1/applications/'.$id.'/corrections', ['score' => '21', 'reason' => 'Error material de transcripción', 'evidence' => 'CPD sintético con valor cotejado'])->assertCreated();
        $request = DB::table('correction_requests')->value('id');
        $this->getJson('/api/v1/applications/'.$id.'/corrections')->assertOk()->assertJsonCount(1, 'requests');
        $this->actingAs($this->authority)->postJson('/api/v1/corrections/'.$request.'/decision', ['approved' => true, 'reason' => 'Aprobación independiente con respaldo'])->assertOk();
        $this->assertEquals($publication, DB::table('publications')->first());
        $this->assertDatabaseHas('offers', ['id' => $this->offer, 'needs_publication_review' => true]);
        $this->assertDatabaseCount('publications', 1);
        $this->assertDatabaseCount('award_choices', 1);
        $this->actingAs($this->student)->getJson('/api/v1/offers/'.$this->offer.'/ranking')->assertForbidden();
    }
}
