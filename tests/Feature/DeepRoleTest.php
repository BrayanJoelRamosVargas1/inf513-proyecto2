<?php

namespace Tests\Feature;

use App\Domain\Administration;
use App\Domain\DocumentProcessor;
use App\Domain\Participation;
use App\Domain\Rectifications;
use App\Domain\Selection;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DeepRoleTest extends TestCase
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
        $this->call = $s->createCall($this->authority, [
            'title' => 'Convocatoria REST de prueba',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'exam_at' => now()->addDays(2),
            'scope' => 'UAGRM',
            'source' => 'Fixture sintÃ©tico de API',
        ]);
        foreach ([[$this->reviewer, 'reviewer'], [$this->evaluator, 'evaluator']] as [$u, $role]) {
            DB::table('memberships')->insert(['user_id' => $u->id, 'call_id' => $this->call, 'role' => $role]);
        }
        $category = DB::table('categories')->insertGetId(['name' => 'Software API', 'regime' => 'docencia', 'active' => true]);
        $this->offer = $s->saveOffer($this->authority, $this->call, [
            'category_id' => $category,
            'title' => 'Oferta REST',
            'seats' => 1,
            'is_subject' => false,
            'modality' => 'Presencial',
            'documents' => ['cpd' => 'CPD'],
            'requirements' => ['activo' => 'Activo'],
            'weights' => ['Software' => '100'],
            'scheme' => 'DEMO_AREAS_100',
            'source' => 'Fixture sintÃ©tico de API',
        ]);
        $s->assign($this->authority, $this->offer, $this->evaluator->id, 'Software');
        $s->transition($this->authority, $this->call, 'published');
    }

    public function test_d01_participation_does_not_serialize_other_candidates_publication_snapshot(): void
    {
        $id = app(Selection::class)->apply($this->student, $this->offer);
        $snapshot = json_encode([
            'selected' => [$id],
            'rank' => [['id' => 999, 'eligibility_reason' => 'PRIVADO-DE-OTRO-POSTULANTE', 'cpd_reference' => 'CPD-RESERVADO']],
        ]);
        $pub = DB::table('publications')->insertGetId([
            'offer_id' => $this->offer,
            'version' => 1,
            'snapshot' => $snapshot,
            'minutes' => 'Acta sintética de prueba de privacidad',
            'user_id' => $this->authority->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $before = DB::table('publications')->find($pub);
        $response = $this->actingAs($this->student)->getJson('/api/v1/applications/'.$id.'/participation')->assertOk();
        $response->assertJsonPath('award.id', $pub)->assertJsonMissingPath('award.snapshot');
        $this->assertStringNotContainsString('PRIVADO-DE-OTRO', $response->getContent());
        $this->assertEquals($before, DB::table('publications')->find($pub));
    }

    public function test_d02_enable_capability_requires_saved_complete_review_and_current_documents(): void
    {
        $s = app(Selection::class);
        $id = $s->apply($this->student, $this->offer);
        $s->upload($this->student, $id, 'cpd', UploadedFile::fake()->image('cpd.png'));
        $this->scanApplicantFixtures();
        $s->submit($this->student, $id);
        $this->actingAs($this->reviewer)->getJson('/api/v1/applications/'.$id)->assertOk()->assertJsonPath('can_enable', false);
        $s->review($this->reviewer, $id, ['doc:cpd' => 'meets'], 'Revisión parcial sintética documentada');
        $this->getJson('/api/v1/applications/'.$id)->assertJsonPath('can_enable', false)->assertJsonFragment(['criterion' => 'doc:cpd', 'decision' => 'meets']);
        $s->review($this->reviewer, $id, ['activo' => 'meets'], 'Revisión completa sintética documentada');
        $this->getJson('/api/v1/applications/'.$id)->assertJsonPath('can_enable', true);
        $document = DB::table('documents')->where('application_id', $id)->value('id');
        app(DocumentProcessor::class)->enqueue($document, 'applicant');
        $this->getJson('/api/v1/applications/'.$id)->assertJsonPath('can_enable', false);
        $this->actingAs($this->student)->getJson('/api/v1/applications/'.$id)->assertJsonPath('can_enable', false);
    }

    public function test_d03_participant_receives_the_recorded_decision_of_their_request_only(): void
    {
        $s = app(Selection::class);
        $id = $s->apply($this->student, $this->offer);
        $participation = app(Participation::class);
        $request = $participation->request($this->student, $id, 'withdrawal', 'Solicitud sintética del interesado', 'Respaldo sintético de la solicitud');
        $participation->decide($this->authority, $request, false, 'Se rechaza por el motivo sintético registrado');
        $response = $this->actingAs($this->student)->getJson('/api/v1/applications/'.$id.'/participation')->assertOk();
        $response->assertJsonPath('decisions.'.$request.'.approved', false)->assertJsonPath('decisions.'.$request.'.reason', 'Se rechaza por el motivo sintético registrado');
        $other = User::factory()->create();
        $this->actingAs($other)->getJson('/api/v1/applications/'.$id.'/participation')->assertForbidden();
    }

    public function test_d04_reassigned_evaluator_sees_the_area_grade_without_changing_its_author(): void
    {
        $s = app(Selection::class);
        $id = $s->apply($this->student, $this->offer);
        $s->upload($this->student, $id, 'cpd', UploadedFile::fake()->image('cpd.png'));
        $this->scanApplicantFixtures();
        $s->submit($this->student, $id);
        $s->review($this->reviewer, $id, ['doc:cpd' => 'meets', 'activo' => 'meets'], 'Criterios cotejados en prueba sintética');
        $s->eligibility($this->reviewer, $id, true, 'Habilitación de prueba sintética');
        $s->transition($this->authority, $this->call, 'closed');
        $s->transition($this->authority, $this->call, 'evaluation');
        $assignment = DB::table('assignments')->where('offer_id', $this->offer)->first();
        $s->grade($this->evaluator, $id, $assignment->id, '80');
        $grade = DB::table('grades')->where('application_id', $id)->first();
        $next = User::factory()->create(['role' => 'staff']);
        DB::table('memberships')->insert(['user_id' => $next->id, 'call_id' => $this->call, 'role' => 'evaluator']);
        app(Administration::class)->reassign($this->authority, $assignment->id, $next->id, $assignment->version, 'Reasignación sintética con historial conservado');
        $this->actingAs($next)->getJson('/api/v1/applications/'.$id)->assertOk()
            ->assertJsonPath('grades.0.id', $grade->id)->assertJsonPath('grades.0.user_id', $this->evaluator->id)
            ->assertJsonPath('grades.0.score', '80.00')->assertJsonPath('can_grade', false)->assertJsonPath('can_view_corrections', true);
        $this->getJson('/api/v1/applications/'.$id.'/corrections')->assertOk()->assertJsonCount(1, 'grades')->assertJsonMissingPath('app.merit');
        $this->postJson('/api/v1/applications/'.$id.'/nota', [
            'assignment_id' => $assignment->id,
            'score' => '90',
            'intent' => 'confirm',
        ])->assertUnprocessable();
        $this->actingAs($this->evaluator)->getJson('/api/v1/applications/'.$id)->assertForbidden();
        $this->assertEquals($grade, DB::table('grades')->find($grade->id));
    }

    private function evaluatedApplicationForMetrics(): array
    {
        $s = app(Selection::class);
        $id = $s->apply($this->student, $this->offer);
        $s->upload($this->student, $id, 'cpd', UploadedFile::fake()->image('cpd.png'));
        $this->scanApplicantFixtures();
        $s->submit($this->student, $id);
        $s->review($this->reviewer, $id, ['doc:cpd' => 'meets', 'activo' => 'meets'], 'Revisión completa en escenario sintético');
        $s->eligibility($this->reviewer, $id, true, 'Criterios sintéticos comprobados');
        $s->merit($this->reviewer, $id, '35.25', 'CPD sintético');
        $s->transition($this->authority, $this->call, 'closed');
        $s->transition($this->authority, $this->call, 'evaluation');
        $assignment = DB::table('assignments')->where('offer_id', $this->offer)->first();
        $s->grade($this->evaluator, $id, $assignment->id, '80');

        return [$id, DB::table('grades')->where('application_id', $id)->first()];
    }

    public function test_d05_metrics_include_a_confirmed_grade_after_authorized_rectification(): void
    {
        [$id, $grade] = $this->evaluatedApplicationForMetrics();
        $service = app(Rectifications::class);
        $request = $service->request($this->evaluator, $id, $grade->id, '90', 'Corrección de transcripción sintética', 'Acta sintética de respaldo');
        $service->decide($this->authority, $request, true, 'Aprobación independiente documentada');
        $this->actingAs($this->student)->getJson('/api/v1/metrics')->assertOk()->assertJsonPath('confirmed_exams', 1);
        $this->actingAs($this->evaluator)->getJson('/api/v1/metrics')->assertOk()->assertJsonPath('confirmed_exams', 1);
        $this->assertDatabaseHas('grades', ['id' => $grade->id, 'status' => 'corrected', 'score' => '90.00']);
    }

    public function test_d06_metrics_require_current_evaluator_permission_even_if_actor_remains_a_reviewer(): void
    {
        $this->evaluatedApplicationForMetrics();
        DB::table('memberships')->where('user_id', $this->evaluator->id)->where('role', 'evaluator')->update(['revoked_at' => now()]);
        DB::table('memberships')->insert(['user_id' => $this->evaluator->id, 'call_id' => $this->call, 'role' => 'reviewer']);
        $this->actingAs($this->evaluator)->getJson('/api/v1/metrics')->assertOk()->assertJsonPath('applications', 1)->assertJsonPath('confirmed_exams', 0);
    }

    public function test_d07_revoked_evaluator_retaining_review_permission_cannot_read_exam_grade_history(): void
    {
        [$id] = $this->evaluatedApplicationForMetrics();
        $this->actingAs($this->evaluator)->getJson('/api/v1/applications/'.$id.'/corrections')->assertOk()->assertJsonCount(1, 'grades');
        DB::table('memberships')->where('user_id', $this->evaluator->id)->where('role', 'evaluator')->update(['revoked_at' => now()]);
        DB::table('memberships')->insert(['user_id' => $this->evaluator->id, 'call_id' => $this->call, 'role' => 'reviewer']);
        $this->getJson('/api/v1/applications/'.$id.'/corrections')->assertOk()->assertJsonCount(0, 'grades')->assertJsonCount(1, 'versions')->assertJsonPath('versions.0.grade_id', null);
        $this->get('/postulaciones/'.$id.'/rectificaciones')->assertOk()->assertDontSee('80.00');
    }

    public function test_d08_invalid_document_format_is_explained_in_spanish(): void
    {
        $id = app(Selection::class)->apply($this->student, $this->offer);
        $response = $this->actingAs($this->student)->post('/api/v1/applications/'.$id.'/archivo', [
            'kind' => 'cpd',
            'file' => UploadedFile::fake()->createWithContent('respaldo.txt', 'Texto de prueba'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertStringContainsString('formato', $response->json('errors.file.0'));
        $this->assertStringNotContainsString('validation.', $response->getContent());
    }

    public function test_d10_audit_history_requires_current_authority_and_its_footer_is_idempotent(): void
    {
        $this->actingAs($this->authority)->getJson('/api/v1/calls/'.$this->call.'/audit')
            ->assertOk()->assertJsonPath('events.data.0.action', 'call.transition')->assertJsonFragment(['action' => 'evaluator.assigned']);
        $key = (string) Str::uuid();
        $path = '/convocatorias/'.$this->call.'/auditoria';
        foreach ([1, 2] as $_) {
            $this->postJson('/api/v1/visits', ['path' => $path, 'key' => $key])->assertOk()->assertJsonPath('total', 1);
        }
        $this->assertDatabaseHas('page_visits', ['path' => $path, 'total' => 1]);
        $this->actingAs($this->reviewer)->getJson('/api/v1/calls/'.$this->call.'/audit')->assertForbidden();
        DB::table('memberships')->where('user_id', $this->authority->id)->where('call_id', $this->call)->update(['revoked_at' => now()]);
        $this->actingAs($this->authority)->getJson('/api/v1/calls/'.$this->call.'/audit')->assertForbidden();
    }

    public function test_d11_html_permission_renewal_preserves_dates_when_reusing_the_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $start = now()->subDay()->setMicrosecond(0);
        $end = now()->addDays(3)->setMicrosecond(0);
        DB::table('memberships')->where('user_id', $this->reviewer->id)->where('call_id', $this->call)->update(['starts_at' => $start, 'expires_at' => $end]);
        $response = $this->actingAs($admin)->get('/usuarios')->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $form = $xpath->query('//form[@action="/permisos"][input[@name="user_id"][@value="'.$this->reviewer->id.'"]]')->item(0);
        $this->assertNotNull($form);
        $values = [];
        foreach ($xpath->query('.//input[@name]', $form) as $input) {
            $values[$input->getAttribute('name')] = $input->getAttribute('value');
        }
        $this->assertSame($start->format('Y-m-d\TH:i:s'), $values['starts_at']);
        $this->assertSame($end->format('Y-m-d\TH:i:s'), $values['expires_at']);
        $values['reason'] = 'Renovación de ensayo sin modificar la vigencia existente';
        $this->post('/permisos', $values)->assertSessionHasNoErrors();
        $membership = DB::table('memberships')->where('user_id', $this->reviewer->id)->where('call_id', $this->call)->first();
        $this->assertSame($end->timestamp, Carbon::parse($membership->expires_at)->timestamp);
    }

    public function test_d09_missing_scope_and_invalid_year_have_spanish_field_messages(): void
    {
        $response = $this->actingAs($this->authority)->postJson('/api/v1/calls', [
            'title' => 'Convocatoria sintética',
            'opens_at' => now()->toIso8601String(),
            'closes_at' => now()->addDay()->toIso8601String(),
            'exam_at' => now()->addDays(2)->toIso8601String(),
            'scope' => 'faculty',
            'source' => 'Fuente sintética',
            'management_year' => 23,
        ])->assertUnprocessable()->assertJsonValidationErrors(['scope_value']);
        $this->assertStringContainsString('facultad o carrera', $response->json('errors.scope_value.0'));
        $response = $this->postJson('/api/v1/calls', [
            'title' => 'Convocatoria sintética',
            'opens_at' => now()->toIso8601String(),
            'closes_at' => now()->addDay()->toIso8601String(),
            'exam_at' => now()->addDays(2)->toIso8601String(),
            'scope' => 'UAGRM',
            'source' => 'Fuente sintética',
            'management_year' => 23,
        ])->assertUnprocessable()->assertJsonValidationErrors(['management_year']);
        $this->assertStringContainsString('dígitos', $response->json('errors.management_year.0'));
    }
}
