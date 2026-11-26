<?php

namespace Tests\Feature;

use App\Domain\Access;
use App\Domain\Administration;
use App\Domain\Amendments;
use App\Domain\Selection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Roles y permisos (auditoría del 26/11/2026 contra la Resolución 021/2023): separación de funciones,
 * permisos que no sobreviven a un cambio de perfil y datos que cada rol no debe ver todavía.
 */
final class AccessHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Selection $s;

    private User $admin;

    private User $authority;

    private User $reviewer;

    private User $teacher;

    private User $student;

    private int $call;

    private int $offer;

    private int $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->s = app(Selection::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->authority = User::factory()->create(['role' => 'authority']);
        $this->reviewer = User::factory()->create(['role' => 'staff']);
        $this->teacher = User::factory()->create(['role' => 'staff']);
        $this->student = User::factory()->create();
        $this->call = $this->s->createCall($this->authority, [
            'title' => 'Roles',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'exam_at' => now()->addDays(2),
            'scope' => 'UAGRM',
        ]);
        foreach ([[$this->reviewer, 'reviewer'], [$this->teacher, 'evaluator']] as [$u, $role]) {
            DB::table('memberships')->insert(['call_id' => $this->call, 'user_id' => $u->id, 'role' => $role]);
        }
        $category = DB::table('categories')->insertGetId(['name' => 'Tipo roles', 'regime' => 'docencia', 'active' => true]);
        $this->offer = $this->s->saveOffer($this->authority, $this->call, [
            'category_id' => $category, 'title' => 'Oferta roles', 'seats' => 1, 'is_subject' => false, 'modality' => 'Presencial',
            'documents' => ['cpd' => 'CPD'], 'requirements' => ['activo' => 'Activo'], 'weights' => ['Soporte' => '100'],
            'scheme' => 'AREAS_100', 'source' => 'Fixture',
        ]);
        $this->s->transition($this->authority, $this->call, 'published');
        $this->s->assign($this->authority, $this->offer, $this->teacher->id, 'Soporte');
        $this->assignment = DB::table('assignments')->where('offer_id', $this->offer)->value('id');
    }

    private function submitted(User $student): int
    {
        $id = $this->s->apply($student, $this->offer);
        $this->s->upload($student, $id, 'cpd', UploadedFile::fake()->image('cpd.png'));
        $this->scanApplicantFixtures();
        $this->s->submit($student, $id);

        return $id;
    }

    private function rejects(callable $action): void
    {
        try {
            $action();
            $this->fail('Se esperaba un rechazo.');
        } catch (ValidationException|HttpException) {
            $this->assertTrue(true);
        }
    }

    public function test_the_applicant_does_not_see_grades_or_final_score_before_the_publication(): void
    {
        $id = $this->submitted($this->student);
        $this->s->review($this->reviewer, $id, ['doc:cpd' => 'meets', 'activo' => 'meets'], 'Revisión completa del expediente.');
        $this->s->eligibility($this->reviewer, $id, true, 'Cumple todos los requisitos.');
        $this->s->merit($this->reviewer, $id, '20.00', 'CPD-1');
        $this->s->transition($this->authority, $this->call, 'closed');
        $this->s->transition($this->authority, $this->call, 'evaluation');

        $this->s->grade($this->teacher, $id, $this->assignment, '45', false);
        $draft = $this->actingAs($this->student)->getJson('/api/v1/applications/'.$id)->assertOk();
        $this->assertSame([], $draft->json('grades'));

        $this->s->grade($this->teacher, $id, $this->assignment, '80');
        $this->s->calculate($this->authority, $id);
        $calculated = $this->actingAs($this->student)->getJson('/api/v1/applications/'.$id)->assertOk();
        $this->assertSame([], $calculated->json('grades'));
        $this->assertArrayNotHasKey('final', $calculated->json('application'));
        $this->assertArrayNotHasKey('exam', $calculated->json('application'));
        $this->assertSame('20.00', $calculated->json('application.merit'));

        $this->s->approve($this->authority, $this->offer, 'Acta de aprobación de la comisión.');
        $published = $this->actingAs($this->student)->getJson('/api/v1/applications/'.$id)->assertOk();
        $this->assertSame('68.00', $published->json('application.final'));
        $this->assertCount(1, $published->json('grades'));
        $this->assertArrayNotHasKey('user_id', $published->json('grades.0'));
    }

    public function test_the_authority_cannot_also_review_or_evaluate_in_the_same_call(): void
    {
        $admin = app(Administration::class);
        $other = User::factory()->create(['role' => 'authority']);
        $admin->grant($this->admin, $this->call, $other->id, 'authority', null, null, 'Autoridad adjunta de la convocatoria.');
        foreach (['reviewer', 'evaluator'] as $role) {
            $this->rejects(fn () => $admin->grant($this->admin, $this->call, $other->id, $role, null, null, 'Funciones incompatibles.'));
        }
        // Y al revés: quien ya revisa o evalúa no recibe la autoridad.
        $this->rejects(fn () => $admin->grant($this->admin, $this->call, $this->reviewer->id, 'authority', null, null, 'Funciones incompatibles.'));
        $this->rejects(fn () => $admin->grant($this->admin, $this->call, $this->teacher->id, 'authority', null, null, 'Funciones incompatibles.'));
        // Revisión y evaluación sí pueden recaer en una persona: la comisión evalúa méritos y competencia.
        $admin->grant($this->admin, $this->call, $this->reviewer->id, 'evaluator', null, null, 'La comisión evalúa méritos y examen.');
        $this->assertTrue(DB::table('memberships')->where(['call_id' => $this->call, 'user_id' => $this->reviewer->id, 'role' => 'evaluator'])->exists());
    }

    public function test_permissions_are_not_granted_to_inactive_or_technical_administration_accounts(): void
    {
        $admin = app(Administration::class);
        $inactive = User::factory()->create(['role' => 'staff', 'active' => false]);
        $this->rejects(fn () => $admin->grant($this->admin, $this->call, $inactive->id, 'reviewer', null, null, 'Cuenta desactivada.'));
        $technical = User::factory()->create(['role' => 'admin']);
        $this->rejects(fn () => $admin->grant($this->admin, $this->call, $technical->id, 'reviewer', null, null, 'Cuenta técnica.'));
    }

    public function test_an_assigner_cannot_name_themselves_as_evaluator(): void
    {
        $call = $this->s->createCall($this->authority, ['title' => 'Otra', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'exam_at' => now()->addDays(2), 'scope' => 'UAGRM']);
        $category = DB::table('categories')->first()->id;
        $offer = $this->s->saveOffer($this->authority, $call, [
            'category_id' => $category, 'title' => 'Oferta 2', 'seats' => 1, 'is_subject' => false, 'modality' => 'Presencial',
            'documents' => ['cpd' => 'CPD'], 'requirements' => ['activo' => 'Activo'], 'weights' => ['Soporte' => '100'], 'scheme' => 'AREAS_100', 'source' => 'Fixture',
        ]);
        // Aunque la membresía exista (por ejemplo, concedida antes de la regla), no puede asignarse a sí mismo.
        DB::table('memberships')->insert(['call_id' => $call, 'user_id' => $this->authority->id, 'role' => 'evaluator']);
        $this->expectException(HttpException::class);
        $this->s->assign($this->authority, $offer, $this->authority->id, 'Soporte');
    }

    public function test_any_unrevoked_permission_blocks_applying_even_if_not_yet_in_force(): void
    {
        $person = User::factory()->create();
        DB::table('memberships')->insert(['call_id' => $this->call, 'user_id' => $person->id, 'role' => 'reviewer', 'starts_at' => now()->addDays(5)]);
        $this->rejects(fn () => $this->s->apply($person, $this->offer));
        DB::table('memberships')->where('user_id', $person->id)->update(['revoked_at' => now()]);
        $this->assertIsInt($this->s->apply($person, $this->offer));
    }

    public function test_changing_a_profile_revokes_the_permissions_by_call(): void
    {
        $admin = app(Administration::class);
        $this->assertTrue(Access::has($this->reviewer, $this->call, ['reviewer']));
        $admin->profile($this->admin, $this->reviewer->id, ['role' => 'student'], 1, 'La persona deja la función de revisión.');
        $this->assertFalse(Access::has($this->reviewer->fresh(), $this->call, ['reviewer']));
        $this->assertTrue(DB::table('audit_events')->where('action', 'permission.revoked')->where('reason', 'La persona deja la función de revisión.')->exists());
    }

    public function test_only_reviewers_open_or_expire_amendments_not_the_authority(): void
    {
        $id = $this->submitted($this->student);
        $this->s->review($this->reviewer, $id, ['doc:cpd' => 'observed', 'activo' => 'meets'], 'Observación del documento presentado.');
        $this->expectException(HttpException::class);
        app(Amendments::class)->open($this->authority, $id, ['cpd'], now()->addDays(2)->toDateTimeString(), 'Plazo para corregir el CPD.', 'Referencia de evidencia verificable.');
    }

    public function test_a_call_creator_permission_is_audited_and_the_parent_call_must_be_theirs(): void
    {
        $this->assertTrue(DB::table('audit_events')->where('action', 'permission.granted')->where('call_id', $this->call)->exists());
        $stranger = User::factory()->create(['role' => 'authority']);
        $this->expectException(HttpException::class);
        $this->s->createCall($stranger, ['title' => 'Hija', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'exam_at' => now()->addDays(2), 'scope' => 'UAGRM', 'parent_id' => $this->call]);
    }

    public function test_the_exam_split_is_fixed_for_subject_and_laboratory_profiles(): void
    {
        $category = DB::table('categories')->first()->id;
        $base = ['category_id' => $category, 'seats' => 1, 'is_subject' => true, 'modality' => 'Presencial', 'documents' => ['cpd' => 'CPD'], 'requirements' => ['activo' => 'Activo'],
            'scheme' => 'COMPONENTS_100', 'source' => 'Fixture', 'requirement_profile' => 'asignatura'];
        $call = $this->s->createCall($this->authority, ['title' => 'Examen', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'exam_at' => now()->addDays(2), 'scope' => 'UAGRM']);
        $this->rejects(fn () => $this->s->saveOffer($this->authority, $call, $base + ['title' => 'X', 'weights' => ['Escrito' => '50', 'Práctico' => '50']]));
        $id = $this->s->saveOffer($this->authority, $call, $base + ['title' => 'Correcto', 'weights' => ['Escrito' => '60', 'Práctico' => '40']]);
        $this->assertIsInt($id);
    }

    public function test_graduation_arrears_is_conditional_in_every_profile_that_has_it(): void
    {
        foreach (['asignatura', 'sala', 'laboratorio'] as $profile) {
            $this->assertTrue(Selection::conditionalCriterion((object) ['requirement_profile' => $profile], 'graduacion_mora'));
        }
        $this->assertFalse(Selection::conditionalCriterion((object) ['requirement_profile' => null], 'graduacion_mora'));
        $this->assertFalse(Selection::conditionalCriterion((object) ['requirement_profile' => 'laboratorio'], 'activo'));
    }

    public function test_the_administration_history_lists_account_and_permission_acts(): void
    {
        app(Administration::class)->grant($this->admin, $this->call, User::factory()->create(['role' => 'staff'])->id, 'reviewer', null, null, 'Segundo revisor de la convocatoria.');
        $history = $this->actingAs($this->admin)->getJson('/api/v1/management')->assertOk()->json('history');
        $this->assertContains('permission.granted', array_column($history, 'action'));
        $this->actingAs($this->authority)->getJson('/api/v1/management')->assertForbidden();
    }
}
