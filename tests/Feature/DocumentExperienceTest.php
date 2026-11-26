<?php

namespace Tests\Feature;

use App\Domain\DocumentaryJourney;
use App\Domain\DocumentProcessor;
use App\Domain\Participation;
use App\Domain\Rectifications;
use App\Domain\Selection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class DocumentExperienceTest extends TestCase
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
        $this->call = $s->createCall($this->authority, [
            'title' => 'Documentos legibles',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'exam_at' => now()->addDays(2),
            'scope' => 'UAGRM',
            'source' => 'Fixture sintético',
        ]);
        foreach ([[$this->reviewer, 'reviewer'], [$this->evaluator, 'evaluator']] as [$u, $role]) {
            DB::table('memberships')->insert(['user_id' => $u->id, 'call_id' => $this->call, 'role' => $role]);
        }
        $category = DB::table('categories')->insertGetId(['name' => 'Tipo documental', 'regime' => 'docencia', 'active' => true]);
        $this->offer = $s->saveOffer($this->authority, $this->call, [
            'category_id' => $category,
            'title' => 'Oferta documental',
            'seats' => 1,
            'is_subject' => false,
            'modality' => 'Presencial',
            'documents' => ['cpd' => 'CPD'],
            'requirements' => ['activo' => 'Activo'],
            'weights' => ['Software' => '100'],
            'scheme' => 'DEMO_AREAS_100',
            'source' => 'Fixture sintético',
        ]);
        $s->transition($this->authority, $this->call, 'published');
        $this->application = $s->apply($this->student, $this->offer);
        $this->document = $s->upload($this->student, $this->application, 'cpd', new UploadedFile(resource_path('demo-documents/cpd.pdf'), 'respaldo.pdf', 'application/pdf', null, true));
        $this->scanApplicantFixtures();
    }

    public function test_e01_pdf_download_and_inline_preview_preserve_bytes_and_mime(): void
    {
        foreach (['' => 'attachment', '/ver' => 'inline'] as $suffix => $disposition) {
            $r = $this->actingAs($this->student)->get('/documentos/'.$this->document.$suffix)->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringContainsString($disposition, $r->headers->get('Content-Disposition'));
            $this->assertSame(file_get_contents(resource_path('demo-documents/cpd.pdf')), $r->getContent());
            $r->assertHeader('X-Content-Type-Options', 'nosniff');
        }
    }

    public function test_e02_all_file_routes_require_owner_or_scoped_reviewer(): void
    {
        foreach ([$this->authority, $this->evaluator, User::factory()->create(['role' => 'admin']), User::factory()->create()] as $u) {
            foreach (['', '/ver', '/detalle'] as $suffix) {
                $this->actingAs($u)->get('/documentos/'.$this->document.$suffix)->assertForbidden();
            }
        }
        $this->actingAs($this->reviewer)->get('/documentos/'.$this->document.'/detalle')->assertOk()->assertSee('respaldo.pdf')->assertSee('PDF');
        $this->get('/documentos/'.$this->document.'/ver')->assertOk();
    }

    public function test_e03_file_tampering_is_blocked_before_serving_any_bytes(): void
    {
        $doc = DB::table('documents')->find($this->document);
        Storage::disk('local')->put($doc->path, 'Contenido cambiado');
        foreach (['', '/ver', '/detalle'] as $suffix) {
            $this->actingAs($this->student)->get('/documentos/'.$this->document.$suffix)->assertStatus(409);
        }
    }

    public function test_e04_missing_file_returns_not_found_without_replacing_record(): void
    {
        $doc = DB::table('documents')->find($this->document);
        Storage::disk('local')->delete($doc->path);
        $this->actingAs($this->student)->get('/documentos/'.$this->document)->assertNotFound();
        $this->assertDatabaseHas('documents', ['id' => $doc->id, 'sha256' => $doc->sha256]);
    }

    public function test_e05_small_image_warning_does_not_relabel_or_resize_original(): void
    {
        $s = app(Selection::class);
        $id = $s->upload($this->student, $this->application, 'cpd', UploadedFile::fake()->image('pequena.png', 1, 1));
        $this->scanApplicantFixtures();
        $doc = DB::table('documents')->find($id);
        $this->actingAs($this->student)->get('/documentos/'.$id.'/detalle')->assertSee('Imagen de tamaño reducido')->assertSee('1 × 1');
        $r = $this->get('/documentos/'.$id)->assertHeader('Content-Type', 'image/png');
        $this->assertSame(Storage::disk('local')->get($doc->path), $r->getContent());
    }

    public function test_e06_samples_are_marked_pdfs_and_do_not_allow_arbitrary_paths(): void
    {
        foreach (['carta', 'cpd', 'ci', 'academico', 'autonomia', 'cv'] as $kind) {
            $r = $this->actingAs($this->student)->get('/ejemplos/documentos/'.$kind)->assertOk()->assertDownload('MUESTRA-'.$kind.'.pdf')->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', file_get_contents($r->baseResponse->getFile()->getPathname()));
        }
        $this->get('/ejemplos/documentos/env')->assertNotFound();
    }

    public function test_e07_role_guides_show_relevant_responsibilities_and_not_applicant_menu_for_staff(): void
    {
        foreach ([
            [$this->student, 'Como postulante'],
            [$this->reviewer, 'Como Revisor'],
            [$this->evaluator, 'Como Evaluador'],
            [$this->authority, 'Como Autoridad'],
            [User::factory()->create(['role' => 'admin']), 'Como administrador técnico'],
        ] as [$u, $heading]) {
            $r = $this->actingAs($u)->get('/ayuda')->assertOk()->assertSee($heading);
            if ($u->role !== 'student') {
                $r->assertDontSee('>Mis postulaciones</a>', false);
            }
        }
    }

    public function test_e08_admin_cannot_create_academic_call_using_hidden_post_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/convocatorias', [
            'title' => 'No autorizada',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'exam_at' => now()->addDays(2),
            'scope' => 'UAGRM',
            'source' => 'Intento por cuenta técnica',
        ])->assertForbidden();
        $this->assertDatabaseCount('calls', 1);
    }

    public function test_e09_student_with_evaluator_permission_cannot_grade_own_application(): void
    {
        $s = app(Selection::class);
        $s->submit($this->student, $this->application);
        $s->review($this->reviewer, $this->application, ['doc:cpd' => 'meets', 'activo' => 'meets'], 'Revisión sintética completa');
        $s->eligibility($this->reviewer, $this->application, true, 'Requisitos sintéticos verificados');
        DB::table('memberships')->insert(['user_id' => $this->student->id, 'call_id' => $this->call, 'role' => 'evaluator']);
        $s->assign($this->authority, $this->offer, $this->student->id, 'Software');
        $s->transition($this->authority, $this->call, 'closed');
        $s->transition($this->authority, $this->call, 'evaluation');
        $this->actingAs($this->student)->post('/postulaciones/'.$this->application.'/nota', [
            'assignment_id' => DB::table('assignments')->value('id'),
            'score' => '100',
            'intent' => 'confirm',
        ])->assertForbidden();
        $this->get('/postulaciones/'.$this->application)->assertDontSee('Confirmar nota');
        $this->assertDatabaseCount('grades', 0);
    }

    public function test_e10_revoked_reviewer_loses_metadata_preview_and_download(): void
    {
        DB::table('memberships')->where('user_id', $this->reviewer->id)->update(['revoked_at' => now()]);
        foreach (['', '/ver', '/detalle'] as $suffix) {
            $this->actingAs($this->reviewer)->get('/documentos/'.$this->document.$suffix)->assertForbidden();
        }
    }

    public function test_e11_authority_cannot_approve_rectification_of_own_application(): void
    {
        $s = app(Selection::class);
        $s->submit($this->student, $this->application);
        $s->merit($this->reviewer, $this->application, '20', 'CPD-MUESTRA');
        // B-02: la evaluación solo se abre con las postulaciones revisadas; esta prueba trata de rectificaciones.
        DB::table('applications')->where('id', $this->application)->update(['eligibility' => 'enabled']);
        $s->transition($this->authority, $this->call, 'closed');
        $s->transition($this->authority, $this->call, 'evaluation');
        DB::table('memberships')->insert(['user_id' => $this->student->id, 'call_id' => $this->call, 'role' => 'authority']);
        $request = app(Rectifications::class)->request($this->reviewer, $this->application, null, '25', 'Corrección sintética de méritos', 'Referencia sintética CPD');
        $this->actingAs($this->student)->post('/rectificaciones/'.$request.'/decision', [
            'approved' => '1',
            'reason' => 'Decisión sobre postulación propia',
        ])->assertForbidden();
        $this->assertDatabaseHas('applications', ['id' => $this->application, 'merit' => '20.00']);
    }

    public function test_e12_readable_demo_adds_scenarios_once_without_replacing_existing_documents(): void
    {
        foreach ([
            [$this->authority, 'autoridad'],
            [$this->reviewer, 'revisor'],
            [$this->evaluator, 'docente'],
            [$this->student, 'estudiante'],
        ] as [$u, $prefix]) {
            $u->email = $prefix.'@ficct.test';
            $u->save();
        }
        foreach (['bruno', 'carla'] as $prefix) {
            User::factory()->create(['email' => $prefix.'@ficct.test']);
        }
        DB::table('categories')->insert(['name' => 'Software', 'regime' => 'docencia', 'active' => true]);
        $before = DB::table('documents')->find($this->document);
        $environment = app()->environment();
        app()->detectEnvironment(fn () => 'local');
        try {
            $this->useApplicantFixtureScanner();
            $first = app(DocumentaryJourney::class)->run();
            $counts = [DB::table('calls')->count(), DB::table('documents')->count(), DB::table('audit_events')->count()];
            $this->assertSame($first, app(DocumentaryJourney::class)->run());
            $this->assertSame($counts, [DB::table('calls')->count(), DB::table('documents')->count(), DB::table('audit_events')->count()]);
            $this->assertDatabaseHas('calls', ['id' => $first['recepcion'], 'status' => 'published']);
            $this->assertDatabaseHas('calls', ['id' => $first['evaluacion'], 'status' => 'evaluation']);
            $this->assertSame(12, DB::table('documents')->where('mime', 'application/pdf')->where('id', '!=', $this->document)->count());
            $this->assertEquals($before, DB::table('documents')->find($this->document));
        } finally {
            app()->detectEnvironment(fn () => $environment);
        }
    }

    public function test_e13_open_calls_are_first_and_staff_history_is_not_labeled_as_current_application(): void
    {
        $copy = (array) DB::table('calls')->find($this->call);
        unset($copy['id']);
        $copy['title'] = 'Antecedente cerrado';
        $copy['status'] = 'archived';
        DB::table('calls')->insert($copy);
        $this->actingAs($this->student)->get('/')->assertViewHas('calls', fn ($calls) => $calls->first()->id === $this->call);
        $this->student->role = 'staff';
        $this->student->save();
        $this->get('/')->assertOk()->assertSee('Mis expedientes anteriores')->assertDontSee('>Mis postulaciones</a>', false);
    }

    public function test_e14_archived_records_show_history_without_action_forms(): void
    {
        DB::table('calls')->where('id', $this->call)->update(['status' => 'archived']);
        $this->actingAs($this->authority)->get('/ofertas/'.$this->offer.'/resultados')->assertOk()->assertDontSee('Confirmar selección y snapshot');
        $this->actingAs($this->student)->get('/postulaciones/'.$this->application.'/procesos')->assertOk()->assertDontSee('Presentar solicitud DEMO')->assertSee('Versiones inmutables');
    }

    public function test_e15_reviewer_cannot_close_own_amendment_and_change_own_eligibility(): void
    {
        $s = app(Selection::class);
        $s->submit($this->student, $this->application);
        $s->review($this->reviewer, $this->application, ['doc:cpd' => 'observed'], 'Observación sintética del respaldo');
        $participation = app(Participation::class);
        $id = $participation->open($this->reviewer, $this->application, ['cpd'], now()->addHour()->toDateTimeString(), 'Ventana sintética por observación', 'Referencia de prueba documental');
        DB::table('memberships')->insert(['user_id' => $this->student->id, 'call_id' => $this->call, 'role' => 'reviewer']);
        $this->travel(2)->hours();
        $this->actingAs($this->student)->post('/subsanaciones/'.$id.'/vencer', [
            'reason' => 'Cierre intentado por el propietario',
        ])->assertForbidden();
        $this->assertDatabaseHas('amendments', ['id' => $id, 'status' => 'open']);
    }

    public function test_e16_evaluator_is_not_offered_rectification_until_a_grade_exists(): void
    {
        $s = app(Selection::class);
        $s->submit($this->student, $this->application);
        $s->review($this->reviewer, $this->application, ['doc:cpd' => 'meets', 'activo' => 'meets'], 'Revisión sintética completa');
        $s->eligibility($this->reviewer, $this->application, true, 'Requisitos sintéticos verificados');
        $s->assign($this->authority, $this->offer, $this->evaluator->id, 'Software');
        $s->transition($this->authority, $this->call, 'closed');
        $s->transition($this->authority, $this->call, 'evaluation');
        $this->actingAs($this->evaluator)->get('/postulaciones/'.$this->application)->assertOk()->assertSee('Confirmar nota')->assertDontSee('Rectificaciones con aprobación separada');
        $s->grade($this->evaluator, $this->application, DB::table('assignments')->value('id'), '80');
        $this->get('/postulaciones/'.$this->application)->assertOk()->assertDontSee('Confirmar nota')->assertSee('Rectificaciones con aprobación separada');
    }

    public function test_e17_api_reviewer_download_is_the_uploaded_original_with_safe_metadata(): void
    {
        $original = file_get_contents(resource_path('demo-documents/cpd.pdf'));
        $this->actingAs($this->reviewer)->get('/api/v1/documents/'.$this->document.'/download')
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertDownload('respaldo.pdf')->assertContent($original);
        $this->getJson('/api/v1/documents/'.$this->document.'/metadata')->assertOk()
            ->assertJsonPath('size_bytes', strlen($original))->assertJsonPath('sha256', hash('sha256', $original))
            ->assertJsonPath('small_image', false)->assertJsonMissingPath('path');
        $this->actingAs($this->student)->get('/api/v1/documents/'.$this->document.'/download')->assertOk()->assertContent($original);
    }

    public function test_e18_api_download_and_metadata_reject_other_roles_and_revoked_reviewers(): void
    {
        foreach ([$this->authority, $this->evaluator, User::factory()->create(['role' => 'admin']), User::factory()->create()] as $user) {
            foreach (['download', 'metadata'] as $action) {
                $this->actingAs($user)->getJson('/api/v1/documents/'.$this->document.'/'.$action)->assertForbidden();
            }
        }
        DB::table('memberships')->where('user_id', $this->reviewer->id)->update(['revoked_at' => now()]);
        $this->actingAs($this->reviewer)->getJson('/api/v1/documents/'.$this->document.'/download')->assertForbidden();
    }

    public function test_e19_api_metadata_and_download_check_pending_tampered_and_missing_bytes(): void
    {
        $this->actingAs($this->reviewer);
        $doc = DB::table('documents')->find($this->document);
        Storage::disk('local')->put($doc->path, 'Archivo alterado');
        foreach (['download', 'metadata'] as $action) {
            $this->getJson('/api/v1/documents/'.$this->document.'/'.$action)->assertStatus(409);
        }
        Storage::disk('local')->delete($doc->path);
        $this->getJson('/api/v1/documents/'.$this->document.'/download')->assertNotFound();
        $this->getJson('/api/v1/documents/'.$this->document.'/metadata')->assertNotFound();
        app(DocumentProcessor::class)->enqueue($this->document, 'applicant');
        $this->getJson('/api/v1/documents/'.$this->document.'/download')->assertStatus(423);
        $this->getJson('/api/v1/documents/'.$this->document.'/metadata')->assertStatus(423);
    }

    public function test_e20_api_small_image_details_do_not_resize_the_original(): void
    {
        $id = app(Selection::class)->upload($this->student, $this->application, 'cpd', UploadedFile::fake()->image('original.png', 1, 1));
        $this->scanApplicantFixtures();
        $doc = DB::table('documents')->find($id);
        $this->actingAs($this->reviewer)->getJson('/api/v1/documents/'.$id.'/metadata')->assertOk()
            ->assertJsonPath('width', 1)->assertJsonPath('height', 1)->assertJsonPath('small_image', true);
        $this->get('/api/v1/documents/'.$id.'/download')->assertOk()->assertHeader('Content-Type', 'image/png')->assertContent(Storage::disk('local')->get($doc->path));
    }
}
