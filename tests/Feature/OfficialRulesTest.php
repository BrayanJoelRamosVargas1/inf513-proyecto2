<?php

namespace Tests\Feature;

use App\Domain\Commission;
use App\Domain\Selection;
use App\Domain\Vacancies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Reglas de la Resolución 021/2023 en la instalación real: comisión y veedores, aprobación del HCF, fecha del examen,
 * identidad única, mérito frente al CPD y plazas sin cubrir.
 */
final class OfficialRulesTest extends TestCase
{
    use RefreshDatabase;

    private Selection $s;

    private User $authority;

    private User $reviewer;

    private User $teacher;

    private int $call;

    private int $offer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['portal.experience' => 'institutional', 'portal.approval_threshold_default' => null]);
        $this->s = app(Selection::class);
        $this->authority = User::factory()->create(['role' => 'authority']);
        $this->reviewer = User::factory()->create(['role' => 'staff']);
        $this->teacher = User::factory()->create(['role' => 'staff']);
        $this->call = $this->newCall('Convocatoria 01/2026');
        foreach ([[$this->reviewer, 'reviewer'], [$this->teacher, 'evaluator']] as [$u, $role]) {
            DB::table('memberships')->insert(['call_id' => $this->call, 'user_id' => $u->id, 'role' => $role]);
        }
        $this->offer = $this->newOffer($this->call, 'Software');
    }

    private function newCall(string $title, ?int $parent = null): int
    {
        return $this->s->createCall($this->authority, [
            'title' => $title, 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'exam_at' => now()->addDays(5),
            'scope' => 'UAGRM', 'source' => 'Resolución del Decanato', 'parent_id' => $parent,
        ]);
    }

    private function newOffer(int $call, string $title, int $seats = 1): int
    {
        $category = DB::table('categories')->first()?->id ?? DB::table('categories')->insertGetId(['name' => 'Sala', 'regime' => 'docencia', 'active' => true]);
        $id = $this->s->saveOffer($this->authority, $call, [
            'category_id' => $category, 'title' => $title, 'seats' => $seats, 'is_subject' => false, 'modality' => 'Presencial',
            'documents' => ['cpd' => 'Formulario CPD'], 'requirements' => ['activo' => 'Activo'], 'weights' => ['Software' => '100'],
            'scheme' => 'AREAS_100', 'source' => 'Convocatoria',
        ]);
        DB::table('offers')->where('id', $id)->update(['requirement_profile' => 'sala']);

        return $id;
    }

    private function act(int $call, string $type): void
    {
        DB::table('institutional_register')->insert([
            'call_id' => $call, 'kind' => 'act', 'reference' => $type.random_int(100, 999), 'record_scope' => 'call', 'version' => 1,
            'payload' => json_encode(['act_type' => $type, 'modality' => 'sala', 'notes' => 'Acto de prueba.']),
            'recorded_by' => $this->authority->id, 'created_at' => now(),
        ]);
    }

    private function member(string $cargo, ?User $user = null, ?int $offer = null): int
    {
        return app(Commission::class)->add($this->authority, $this->call, [
            'cargo' => $cargo, 'user_id' => ($user ?? User::factory()->create(['role' => 'staff']))->id, 'offer_id' => $offer, 'designation_reference' => 'RES-DEC-001',
        ]);
    }

    private function rejects(callable $action, string $contains): void
    {
        try {
            $action();
            $this->fail('Se esperaba un rechazo.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($contains, implode(' ', array_merge(...array_values($e->errors()))));
        }
    }

    public function test_the_call_needs_the_council_approval_before_publication(): void
    {
        $this->rejects(fn () => $this->s->transition($this->authority, $this->call, 'published'), 'Honorable Consejo Facultativo');
        $this->act($this->call, 'aprobacion');
        $this->s->transition($this->authority, $this->call, 'published');
        $this->assertSame('published', DB::table('calls')->where('id', $this->call)->value('status'));
    }

    public function test_evaluation_cannot_open_without_the_minimum_commission(): void
    {
        $this->act($this->call, 'aprobacion');
        $this->s->transition($this->authority, $this->call, 'published');
        $this->s->transition($this->authority, $this->call, 'closed');
        $this->rejects(fn () => $this->s->transition($this->authority, $this->call, 'evaluation'), 'comisión evaluadora está incompleta');

        $this->member('encargado_laboratorio', $this->teacher);
        $this->member('vicedecano');
        $this->member('docente_invitado');
        $this->rejects(fn () => $this->s->transition($this->authority, $this->call, 'evaluation'), 'Docentes invitados (mínimo dos)');
        $this->member('docente_invitado');
        $this->s->transition($this->authority, $this->call, 'evaluation');
        $this->assertSame('evaluation', DB::table('calls')->where('id', $this->call)->value('status'));
    }

    public function test_only_commission_members_can_be_assigned_as_evaluators(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('integra la comisión');
        $this->s->assign($this->authority, $this->offer, $this->teacher->id, 'Software');
    }

    public function test_a_member_of_the_commission_can_be_assigned_and_applicants_cannot_join(): void
    {
        $this->member('encargado_laboratorio', $this->teacher);
        $this->s->assign($this->authority, $this->offer, $this->teacher->id, 'Software');
        $this->assertDatabaseHas('assignments', ['offer_id' => $this->offer, 'user_id' => $this->teacher->id]);

        $this->act($this->call, 'aprobacion');
        $this->s->transition($this->authority, $this->call, 'published');
        $student = User::factory()->create();
        $this->s->apply($student, $this->offer);
        $this->rejects(fn () => app(Commission::class)->add($this->authority, $this->call, ['cargo' => 'docente_invitado', 'user_id' => $student->id, 'designation_reference' => 'RES-1']), 'conflicto de interés');
    }

    public function test_observers_are_accredited_at_least_48_hours_before_the_exam(): void
    {
        $commission = app(Commission::class);
        $late = now()->addDays(4)->toDateString();
        $this->rejects(fn () => $commission->add($this->authority, $this->call, ['cargo' => 'veedor_federacion', 'name' => 'Delegado FUAD', 'accredited_on' => $late, 'designation_reference' => 'CARTA-1']), '48 horas');
        $id = $commission->add($this->authority, $this->call, ['cargo' => 'veedor_vicerrectorado', 'name' => 'Delegado del Vicerrectorado', 'accredited_on' => now()->addDays(2)->toDateString(), 'designation_reference' => 'CARTA-2']);
        $this->assertNotNull(DB::table('commission_members')->where('id', $id)->value('accredited_on'));
        $this->assertNull(DB::table('commission_members')->where('id', $id)->value('user_id'));
        // Un veedor no integra la comisión: no cuenta para la composición ni puede ser evaluador.
        $this->assertFalse(Commission::isMember($this->call, $this->offer, $this->teacher->id));
    }

    public function test_the_commission_api_is_for_the_call_authority_only(): void
    {
        $this->actingAs($this->authority)->getJson('/api/v1/calls/'.$this->call.'/commission')->assertOk()->assertJsonStructure(['cargos', 'members', 'missing', 'candidates']);
        $this->actingAs($this->reviewer)->getJson('/api/v1/calls/'.$this->call.'/commission')->assertForbidden();
        $this->actingAs($this->reviewer)->postJson('/api/v1/calls/'.$this->call.'/commission', ['cargo' => 'decano', 'user_id' => $this->teacher->id, 'designation_reference' => 'X-1'])->assertForbidden();
        $this->actingAs($this->authority)->postJson('/api/v1/calls/'.$this->call.'/commission', ['cargo' => 'decano', 'user_id' => $this->teacher->id, 'designation_reference' => 'RES-1'])->assertCreated();
    }

    public function test_grades_cannot_be_registered_before_the_exam_date(): void
    {
        $student = User::factory()->create();
        $this->member('encargado_laboratorio', $this->teacher);
        $this->member('vicedecano');
        $this->member('docente_invitado');
        $this->member('docente_invitado');
        $this->s->assign($this->authority, $this->offer, $this->teacher->id, 'Software');
        $assignment = DB::table('assignments')->where('offer_id', $this->offer)->value('id');
        $this->act($this->call, 'aprobacion');
        $this->s->transition($this->authority, $this->call, 'published');
        $id = $this->s->apply($student, $this->offer);
        $this->s->upload($student, $id, 'cpd', UploadedFile::fake()->image('cpd.png'));
        $this->scanApplicantFixtures();
        $this->s->submit($student, $id);
        $this->s->review($this->reviewer, $id, ['doc:cpd' => 'meets', 'activo' => 'meets'], 'Revisión completa del expediente.');
        $this->s->eligibility($this->reviewer, $id, true, 'Cumple todos los requisitos.');
        $this->s->merit($this->reviewer, $id, '20.00', 'CPD-1');
        $this->s->transition($this->authority, $this->call, 'closed');
        $this->s->transition($this->authority, $this->call, 'evaluation');

        $this->rejects(fn () => $this->s->grade($this->teacher, $id, $assignment, '70'), 'desde la toma del examen');
        DB::table('calls')->where('id', $this->call)->update(['exam_at' => now()->subHour()]); // el examen ya se tomó
        $this->s->grade($this->teacher, $id, $assignment, '70');
        $this->assertDatabaseHas('grades', ['application_id' => $id, 'status' => 'confirmed']);
    }

    public function test_the_merit_must_match_the_recorded_cpd_score(): void
    {
        $student = User::factory()->create();
        $this->act($this->call, 'aprobacion');
        $this->s->transition($this->authority, $this->call, 'published');
        $id = $this->s->apply($student, $this->offer);
        $this->s->upload($student, $id, 'cpd', UploadedFile::fake()->image('cpd.png'));
        $this->scanApplicantFixtures();
        $this->s->submit($student, $id);
        $this->s->review($this->reviewer, $id, ['doc:cpd' => 'meets', 'activo' => 'meets'], 'Revisión completa del expediente.');
        $this->s->eligibility($this->reviewer, $id, true, 'Cumple todos los requisitos.');
        DB::table('institutional_register')->insert([
            'call_id' => $this->call, 'kind' => 'cpd', 'reference' => 'CPD-1', 'record_scope' => 'application:'.$id, 'version' => 1,
            'payload' => json_encode(['score' => '31.50', 'application_id' => $id, 'modality' => 'sala']), 'recorded_by' => $this->reviewer->id, 'created_at' => now(),
        ]);
        $this->rejects(fn () => $this->s->merit($this->reviewer, $id, '20.00', 'CPD-1'), 'coincidir con el puntaje del formulario CPD');
        $this->s->merit($this->reviewer, $id, '31.50', 'CPD-1');
        $this->assertSame('31.50', DB::table('applications')->where('id', $id)->value('merit'));
    }

    public function test_registration_requires_a_unique_identity_in_the_real_installation(): void
    {
        $base = ['name' => 'Persona Uno', 'email' => 'uno@ficct.example', 'password' => 'clave-muy-larga-123', 'password_confirmation' => 'clave-muy-larga-123', 'faculty' => 'FICCT', 'career' => 'Sistemas'];
        $this->postJson('/api/v1/register', $base)->assertStatus(422)->assertJsonValidationErrors(['document_number', 'student_code']);
        $this->postJson('/api/v1/register', $base + ['document_number' => '1234567 sc', 'student_code' => '219000111'])->assertCreated();
        $this->assertSame('1234567SC', User::where('email', 'uno@ficct.example')->value('document_number'));
        // La misma persona con otro correo y con formato distinto de la cédula no puede abrir otra cuenta.
        $this->postJson('/api/v1/register', ['email' => 'dos@ficct.example', 'document_number' => '1.234.567-sc', 'student_code' => '219000222'] + $base)
            ->assertStatus(422)->assertJsonValidationErrors(['document_number']);
        $this->postJson('/api/v1/register', ['email' => 'dos@ficct.example', 'document_number' => '7654321', 'student_code' => '219000111'] + $base)
            ->assertStatus(422)->assertJsonValidationErrors(['student_code']);
    }

    public function test_unfilled_seats_are_counted_and_carried_to_the_next_call_until_the_limit(): void
    {
        $this->act($this->call, 'aprobacion');
        $this->s->transition($this->authority, $this->call, 'published');
        $this->s->transition($this->authority, $this->call, 'closed');
        // Resultados aprobados sin seleccionados: nadie se presentó.
        DB::table('publications')->insert(['offer_id' => $this->offer, 'version' => 1, 'result_revision' => 0, 'snapshot' => '{}', 'minutes' => 'Acta sin postulantes.', 'user_id' => $this->authority->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('calls')->where('id', $this->call)->update(['status' => 'results']);
        $report = app(Vacancies::class)->report($this->authority, $this->call);
        $this->assertSame('deserted', $report['offers'][0]['outcome']);
        $this->assertSame(1, $report['offers'][0]['vacancies']);
        $this->assertSame(1, $report['offers'][0]['consecutive_calls_unfilled']);
        $this->assertFalse($report['offers'][0]['may_declare']);

        $second = app(Vacancies::class)->carryOver($this->authority, $this->call, ['title' => 'Convocatoria 02/2026', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'exam_at' => now()->addDays(5), 'source' => 'Nueva convocatoria de plazas vacantes']);
        $this->assertSame($this->call, (int) DB::table('calls')->where('id', $second)->value('parent_id'));
        $this->assertSame('draft', DB::table('calls')->where('id', $second)->value('status'));
        $carried = DB::table('offers')->where('call_id', $second)->first();
        $this->assertSame('Software', $carried->title);
        $this->assertSame(1, (int) $carried->seats);

        // La segunda convocatoria tampoco cubre la plaza: ya no se admite una tercera.
        DB::table('publications')->insert(['offer_id' => $carried->id, 'version' => 1, 'result_revision' => 0, 'snapshot' => '{}', 'minutes' => 'Acta sin postulantes.', 'user_id' => $this->authority->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('calls')->where('id', $second)->update(['status' => 'results']);
        $again = app(Vacancies::class)->report($this->authority, $second);
        $this->assertSame(2, $again['offers'][0]['consecutive_calls_unfilled']);
        $this->assertTrue($again['offers'][0]['may_declare']);
        $this->rejects(fn () => app(Vacancies::class)->carryOver($this->authority, $second, ['title' => 'Tercera', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'exam_at' => now()->addDays(5), 'source' => 'Tercera convocatoria']), 'acta de declaración desierta');
    }

    public function test_vacancies_are_only_visible_to_the_authority(): void
    {
        $this->actingAs($this->authority)->getJson('/api/v1/calls/'.$this->call.'/vacancies')->assertOk()->assertJsonPath('max_consecutive', 2);
        $this->actingAs($this->reviewer)->getJson('/api/v1/calls/'.$this->call.'/vacancies')->assertForbidden();
        $this->expectException(HttpException::class);
        app(Vacancies::class)->carryOver($this->reviewer, $this->call, []);
    }
}
