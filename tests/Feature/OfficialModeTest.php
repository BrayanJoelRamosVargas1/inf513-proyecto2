<?php

namespace Tests\Feature;

use App\Domain\Selection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Instalación institucional: las convocatorias se crean y operan en modo OFICIAL, sin etiquetas de demostración,
 * solo con la interfaz React, y con las herramientas de puesta en marcha (administración, clave, verificación).
 */
final class OfficialModeTest extends TestCase
{
    use RefreshDatabase;

    private Selection $s;

    private User $authority;

    protected function setUp(): void
    {
        parent::setUp();
        config(['portal.experience' => 'institutional', 'portal.approval_threshold_default' => 51.0]);
        $this->s = app(Selection::class);
        $this->authority = User::factory()->create(['role' => 'authority']);
    }

    private function newCall(): int
    {
        return $this->s->createCall($this->authority, [
            'title' => 'Convocatoria oficial',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'exam_at' => now()->addDays(2),
            'scope' => 'UAGRM',
            'subject_limit' => 4,
            'source' => 'Resolución del Decanato',
            'management_year' => 2026,
            'call_number' => '01/2026',
        ]);
    }

    private function newOffer(int $call, array $override = []): int
    {
        $category = DB::table('categories')->insertGetId(['name' => 'Docencia '.random_int(1, 99999), 'regime' => 'docencia', 'active' => true]);

        return $this->s->saveOffer($this->authority, $call, $override + [
            'category_id' => $category,
            'title' => 'Oferta '.random_int(1, 99999),
            'seats' => 2,
            'is_subject' => false,
            'modality' => 'Presencial',
            'documents' => ['cpd' => 'Formulario CPD', 'ci' => 'Cédula de identidad'],
            'requirements' => ['activo' => 'Estudiante activo'],
            'weights' => ['Soporte' => '50', 'Redes' => '25', 'Software' => '25'],
            'scheme' => 'AREAS_100',
            'source' => 'Convocatoria 01/2026',
        ]);
    }

    public function test_calls_are_created_and_published_as_official(): void
    {
        $call = $this->newCall();
        $this->assertSame('OFICIAL', DB::table('calls')->where('id', $call)->value('mode'));
        $this->assertSame('OFICIAL', Selection::operationalMode());
        $this->newOffer($call);
        $this->s->transition($this->authority, $call, 'published');
        $this->assertSame('published', DB::table('calls')->where('id', $call)->value('status'));
    }

    public function test_training_installation_keeps_demo_calls(): void
    {
        config(['portal.experience' => 'training']);
        $call = $this->newCall();
        $this->assertSame('DEMO', DB::table('calls')->where('id', $call)->value('mode'));
    }

    public function test_demo_and_historical_calls_are_read_only_in_the_institutional_installation(): void
    {
        $other = DB::table('calls')->insertGetId([
            'title' => 'Histórica', 'mode' => 'HISTORICAL', 'status' => 'archived', 'scope' => 'UAGRM', 'source' => 'Archivo',
            'opens_at' => now()->subYear(), 'closes_at' => now()->subYear()->addDay(), 'exam_at' => now()->subYear()->addDays(2),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('memberships')->insert(['call_id' => $other, 'user_id' => $this->authority->id, 'role' => 'authority']);
        $this->expectException(ValidationException::class);
        $this->s->transition($this->authority, $other, 'published');
    }

    public function test_new_offers_get_the_configured_approval_threshold_and_it_can_be_adjusted(): void
    {
        $call = $this->newCall();
        $default = $this->newOffer($call);
        $this->assertSame('51.00', DB::table('offers')->where('id', $default)->value('approval_threshold'));
        $custom = $this->newOffer($call, ['approval_threshold' => '60.00']);
        $this->assertSame('60.00', DB::table('offers')->where('id', $custom)->value('approval_threshold'));
        config(['portal.approval_threshold_default' => null]);
        $none = $this->newOffer($call);
        $this->assertNull(DB::table('offers')->where('id', $none)->value('approval_threshold'));
    }

    public function test_reports_do_not_carry_the_demo_legend_for_official_calls(): void
    {
        $call = $this->newCall();
        $row = DB::table('calls')->find($call);
        $this->assertStringNotContainsString('DEMO', Selection::reportNotice($row));
        $this->assertStringContainsString('autoridad competente', Selection::reportNotice($row));
        $this->assertSame('consolidado-', Selection::reportPrefix($row, 'consolidado'));
    }

    public function test_blade_views_redirect_to_the_react_app_and_posts_are_closed(): void
    {
        $this->get('/login')->assertRedirect('/p2');
        $this->get('/')->assertRedirect('/p2');
        $this->post('/registro', [])->assertNotFound();
        $this->get('/p2')->assertOk();
        $this->getJson('/api/v1/csrf')->assertOk()->assertJsonPath('operational_mode', 'OFICIAL');
    }

    public function test_session_reports_the_operational_mode_and_hides_training(): void
    {
        $this->actingAs($this->authority)->getJson('/api/v1/me')
            ->assertOk()->assertJsonPath('operational_mode', 'OFICIAL')->assertJsonPath('training_enabled', false);
        $this->actingAs($this->authority)->getJson('/api/v1/sandbox')->assertNotFound();
    }

    public function test_password_change_requires_the_current_password_and_closes_other_sessions(): void
    {
        $user = User::factory()->create(['password' => 'clave-actual-muy-larga']);
        $before = $user->security_version;
        $this->actingAs($user)->postJson('/api/v1/me/password', [
            'current_password' => 'incorrecta-incorrecta',
            'password' => 'clave-nueva-muy-larga-1',
            'password_confirmation' => 'clave-nueva-muy-larga-1',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');
        $this->actingAs($user)->postJson('/api/v1/me/password', [
            'current_password' => 'clave-actual-muy-larga',
            'password' => 'corta',
            'password_confirmation' => 'corta',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
        $this->actingAs($user)->postJson('/api/v1/me/password', [
            'current_password' => 'clave-actual-muy-larga',
            'password' => 'clave-nueva-muy-larga-1',
            'password_confirmation' => 'clave-nueva-muy-larga-1',
        ])->assertNoContent();
        $user->refresh();
        $this->assertTrue(Hash::check('clave-nueva-muy-larga-1', $user->password));
        $this->assertSame($before + 1, $user->security_version);
        $this->assertTrue(DB::table('audit_events')->where('action', 'user.password_changed')->where('entity', 'user:'.$user->id)->exists());
    }

    public function test_a_full_selection_runs_in_official_mode_and_the_threshold_leaves_out_low_scores(): void
    {
        Storage::fake('local');
        $reviewer = User::factory()->create(['role' => 'staff']);
        $teacher = User::factory()->create(['role' => 'staff']);
        $call = $this->newCall();
        foreach ([[$reviewer, 'reviewer'], [$teacher, 'evaluator']] as [$u, $role]) {
            DB::table('memberships')->insert(['call_id' => $call, 'user_id' => $u->id, 'role' => $role]);
        }
        $offer = $this->newOffer($call, ['seats' => 2, 'documents' => ['cpd' => 'Formulario CPD'], 'weights' => ['Soporte' => '100']]);
        $this->s->transition($this->authority, $call, 'published');
        $this->s->assign($this->authority, $offer, $teacher->id, 'Soporte');
        $assignment = DB::table('assignments')->where('offer_id', $offer)->value('id');

        $applications = [];
        foreach (['90', '30'] as $score) {
            $student = User::factory()->create();
            $id = $this->s->apply($student, $offer);
            $this->s->upload($student, $id, 'cpd', UploadedFile::fake()->image('cpd.png'));
            $this->scanApplicantFixtures();
            $this->s->submit($student, $id);
            $this->s->review($reviewer, $id, ['doc:cpd' => 'meets', 'activo' => 'meets'], 'Revisión completa del expediente.');
            $this->s->eligibility($reviewer, $id, true, 'Cumple todos los requisitos.');
            $this->s->merit($reviewer, $id, '20.00', 'CPD-OFICIAL');
            $applications[$score] = $id;
        }
        $this->s->transition($this->authority, $call, 'closed');
        $this->s->transition($this->authority, $call, 'evaluation');
        foreach ($applications as $score => $id) {
            $this->s->grade($teacher, $id, $assignment, $score);
            $this->s->calculate($this->authority, $id);
        }

        $ranking = $this->s->ranking($offer);
        $this->assertSame([$applications['90']], array_map(fn ($a) => $a->id, $ranking['rank']));
        $this->assertSame([$applications['30']], array_map(fn ($a) => $a->id, $ranking['below_threshold']));
        $this->assertSame(1, $ranking['vacancies']);

        $this->s->approve($this->authority, $offer, 'Acta de la comisión con la lista de seleccionados.');
        $this->assertSame('selected', DB::table('applications')->where('id', $applications['90'])->value('selection'));
        $this->assertSame('not_selected', DB::table('applications')->where('id', $applications['30'])->value('selection'));
        $snapshot = json_decode(DB::table('publications')->where('offer_id', $offer)->value('snapshot'), true);
        $this->assertSame('OFICIAL', $snapshot['mode']);
    }

    public function test_console_creates_an_administrator_with_a_random_password(): void
    {
        $this->artisan('ficct:crear-administrador', ['email' => 'admin@ficct.uagrm.edu.bo', '--nombre' => 'Administración FICCT'])
            ->expectsOutputToContain('Administración creada')->assertSuccessful();
        $admin = User::where('email', 'admin@ficct.uagrm.edu.bo')->firstOrFail();
        $this->assertSame('admin', $admin->role);
        $this->assertTrue($admin->active);
        $this->artisan('ficct:crear-administrador', ['email' => 'admin@ficct.uagrm.edu.bo', '--nombre' => 'Otra'])->assertFailed();
    }

    public function test_console_resets_a_password_and_invalidates_sessions(): void
    {
        $user = User::factory()->create(['password' => 'clave-actual-muy-larga']);
        $before = $user->security_version;
        $this->artisan('ficct:restablecer-clave', ['email' => $user->email])->expectsOutputToContain('Clave nueva')->assertSuccessful();
        $user->refresh();
        $this->assertFalse(Hash::check('clave-actual-muy-larga', $user->password));
        $this->assertSame($before + 1, $user->security_version);
        $this->artisan('ficct:restablecer-clave', ['email' => 'nadie@ficct.uagrm.edu.bo'])->assertFailed();
    }

    public function test_installation_check_reports_what_is_missing(): void
    {
        $this->artisan('ficct:verificar-instalacion')->expectsOutputToContain('[OK]')->assertFailed();
    }
}
