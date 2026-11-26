<?php

namespace Tests\Feature;

use App\Domain\Selection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class RoleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Selection $s;

    private User $authority;

    private User $reviewer;

    private User $evaluator;

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
        $this->evaluator = User::factory()->create(['role' => 'staff']);
        $this->student = User::factory()->create(['role' => 'student']);
        $this->call = $this->s->createCall($this->authority, [
            'title' => 'Convocatoria de recorrido',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'exam_at' => now()->addDays(2),
            'scope' => 'UAGRM',
            'source' => 'Fixture sintético',
        ]);
        foreach ([[$this->reviewer, 'reviewer'], [$this->evaluator, 'evaluator']] as [$u, $role]) {
            DB::table('memberships')->insert(['user_id' => $u->id, 'call_id' => $this->call, 'role' => $role]);
        }
        $category = DB::table('categories')->insertGetId(['name' => 'Tipo de recorrido', 'regime' => 'docencia', 'active' => true]);
        $this->offer = $this->s->saveOffer($this->authority, $this->call, [
            'category_id' => $category,
            'title' => 'Oferta de recorrido',
            'seats' => 1,
            'is_subject' => false,
            'modality' => 'Presencial',
            'documents' => ['cpd' => 'Formulario CPD'],
            'requirements' => ['activo' => 'Estudiante activo'],
            'weights' => ['Software' => '100'],
            'scheme' => 'DEMO_AREAS_100',
            'source' => 'Fixture sintético',
        ]);
        $this->s->transition($this->authority, $this->call, 'published');
    }

    private function submitted(): int
    {
        $id = $this->s->apply($this->student, $this->offer);
        $this->s->upload($this->student, $id, 'cpd', UploadedFile::fake()->image('cpd.png'));
        $this->scanApplicantFixtures();
        $this->s->submit($this->student, $id);

        return $id;
    }

    public function test_r01_student_completes_application_and_resumes_without_duplicate_button(): void
    {
        $this->actingAs($this->student)->get('/')->assertOk()->assertSee('Mi postulación')->assertSee('Confirma el envío');
        $this->get('/convocatorias/'.$this->call)->assertSee('Iniciar postulación');
        $this->post('/ofertas/'.$this->offer.'/postular')->assertRedirect();
        $id = DB::table('applications')->value('id');
        $this->get('/convocatorias/'.$this->call)->assertSee('Continuar mi postulación')->assertDontSee('Iniciar postulación');
        $this->get('/postulaciones/'.$id)->assertSee('Faltan 1 documentos obligatorios')->assertSee('disabled', false);
        $this->post('/postulaciones/'.$id.'/enviar')->assertSessionHasErrors('proceso');
        $this->post('/postulaciones/'.$id.'/archivo', [
            'kind' => 'cpd',
            'file' => UploadedFile::fake()->image('cpd.png'),
        ])->assertSessionHasNoErrors();
        $this->post('/postulaciones/'.$id.'/enviar')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('applications', ['id' => $id, 'status' => 'submitted']);
        $this->get('/convocatorias/'.$this->call)->assertSee('Consultar mi postulación')->assertDontSee('Iniciar postulación');
        $this->get('/postulaciones/'.$id)->assertDontSee('Confirmar envío definitivo');
    }

    public function test_r02_expired_receiving_hides_apply_and_draft_upload_and_server_rejects(): void
    {
        $id = $this->s->apply($this->student, $this->offer);
        $this->travel(2)->days();
        $this->actingAs($this->student)->get('/postulaciones/'.$id)->assertOk()->assertSee('Tu borrador está conservado')->assertDontSee('Guardar archivo');
        $other = User::factory()->create();
        $this->actingAs($other)->get('/convocatorias/'.$this->call)->assertDontSee('Iniciar postulación')->assertSee('Recepción no disponible');
        $this->post('/ofertas/'.$this->offer.'/postular')->assertSessionHasErrors('proceso');
    }

    public function test_r03_future_receiving_and_wrong_scope_do_not_offer_apply(): void
    {
        $this->travel(-2)->days();
        $this->actingAs($this->student)->get('/convocatorias/'.$this->call)->assertDontSee('Iniciar postulación');
        $this->travelBack();
        DB::table('calls')->where('id', $this->call)->update(['scope' => 'faculty', 'scope_value' => 'Otra facultad']);
        $this->get('/convocatorias/'.$this->call)->assertSee('Su perfil no pertenece')->assertDontSee('Iniciar postulación');
        $this->post('/ofertas/'.$this->offer.'/postular')->assertSessionHasErrors('proceso');
    }

    public function test_r04_staff_navigation_and_queues_are_scoped(): void
    {
        $id = $this->submitted();
        $this->actingAs($this->reviewer)->get('/')->assertOk()->assertSee('Atender expediente #'.$id)->assertSee('Revisar documentos')->assertDontSee('Mis adjudicaciones')->assertDontSee('Usuarios y permisos');
        $this->actingAs($this->evaluator)->get('/')->assertOk()->assertSee('Calificar las áreas asignadas')->assertDontSee('Atender expediente #'.$id)->assertDontSee('Mis adjudicaciones')->assertDontSee('Registro del formulario CPD');
        $unassigned = User::factory()->create(['role' => 'staff']);
        $this->actingAs($unassigned)->get('/')->assertDontSee('Convocatoria de recorrido')->assertDontSee('href="/rectificaciones"', false);
    }

    public function test_r05_admin_and_authority_have_distinct_home_actions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/')->assertOk()->assertSee('Administración del sistema')->assertSee('Administrar usuarios y permisos')->assertDontSee('Crear convocatoria DEMO')->assertDontSee('href="/adjudicaciones"', false);
        $this->actingAs($this->authority)->get('/')->assertOk()->assertSee('Gestión de convocatorias')->assertSee('Crear convocatoria DEMO')->assertDontSee('Administrar usuarios y permisos');
    }

    public function test_r06_combined_responsibilities_preserve_own_application_and_expired_permissions_disappear(): void
    {
        $this->submitted();
        DB::table('memberships')->insert(['user_id' => $this->student->id, 'call_id' => $this->call, 'role' => 'reviewer']);
        $this->actingAs($this->student)->get('/')->assertSee('Mis postulaciones')->assertSee('Revisar documentos')->assertSee('href="/rectificaciones"', false);
        DB::table('memberships')->where('user_id', $this->student->id)->update(['expires_at' => now()->subSecond()]);
        $this->get('/')->assertSee('Mis postulaciones')->assertDontSee('Revisar documentos')->assertDontSee('href="/rectificaciones"', false);
    }

    public function test_r07_self_review_habilitation_and_merit_are_forbidden_on_server(): void
    {
        $id = $this->submitted();
        DB::table('memberships')->insert(['user_id' => $this->student->id, 'call_id' => $this->call, 'role' => 'reviewer']);
        $before = DB::table('applications')->find($id);
        $this->actingAs($this->student)->post('/postulaciones/'.$id.'/revision', [
            'decisions' => ['doc:cpd' => 'meets'],
            'reason' => 'Intento de revisar expediente propio',
        ])->assertForbidden();
        $this->post('/postulaciones/'.$id.'/habilitacion', ['enabled' => '0', 'reason' => 'Intento de decidir expediente propio'])->assertForbidden();
        $this->post('/postulaciones/'.$id.'/meritos', ['score' => '40', 'reference' => 'CPD-PROPIO'])->assertForbidden();
        $this->assertEquals($before, DB::table('applications')->find($id));
        $this->assertDatabaseCount('reviews', 0);
        $this->get('/postulaciones/'.$id)->assertDontSee('Guardar revisión por criterio')->assertDontSee('Confirmar méritos');
    }

    public function test_r08_evaluator_has_no_review_reason_or_private_documents_and_only_grades_in_stage(): void
    {
        $id = $this->submitted();
        $this->s->assign($this->authority, $this->offer, $this->evaluator->id, 'Software');
        $this->s->review($this->reviewer, $id, ['doc:cpd' => 'meets', 'activo' => 'meets'], 'Cotejo privado de evidencia académica');
        $this->s->eligibility($this->reviewer, $id, true, 'MOTIVO PRIVADO DE HABILITACION');
        $this->actingAs($this->evaluator)->get('/postulaciones/'.$id)->assertOk()->assertDontSee('MOTIVO PRIVADO')->assertDontSee('Expediente digital')->assertDontSee('Confirmar nota');
        $this->s->transition($this->authority, $this->call, 'closed');
        $this->s->transition($this->authority, $this->call, 'evaluation');
        $this->get('/postulaciones/'.$id)->assertSee('Confirmar nota')->assertDontSee('Guardar revisión por criterio');
        $this->get('/')->assertSee('Atender expediente #'.$id);
        $this->actingAs($this->student)->get('/postulaciones/'.$id)->assertSee('MOTIVO PRIVADO DE HABILITACION');
    }

    public function test_r09_subject_limit_blocks_new_offer_but_preserves_resume(): void
    {
        DB::table('offers')->where('id', $this->offer)->update(['is_subject' => true]);
        DB::table('calls')->where('id', $this->call)->update(['subject_limit' => 1]);
        $id = $this->s->apply($this->student, $this->offer);
        $copy = (array) DB::table('offers')->find($this->offer);
        unset($copy['id']);
        $copy['title'] = 'Otra asignatura';
        $other = DB::table('offers')->insertGetId($copy);
        $this->actingAs($this->student)->get('/convocatorias/'.$this->call)->assertSee('Continuar mi postulación')->assertSee('Alcanzó el límite de asignaturas')->assertDontSee('action="/ofertas/'.$other.'/postular"', false);
        $this->post('/ofertas/'.$other.'/postular')->assertSessionHasErrors('proceso');
        $this->assertDatabaseCount('applications', 1);
        $this->get('/postulaciones/'.$id)->assertOk();
    }

    public function test_r10_correction_form_requires_confirmed_value_and_correct_stage(): void
    {
        $id = $this->submitted();
        $this->actingAs($this->reviewer)->get('/postulaciones/'.$id.'/rectificaciones')->assertOk()->assertDontSee('Enviar a decisión de otra autoridad')->assertSee('El historial se conserva');
        $this->s->merit($this->reviewer, $id, '20', 'CPD-FICTICIO');
        // B-02: la evaluación solo se abre con las postulaciones revisadas; esta prueba trata de rectificaciones.
        DB::table('applications')->where('id', $id)->update(['eligibility' => 'enabled']);
        $this->s->transition($this->authority, $this->call, 'closed');
        $this->s->transition($this->authority, $this->call, 'evaluation');
        $this->get('/postulaciones/'.$id.'/rectificaciones')->assertSee('Enviar a decisión de otra autoridad');
    }

    public function test_r11_authority_calculation_waits_for_all_confirmed_grades(): void
    {
        $id = $this->submitted();
        $this->s->review($this->reviewer, $id, ['doc:cpd' => 'meets', 'activo' => 'meets'], 'Cotejo sintético completo');
        $this->s->eligibility($this->reviewer, $id, true, 'Revisión sintética completa');
        $this->s->merit($this->reviewer, $id, '20', 'CPD-FICTICIO');
        $this->s->assign($this->authority, $this->offer, $this->evaluator->id, 'Software');
        $this->s->transition($this->authority, $this->call, 'closed');
        $this->s->transition($this->authority, $this->call, 'evaluation');
        $this->actingAs($this->authority)->get('/postulaciones/'.$id)->assertSee('Cálculo pendiente')->assertDontSee('Calcular méritos + examen');
        $assignment = DB::table('assignments')->value('id');
        $this->s->grade($this->evaluator, $id, $assignment, '80');
        $this->get('/postulaciones/'.$id)->assertSee('Calcular méritos + examen');
        $this->post('/postulaciones/'.$id.'/calcular')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('applications', ['id' => $id, 'final' => '68.00']);
    }

    public function test_r12_new_registration_keeps_session_after_redirect_and_can_start_application(): void
    {
        $password = 'Clave-Sintetica-2026!';
        $this->post('/registro', [
            'name' => 'Postulante nuevo',
            'email' => 'nuevo@example.test',
            'password' => $password,
            'password_confirmation' => $password,
            'faculty' => 'FICCT',
            'career' => 'Informatica',
        ])->assertRedirect('/');
        $user = User::where('email', 'nuevo@example.test')->firstOrFail();
        $this->get('/')->assertOk()->assertSee('Mi postulación')->assertDontSee('Los permisos cambiaron');
        $this->assertAuthenticatedAs($user);
        $this->get('/convocatorias/'.$this->call)->assertOk()->assertSee('Iniciar postulación');
        $this->post('/ofertas/'.$this->offer.'/postular')->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('applications', ['user_id' => $user->id, 'offer_id' => $this->offer]);
    }
}
