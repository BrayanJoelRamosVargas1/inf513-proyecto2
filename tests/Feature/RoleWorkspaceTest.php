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

final class RoleWorkspaceTest extends TestCase
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

    private function readyApplication(): int
    {
        $s = app(Selection::class);
        $id = $s->apply($this->student, $this->offer);
        $s->upload($this->student, $id, 'cpd', UploadedFile::fake()->image('cpd.png'));
        $this->scanApplicantFixtures();
        $s->submit($this->student, $id);
        return $id;
    }

    public function test_w01_each_responsibility_has_its_database_menu_and_revocation_removes_it(): void
    {
        foreach ([[$this->student, '/postulaciones', '/revision'], [$this->reviewer, '/revision', '/evaluaciones'], [$this->evaluator, '/evaluaciones', '/revision'], [$this->authority, '/expedientes', '/administracion']] as [$user, $own, $other]) {
            $this->actingAs($user)->getJson('/api/v1/me/navigation')->assertOk()->assertJsonFragment(['path' => $own])->assertJsonMissing(['path' => $other]);
        }
        $this->actingAs($this->reviewer)->getJson('/api/v1/me/navigation')->assertJsonMissing(['path' => '/simulacion']);
        DB::table('memberships')->where('user_id', $this->reviewer->id)->update(['revoked_at' => now()]);
        $this->getJson('/api/v1/me/navigation')->assertJsonMissing(['path' => '/revision']);
        $this->getJson('/api/v1/applications?workspace=reviewer')->assertForbidden();
    }

    public function test_w02_combined_responsibilities_do_not_mix_own_and_review_queues(): void
    {
        $id = $this->readyApplication();
        DB::table('memberships')->insert(['user_id' => $this->student->id, 'call_id' => $this->call, 'role' => 'reviewer']);
        $this->actingAs($this->student)->getJson('/api/v1/applications?workspace=own')->assertJsonCount(1, 'applications')->assertJsonPath('applications.0.id', $id);
        $this->getJson('/api/v1/applications?workspace=reviewer')->assertJsonCount(0, 'applications');
        $this->getJson('/api/v1/applications?workspace=evaluator')->assertForbidden();
        $this->getJson('/api/v1/applications?workspace=invalid')->assertUnprocessable();
    }

    public function test_w03_evaluation_queue_contains_only_assigned_offers_and_reports_pending_areas(): void
    {
        $id = $this->readyApplication();
        $other = (array) DB::table('offers')->find($this->offer);
        unset($other['id']);
        $other['title'] = 'Oferta no asignada';
        $otherOffer = DB::table('offers')->insertGetId($other);
        app(Selection::class)->apply($this->student, $otherOffer);
        $this->actingAs($this->evaluator)->getJson('/api/v1/applications?workspace=evaluator')->assertOk()->assertJsonCount(1, 'applications')->assertJsonPath('applications.0.id', $id)->assertJsonPath('applications.0.assigned_areas.0', 'Software')->assertJsonPath('applications.0.pending_areas.0', 'Software');
        $this->getJson('/api/v1/applications?workspace=authority')->assertForbidden();
        DB::table('memberships')->where('user_id', $this->evaluator->id)->update(['expires_at' => now()->subSecond()]);
        $this->getJson('/api/v1/applications?workspace=evaluator')->assertForbidden();
    }

    public function test_w04_owner_has_no_link_capability_to_staff_rectifications(): void
    {
        $id = $this->readyApplication();
        $this->actingAs($this->student)->getJson('/api/v1/applications/'.$id)->assertJsonPath('can_view_corrections', false);
        $this->getJson('/api/v1/applications/'.$id.'/corrections')->assertForbidden();
        $this->actingAs($this->reviewer)->getJson('/api/v1/applications/'.$id)->assertJsonPath('can_view_corrections', true);
        $this->actingAs($this->evaluator)->getJson('/api/v1/applications/'.$id)->assertJsonPath('can_view_corrections', false);
    }

    public function test_w05_calculation_waits_for_confirmed_notes_and_merits_remain_visible_to_reviewer(): void
    {
        $id = $this->readyApplication();
        $s = app(Selection::class);
        $s->review($this->reviewer, $id, ['doc:cpd' => 'meets', 'activo' => 'meets'], 'Evidencia sintética completa');
        $s->eligibility($this->reviewer, $id, true, 'Evidencia sintética completa');
        $s->merit($this->reviewer, $id, '20', 'CPD-PRUEBA');
        $s->transition($this->authority, $this->call, 'closed');
        $s->transition($this->authority, $this->call, 'evaluation');
        $this->actingAs($this->authority)->getJson('/api/v1/applications/'.$id)->assertJsonPath('can_calculate', false);
        $assignment = DB::table('assignments')->value('id');
        $s->grade($this->evaluator, $id, $assignment, '80', false);
        $this->getJson('/api/v1/applications/'.$id)->assertJsonPath('can_calculate', false);
        $s->grade($this->evaluator, $id, $assignment, '80', true);
        $this->getJson('/api/v1/applications/'.$id)->assertJsonPath('can_calculate', true);
        $this->actingAs($this->reviewer)->getJson('/api/v1/applications/'.$id)->assertJsonPath('application.merit', '20.00')->assertJsonPath('can_merit', false);
        $this->actingAs($this->evaluator)->getJson('/api/v1/applications/'.$id)->assertJsonMissingPath('application.merit')->assertJsonPath('can_view_corrections', true);
        $this->getJson('/api/v1/applications/'.$id.'/corrections')->assertOk()->assertJsonMissingPath('app.merit');
        $this->getJson('/api/v1/applications?workspace=evaluator')->assertJsonPath('applications.0.pending_areas', []);
        $this->actingAs($this->student)->getJson('/api/v1/applications/'.$id)->assertJsonPath('can_view_corrections', false);
    }

    public function test_w06_admin_does_not_inherit_academic_responsibilities(): void
    {
        $this->readyApplication();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->getJson('/api/v1/me/navigation')->assertJsonFragment(['path' => '/administracion'])->assertJsonMissing(['path' => '/revision'])->assertJsonMissing(['path' => '/evaluaciones'])->assertJsonMissing(['path' => '/expedientes']);
        foreach (['reviewer', 'evaluator', 'authority'] as $role) {
            $this->getJson('/api/v1/applications?workspace='.$role)->assertForbidden();
        }
        $this->getJson('/api/v1/applications?workspace=own')->assertJsonCount(0, 'applications');
    }

    public function test_w07_authority_profile_without_effective_scope_has_no_dead_work_links(): void
    {
        $authority = User::factory()->create(['role' => 'authority']);
        $this->actingAs($authority)->getJson('/api/v1/me/navigation')->assertOk()
            ->assertJsonFragment(['path' => '/convocatorias'])
            ->assertJsonMissing(['path' => '/expedientes'])
            ->assertJsonMissing(['path' => '/institucional']);
        $this->getJson('/api/v1/applications?workspace=authority')->assertForbidden();
        $this->getJson('/api/v1/institutional/dossiers')->assertForbidden();
    }
}
