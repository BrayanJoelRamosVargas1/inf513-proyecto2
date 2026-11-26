<?php

namespace Tests\Feature;

use App\Domain\Scores;
use App\Domain\Selection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

final class SelectionTest extends TestCase
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
        $this->call = $this->s->createCall($this->authority, [
            'title' => 'Fixture DEMO',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'exam_at' => now()->addDays(2),
            'scope' => 'UAGRM',
            'subject_limit' => 4,
            'source' => 'Fixture sintético de aceptación',
        ]);
        foreach ([[$this->reviewer, 'reviewer'], [$this->teacher, 'evaluator']] as [$u,$role]) {
            DB::table('memberships')->insert(['user_id' => $u->id, 'call_id' => $this->call, 'role' => $role]);
        }
        $category = DB::table('categories')->insertGetId(['name' => 'Sala DEMO', 'regime' => 'docencia', 'active' => true]);
        $this->offer = $this->s->saveOffer($this->authority, $this->call, [
            'category_id' => $category,
            'title' => 'Sala A',
            'seats' => 2,
            'is_subject' => false,
            'modality' => 'Presencial',
            'documents' => ['cpd' => 'Formulario CPD', 'ci' => 'Identidad sintética'],
            'requirements' => ['activo' => 'Estudiante activo'],
            'weights' => ['Soporte' => '50', 'Redes' => '25', 'Software' => '25'],
            'scheme' => 'DEMO_AREAS_100',
            'source' => 'Fixture DEMO',
        ]);
        $this->s->transition($this->authority, $this->call, 'published');
    }

    private function app(?User $u = null): int
    {
        return $this->s->apply($u ?? $this->student, $this->offer);
    }

    private function documents(int $id, ?User $u = null): void
    {
        foreach (['cpd', 'ci'] as $kind) {
            $this->s->upload($u ?? $this->student, $id, $kind, UploadedFile::fake()->image($kind.'.png'));
            $this->scanApplicantFixtures();
        }
    }

    private function ready(?User $u = null): int
    {
        $u ??= $this->student;
        $id = $this->app($u);
        $this->documents($id, $u);
        $this->s->submit($u, $id);
        $this->s->review($this->reviewer, $id, [
            'doc:cpd' => 'meets',
            'doc:ci' => 'meets',
            'activo' => 'meets',
        ], 'Evidencia sintética cotejada en DEMO.');
        $this->s->eligibility($this->reviewer, $id, true, 'Revisión completa documentada en DEMO.');
        $this->s->merit($this->reviewer, $id, '20.10', 'CPD-FICTICIO-01');

        return $id;
    }

    private function evaluation(): void
    {
        foreach (['Soporte', 'Redes', 'Software'] as $area) {
            $this->s->assign($this->authority, $this->offer, $this->teacher->id, $area);
        }
        $this->s->transition($this->authority, $this->call, 'closed');
        $this->s->transition($this->authority, $this->call, 'evaluation');
    }

    private function notes(int $id, array $scores = ['Soporte' => '80', 'Redes' => '60', 'Software' => '100']): void
    {
        foreach (DB::table('assignments')->where('offer_id', $this->offer)->get() as $as) {
            $this->s->grade($this->teacher, $id, $as->id, $scores[$as->area]);
        }
        $this->s->calculate($this->authority, $id);
    }

    private function rejects(callable $action): void
    {
        try {
            $action();
            $this->fail('Se esperaba rechazo de dominio.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    public function test_t01_register_login_and_reject_privilege_escalation(): void
    {
        $this->post('/registro', [
            'name' => 'Cuenta Ficticia',
            'email' => 'nueva@example.test',
            'password' => 'Clave-Larga-1234',
            'password_confirmation' => 'Clave-Larga-1234',
            'faculty' => 'FICCT',
            'career' => 'Sistemas',
            'role' => 'admin',
        ])->assertSessionHasErrors('role');
        $this->post('/registro', [
            'name' => 'Cuenta Ficticia',
            'email' => 'nueva@example.test',
            'password' => 'Clave-Larga-1234',
            'password_confirmation' => 'Clave-Larga-1234',
            'faculty' => 'FICCT',
            'career' => 'Sistemas',
        ])->assertRedirect('/');
        $this->assertDatabaseHas('users', ['email' => 'nueva@example.test', 'role' => 'student']);
        $this->assertTrue(Hash::check('Clave-Larga-1234', User::where('email', 'nueva@example.test')->first()->password));
        $this->post('/salir')->assertRedirect('/login');
        $this->post('/login', ['email' => 'nueva@example.test', 'password' => 'Clave-Larga-1234'])->assertRedirect('/');
    }

    public function test_t02_valid_transition_freezes_configuration(): void
    {
        $this->actingAs($this->authority)->get('/convocatorias/'.$this->call)->assertOk()->assertSee('Fixture DEMO');
        $this->rejects(fn () => $this->s->transition($this->authority, $this->call, 'draft'));
        $this->s->transition($this->authority, $this->call, 'closed');
        $this->assertDatabaseHas('calls', ['id' => $this->call, 'status' => 'closed']);
    }

    public function test_t03_category_registration_is_admin_only(): void
    {
        $this->actingAs($this->student)->post('/categorias', ['name' => 'Robótica', 'regime' => 'docencia'])->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/categorias', ['name' => 'Robótica', 'regime' => 'docencia', 'active' => '1'])->assertRedirect();
        $this->assertDatabaseHas('categories', ['name' => 'Robótica', 'active' => true]);
    }

    public function test_t04_seats_are_nonnegative_and_scoped_by_offer(): void
    {
        DB::table('calls')->where('id', $this->call)->update(['status' => 'draft']);
        $this->actingAs($this->authority)->post('/convocatorias/'.$this->call.'/ofertas', [
            'title' => 'Inválida',
            'category_id' => DB::table('categories')->value('id'),
            'seats' => -1,
            'modality' => 'Presencial',
            'documents' => 'cpd=CPD',
            'requirements' => 'activo=Activo',
            'weights' => 'Software=100',
            'scheme' => 'DEMO_AREAS_100',
            'source' => 'Fixture',
        ])->assertSessionHasErrors('seats');
        $this->assertDatabaseCount('offers', 1);
    }

    public function test_t05_scope_rejects_ineligible_faculty(): void
    {
        DB::table('calls')->where('id', $this->call)->update(['scope' => 'faculty', 'scope_value' => 'Otra facultad']);
        $this->rejects(fn () => $this->app());
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_t06_published_requirements_cannot_mutate(): void
    {
        $o = (array) Selection::row('offers', $this->offer);
        $o['weights'] = ['Software' => '100'];
        $o['documents'] = ['cpd' => 'CPD'];
        $o['requirements'] = ['nuevo' => 'Nuevo'];
        unset($o['id'],$o['call_id'],$o['created_at'],$o['updated_at']);
        $this->rejects(fn () => $this->s->saveOffer($this->authority, $this->call, $o));
        $this->assertStringContainsString('activo', Selection::row('offers', $this->offer)->requirements);
    }

    public function test_t07_application_duplicate_and_expired_deadline_rejected(): void
    {
        $id = $this->app();
        $this->assertGreaterThan(0, $id);
        $this->rejects(fn () => $this->app());
        DB::table('calls')->where('id', $this->call)->update(['closes_at' => now()->subMinute()]);
        $other = User::factory()->create();
        $this->rejects(fn () => $this->app($other));
    }

    public function test_t08_multiple_categories_and_subject_limit(): void
    {
        $id = $this->app();
        $o = (array) Selection::row('offers', $this->offer);
        unset($o['id']);
        $o['title'] = 'Otra categoría';
        $other = DB::table('offers')->insertGetId($o);
        $this->s->apply($this->student, $other);
        $this->assertDatabaseCount('applications', 2);
        DB::table('offers')->where('id', $this->offer)->update(['is_subject' => true]);
        DB::table('offers')->where('id', $other)->update(['is_subject' => true]);
        DB::table('calls')->where('id', $this->call)->update(['subject_limit' => 2]);
        $o['title'] = 'Asignatura adicional';
        $o['is_subject'] = true;
        $third = DB::table('offers')->insertGetId($o);
        $this->rejects(fn () => $this->s->apply($this->student, $third));
    }

    public function test_t09_file_versions_and_invalid_upload(): void
    {
        $id = $this->app();
        $this->documents($id);
        $this->s->upload($this->student, $id, 'cpd', UploadedFile::fake()->image('segunda.png'));
        $this->scanApplicantFixtures();
        $this->assertDatabaseHas('documents', ['application_id' => $id, 'kind' => 'cpd', 'version' => 2]);
        $this->rejects(fn () => $this->s->upload($this->student, $id, 'cpd', UploadedFile::fake()->createWithContent('malicioso.php', '<?php echo 1;')));
        $doc = DB::table('documents')->first();
        Storage::disk('local')->assertExists($doc->path);
    }

    public function test_t10_submit_requires_all_documents(): void
    {
        $id = $this->app();
        $this->rejects(fn () => $this->s->submit($this->student, $id));
        $this->documents($id);
        $this->s->submit($this->student, $id);
        $this->assertDatabaseHas('applications', ['id' => $id, 'status' => 'submitted']);
    }

    public function test_t11_private_download_requires_owner_or_scoped_reviewer(): void
    {
        $id = $this->app();
        $this->documents($id);
        $doc = DB::table('documents')->first();
        $outsider = User::factory()->create(['role' => 'staff']);
        $this->actingAs($outsider)->get('/documentos/'.$doc->id)->assertForbidden();
        $this->actingAs($this->reviewer)->get('/documentos/'.$doc->id)->assertForbidden(); // borrador: solo el postulante
        DB::table('applications')->where('id', $id)->update(['status' => 'submitted']);
        $this->actingAs($this->reviewer)->get('/documentos/'.$doc->id)->assertOk()->assertDownload();
        $this->actingAs($this->authority)->get('/documentos/'.$doc->id)->assertForbidden();
    }

    public function test_t12_requirement_decisions_preserve_evidence_and_actor(): void
    {
        $id = $this->app();
        $this->documents($id);
        $this->s->submit($this->student, $id);
        $this->s->review($this->reviewer, $id, ['activo' => 'observed'], 'Evidencia incompleta, revisar respaldo.');
        $this->assertDatabaseHas('reviews', [
            'application_id' => $id,
            'criterion' => 'activo',
            'decision' => 'observed',
            'user_id' => $this->reviewer->id,
        ]);
        $this->rejects(fn () => $this->s->review($this->reviewer, $id, ['activo' => 'not_applicable'], 'No aplica sin fundamento aprobado.'));
    }

    public function test_t13_eligibility_requires_complete_review(): void
    {
        $id = $this->app();
        $this->documents($id);
        $this->s->submit($this->student, $id);
        $this->rejects(fn () => $this->s->eligibility($this->reviewer, $id, true, 'Intento sin revisar todos los criterios.'));
        $this->s->eligibility($this->reviewer, $id, false, 'No cumple requisitos; evidencia documentada.');
        $this->assertDatabaseHas('applications', ['id' => $id, 'eligibility' => 'disabled']);
    }

    public function test_t14_non_enabled_cannot_take_exam_via_direct_request(): void
    {
        $id = $this->app();
        $this->evaluation();
        $as = DB::table('assignments')->first();
        $this->actingAs($this->teacher)->post('/postulaciones/'.$id.'/nota', ['assignment_id' => $as->id, 'score' => '50'])->assertSessionHasErrors('proceso');
        $this->assertDatabaseCount('grades', 0);
    }

    public function test_t15_cpd_requires_attached_evidence(): void
    {
        $id = $this->app();
        $this->rejects(fn () => $this->s->merit($this->reviewer, $id, '20.10', 'Sin respaldo'));
        $this->documents($id);
        $this->s->submit($this->student, $id);
        $this->s->merit($this->reviewer, $id, '20.10', 'CPD-FICTICIO/2026');
        $this->assertDatabaseHas('applications', ['id' => $id, 'cpd_reference' => 'CPD-FICTICIO/2026']);
    }

    public function test_t16_merit_limits_are_exact(): void
    {
        $this->assertSame('0.00', Scores::value('0', '40'));
        $this->assertSame('40.00', Scores::value('40', '40'));
        foreach (['-1', '40.01', 'NaN'] as $n) {
            $this->rejects(fn () => Scores::value($n, '40'));
        }
    }

    public function test_t17_areas_are_defined_per_offer(): void
    {
        $this->assertSame([
            'Soporte' => '50.00',
            'Redes' => '25.00',
            'Software' => '25.00',
        ], Selection::json(Selection::row('offers', $this->offer)->weights));
        $this->rejects(fn () => $this->s->assign($this->authority, $this->offer, $this->teacher->id, 'Electrónica'));
    }

    public function test_t18_weights_must_sum_exactly_100(): void
    {
        $this->assertSame(['Software' => '100.00'], Scores::weights(['Software' => '100']));
        foreach ([['A' => '99.99'], ['A' => '100', 'B' => '1'], ['A' => '0']] as $w) {
            $this->rejects(fn () => Scores::weights($w));
        }
    }

    public function test_t19_assignment_checks_teacher_scope_and_single_responsibility(): void
    {
        $this->s->assign($this->authority, $this->offer, $this->teacher->id, 'Soporte');
        $this->assertDatabaseHas('assignments', ['offer_id' => $this->offer, 'user_id' => $this->teacher->id, 'area' => 'Soporte']);
        $this->rejects(fn () => $this->s->assign($this->authority, $this->offer, $this->teacher->id, 'Soporte'));
        $outsider = User::factory()->create(['role' => 'staff']);
        $this->actingAs($this->authority)->post('/ofertas/'.$this->offer.'/asignar', ['user_id' => $outsider->id, 'area' => 'Redes'])->assertForbidden();
    }

    public function test_t20_only_assigned_teacher_can_confirm_grade_once(): void
    {
        $id = $this->ready();
        $this->evaluation();
        $as = DB::table('assignments')->first();
        $other = User::factory()->create(['role' => 'staff']);
        DB::table('memberships')->insert(['user_id' => $other->id, 'call_id' => $this->call, 'role' => 'evaluator']);
        $this->actingAs($other)->post('/postulaciones/'.$id.'/nota', ['assignment_id' => $as->id, 'score' => '80'])->assertForbidden();
        $this->s->grade($this->teacher, $id, $as->id, '80');
        $this->rejects(fn () => $this->s->grade($this->teacher, $id, $as->id, '81'));
        $this->assertDatabaseCount('grades', 1);
    }

    public function test_t21_weighted_exam_is_48_and_missing_notes_block(): void
    {
        $id = $this->ready();
        $this->evaluation();
        $this->rejects(fn () => $this->s->calculate($this->authority, $id));
        $this->notes($id);
        $this->assertSame('48.00', Selection::row('applications', $id)->exam);
    }

    public function test_t22_decimal_rounding_nan_and_precision(): void
    {
        $this->assertSame('1.01', Scores::round('1.005'));
        $this->assertSame('100.00', Scores::value('100'));
        foreach (['INF', 'NaN', '1.001', '100.01'] as $n) {
            $this->rejects(fn () => Scores::value($n));
        }$this->assertSame('0.00', Scores::exam(['A' => '100'], ['A' => '0']));
    }

    public function test_t23_final_sum_50_10_and_maximum_100(): void
    {
        $id = $this->ready();
        $this->evaluation();
        $this->notes($id, ['Soporte' => '50', 'Redes' => '50', 'Software' => '50']);
        $this->assertSame('50.10', Selection::row('applications', $id)->final);
        $this->assertSame('100.00', bcadd('40.00', Scores::exam(['A' => '100'], ['A' => '100']), 2));
    }

    public function test_t24_independent_results_by_application(): void
    {
        $id = $this->ready();
        $o = (array) Selection::row('offers', $this->offer);
        unset($o['id']);
        $o['title'] = 'Software separado';
        $second = DB::table('offers')->insertGetId($o);
        $other = $this->s->apply($this->student, $second);
        $this->evaluation();
        $this->notes($id);
        $this->assertSame('68.10', Selection::row('applications', $id)->final);
        $this->assertNull(Selection::row('applications', $other)->final);
    }

    public function test_t25_ranking_descending_and_offer_isolation(): void
    {
        $first = $this->ready();
        $second = $this->ready(User::factory()->create());
        $this->evaluation();
        $this->notes($first);
        $this->notes($second, ['Soporte' => '100', 'Redes' => '100', 'Software' => '100']);
        $r = $this->s->ranking($this->offer);
        $this->assertSame([$second, $first], array_map(fn ($a) => $a->id, $r['rank']));
        $this->assertSame([], $r['tied']);
    }

    public function test_t26_approval_cannot_exceed_seats_or_repeat(): void
    {
        $a = $this->ready();
        $b = $this->ready(User::factory()->create());
        DB::table('offers')->where('id', $this->offer)->update(['seats' => 1]);
        $this->evaluation();
        $this->notes($a, ['Soporte' => '100', 'Redes' => '100', 'Software' => '100']);
        $this->notes($b);
        $this->s->approve($this->authority, $this->offer, 'Acta sintética de aprobación de selección DEMO.');
        $this->assertDatabaseCount('publications', 1);
        $this->assertSame(1, DB::table('applications')->where('selection', 'selected')->count());
        $this->rejects(fn () => $this->s->approve($this->authority, $this->offer, 'Reintento de aprobación no debe duplicar plazas.'));
    }

    public function test_t27_tie_requires_new_exam_participants_results_and_commission(): void
    {
        $a = $this->ready();
        $b = $this->ready(User::factory()->create());
        DB::table('offers')->where('id', $this->offer)->update(['seats' => 1]);
        $this->evaluation();
        $this->notes($a);
        $this->notes($b);
        $this->rejects(fn () => $this->s->approve($this->authority, $this->offer, 'No se debe aprobar sin examen exclusivo de desempate.'));
        $id = $this->s->openTie($this->authority, $this->offer, [
            'commission' => ['Comisión DEMO 1', 'Comisión DEMO 2'],
            'conditions' => 'Nuevo examen DEMO exclusivo con decisión documentada.',
            'scheduled_at' => now()->subMinute(),
        ]);
        $this->assertEqualsCanonicalizing([$a, $b], Selection::json(Selection::row('tie_exams', $id)->participants));
        $this->rejects(fn () => $this->s->resolveTie($this->authority, $id, [$a => '80', $b => '70', 999 => '90'], [$a], 'Acta inválida con participante ajeno a los empatados.'));
        $this->s->resolveTie($this->authority, $id, [
            $a => '80, resultado DEMO',
            $b => '70, resultado DEMO',
        ], [$a], 'Acta sintética: comisión resuelve tras nuevo examen.');
        $this->s->approve($this->authority, $this->offer, 'Aprobación DEMO con examen exclusivo y acta de comisión.');
        $this->assertDatabaseHas('applications', ['id' => $a, 'selection' => 'selected']);
    }

    public function test_t28_consolidated_results_require_authority(): void
    {
        $id = $this->ready();
        $this->actingAs($this->student)->get('/ofertas/'.$this->offer.'/resultados')->assertForbidden();
        $this->actingAs($this->authority)->get('/ofertas/'.$this->offer.'/resultados')->assertOk();
        $other = User::factory()->create();
        $this->actingAs($other)->get('/postulaciones/'.$id)->assertForbidden();
    }

    public function test_t29_real_pdf_and_xlsx_export_with_permissions(): void
    {
        $this->ready();
        $this->actingAs($this->student)->get('/convocatorias/'.$this->call.'/reporte/pdf')->assertForbidden();
        $pdf = $this->actingAs($this->authority)->get('/convocatorias/'.$this->call.'/reporte/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $xlsx = $this->get('/convocatorias/'.$this->call.'/reporte/xlsx')->assertOk()->assertDownload('reporte-demo.xlsx');
        $this->assertStringStartsWith('PK', $xlsx->streamedContent());
    }

    public function test_t30_correction_keeps_original_actor_before_after_and_reason(): void
    {
        $id = $this->ready();
        $this->evaluation();
        $this->notes($id);
        $g = DB::table('grades')->join('assignments', 'assignments.id', '=', 'grades.assignment_id')->where('assignments.area', 'Soporte')->select('grades.*')->first();
        $this->rejects(fn () => $this->s->correct($this->authority, $g->id, '81', ''));
        $this->s->correct($this->authority, $g->id, '81', 'Autorización DEMO, transcripción corregida con respaldo.');
        $event = DB::table('audit_events')->where('action', 'grade.corrected')->first();
        $this->assertSame('80.00', Selection::json($event->before)['score']);
        $this->assertSame('81.00', Selection::json($event->after)['score']);
        $this->assertSame($this->authority->id, $event->user_id);
        $this->assertNull(Selection::row('applications', $id)->final);
        $this->assertSame($this->teacher->id, Selection::row('grades', $g->id)->user_id);
    }

    public function test_t_x01_end_to_end_http_views_and_publication(): void
    {
        $id = $this->ready();
        $this->actingAs($this->student)->get('/')->assertOk()->assertSee('Mis postulaciones');
        $this->get('/postulaciones/'.$id)->assertOk()->assertSee('Expediente digital');
        $this->evaluation();
        $this->notes($id);
        $this->actingAs($this->authority)->get('/ofertas/'.$this->offer.'/resultados')->assertOk()->assertSee('68.10');
        $this->s->approve($this->authority, $this->offer, 'Acta de aprobación del flujo integral de demostración.');
        $this->get('/convocatorias/'.$this->call.'/auditoria')->assertOk()->assertSee('result.approved');
    }

    public function test_t_x02_official_calendar_and_inactive_account_blocks(): void
    {
        DB::table('calls')->where('id', $this->call)->update(['mode' => 'OFICIAL']);
        $this->rejects(fn () => $this->app());
        DB::table('calls')->where('id', $this->call)->update(['mode' => 'DEMO', 'status' => 'draft', 'exam_at' => now()->subDays(2)]);
        $this->rejects(fn () => $this->s->transition($this->authority, $this->call, 'published'));
        $this->student->active = false;
        $this->student->save();
        $this->actingAs($this->student)->get('/')->assertForbidden();
    }

    public function test_t_x03_missing_grades_are_not_zero_and_snapshots_freeze(): void
    {
        $id = $this->ready();
        $this->evaluation();
        $this->rejects(fn () => $this->s->calculate($this->authority, $id));
        $this->notes($id);
        $this->s->approve($this->authority, $this->offer, 'Publicación DEMO de referencia inalterada por cambios.');
        $before = DB::table('publications')->value('snapshot');
        $grade = DB::table('grades')->first();
        $this->rejects(fn () => $this->s->correct($this->authority, $grade->id, '99', 'Intento de alterar resultado ya publicado.'));
        $this->assertSame($before, DB::table('publications')->value('snapshot'));
    }

    public function test_t_x04_technical_admin_cannot_access_sensitive_records(): void
    {
        $id = $this->ready();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/postulaciones/'.$id)->assertForbidden();
        $this->get('/ofertas/'.$this->offer.'/resultados')->assertForbidden();
        $this->get('/usuarios')->assertOk();
    }

    public function test_t_x05_draft_grade_must_be_confirmed_before_calculation(): void
    {
        $id = $this->ready();
        $this->evaluation();
        $assignments = DB::table('assignments')->where('offer_id', $this->offer)->get();
        foreach ($assignments as $as) {
            $this->s->grade($this->teacher, $id, $as->id, '50', false);
        }
        $this->rejects(fn () => $this->s->calculate($this->authority, $id));
        foreach ($assignments as $as) {
            $this->s->grade($this->teacher, $id, $as->id, '50', true);
        }
        $this->s->calculate($this->authority, $id);
        $this->assertSame('50.10', Selection::row('applications', $id)->final);
    }

    public function test_t_x06_draft_call_and_offer_can_be_edited_with_audit(): void
    {
        $this->rejects(fn () => $this->s->editCall($this->authority, $this->call, ['title' => 'No puede cambiar publicación']));
        $call = $this->s->createCall($this->authority, [
            'title' => 'Borrador editable',
            'opens_at' => now(),
            'closes_at' => now()->addDay(),
            'exam_at' => now()->addDays(2),
            'scope' => 'UAGRM',
            'source' => 'Fixture',
        ]);
        $this->s->editCall($this->authority, $call, ['title' => 'Borrador corregido']);
        $this->assertDatabaseHas('calls', ['id' => $call, 'title' => 'Borrador corregido', 'version' => 2]);
        $data = [
            'category_id' => DB::table('categories')->value('id'),
            'title' => 'Editable',
            'seats' => 1,
            'is_subject' => false,
            'modality' => 'DEMO',
            'documents' => ['cpd' => 'CPD'],
            'requirements' => ['activo' => 'Activo'],
            'weights' => ['Software' => '100'],
            'scheme' => 'DEMO_AREAS_100',
            'source' => 'Fixture',
        ];
        $offer = $this->s->saveOffer($this->authority, $call, $data);
        $data['seats'] = 3;
        $this->s->saveOffer($this->authority, $call, $data, $offer);
        $this->assertDatabaseHas('offers', ['id' => $offer, 'seats' => 3, 'version' => 2]);
        $this->actingAs($this->authority)->get('/convocatorias/'.$call.'?editar_oferta='.$offer)->assertOk()->assertSee('Guardar cambios de oferta');
        $this->assertDatabaseHas('audit_events', ['action' => 'offer.edited', 'entity' => 'offer:'.$offer]);
    }

    public function test_t_x07_admin_cannot_grant_self_authority(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/permisos', [
            'call_id' => $this->call,
            'user_id' => $admin->id,
            'role' => 'authority',
        ])->assertSessionHasErrors('proceso');
        $this->assertDatabaseMissing('memberships', ['user_id' => $admin->id, 'role' => 'authority']);
    }

    public function test_t_x08_html_is_escaped_and_spreadsheet_formulas_are_text(): void
    {
        $this->student->name = '<script>alert(1)</script>';
        $this->student->save();
        $this->actingAs($this->student)->get('/')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->student->name = '=HYPERLINK("https://example.test","demo")';
        $this->student->save();
        $this->ready();
        $response = $this->actingAs($this->authority)->get('/convocatorias/'.$this->call.'/reporte/xlsx')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'ficct-xlsx');
        file_put_contents($path, $response->streamedContent());
        try {
            $book = IOFactory::load($path);
            $cell = $book->getActiveSheet()->getCell('B4');
            $this->assertSame('s', $cell->getDataType());
            $this->assertSame($this->student->name, $cell->getValue());
        } finally {
            unlink($path);
        }
    }
}
