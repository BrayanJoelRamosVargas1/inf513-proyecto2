<?php

namespace Tests\Feature;

use App\Domain\Access;
use App\Domain\Administration;
use App\Domain\Rectifications;
use App\Domain\Selection;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class GovernanceTest extends TestCase
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
        $this->call = $this->s->createCall($this->authority, ['title' => 'Fixture DEMO', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'exam_at' => now()->addDays(2), 'scope' => 'UAGRM', 'subject_limit' => 4, 'source' => 'Fixture sintético de aceptación']);
        foreach ([[$this->reviewer, 'reviewer'], [$this->teacher, 'evaluator']] as [$u,$role]) {
            DB::table('memberships')->insert(['user_id' => $u->id, 'call_id' => $this->call, 'role' => $role]);
        }
        $category = DB::table('categories')->insertGetId(['name' => 'Sala DEMO', 'regime' => 'docencia', 'active' => true]);
        $this->offer = $this->s->saveOffer($this->authority, $this->call, ['category_id' => $category, 'title' => 'Sala A', 'seats' => 2, 'is_subject' => false, 'modality' => 'Presencial', 'documents' => ['cpd' => 'Formulario CPD', 'ci' => 'Identidad sintética'], 'requirements' => ['activo' => 'Estudiante activo'], 'weights' => ['Soporte' => '50', 'Redes' => '25', 'Software' => '25'], 'scheme' => 'DEMO_AREAS_100', 'source' => 'Fixture DEMO']);
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
        $this->s->review($this->reviewer, $id, ['doc:cpd' => 'meets', 'doc:ci' => 'meets', 'activo' => 'meets'], 'Evidencia sintética cotejada en DEMO.');
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

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function publishedResult(): int
    {
        $id = $this->ready();
        $this->evaluation();
        $this->notes($id);
        $this->s->approve($this->authority, $this->offer, 'Acta sintética de aprobación inicial.');

        return $id;
    }

    private function requestMerit(int $id, string $score = '25.25'): int
    {
        return app(Rectifications::class)->request($this->reviewer, $id, null, $score, 'Corrección certificada para prueba DEMO.', 'CPD-SINTETICO-RECTIFICACION-01');
    }

    private function approveRequest(int $id): void
    {
        app(Rectifications::class)->decide($this->authority, $id, true, 'Autorización técnica sintética documentada.');
    }

    public function test_g01_admin_grants_scoped_temporary_permission(): void
    {
        $target = User::factory()->create(['role' => 'staff']);
        $id = app(Administration::class)->grant($this->admin(), $this->call, $target->id, 'reviewer', now()->subHour()->toIso8601String(), now()->addHour()->toIso8601String(), 'Concesión sintética temporal.');
        $this->assertTrue(Access::has($target, $this->call, ['reviewer']));
        $this->assertDatabaseHas('audit_events', ['action' => 'permission.granted', 'entity' => 'membership:'.$id]);
    }

    public function test_g02_unauthorized_grant_is_forbidden(): void
    {
        $this->actingAs($this->student)->post('/permisos', ['call_id' => $this->call, 'user_id' => $this->student->id, 'role' => 'authority'])->assertForbidden();
    }

    public function test_g03_revoke_invalidates_existing_session(): void
    {
        $this->actingAs($this->teacher)->get('/')->assertOk();
        $m = DB::table('memberships')->where('user_id', $this->teacher->id)->first();
        app(Administration::class)->revoke($this->admin(), $m->id, $m->version, 'Revocación sintética de prueba.');
        $this->get('/')->assertRedirect('/login');
        $this->assertFalse(Access::has($this->teacher, $this->call, ['evaluator']));
    }

    public function test_g04_expired_permission_rejected(): void
    {
        DB::table('memberships')->where('user_id', $this->teacher->id)->update(['expires_at' => now()]);
        $this->assertFalse(Access::has($this->teacher, $this->call, ['evaluator']));
    }

    public function test_g05_future_permission_not_effective(): void
    {
        DB::table('memberships')->where('user_id', $this->teacher->id)->update(['starts_at' => now()->addMinute()]);
        $this->assertFalse(Access::has($this->teacher, $this->call, ['evaluator']));
    }

    public function test_g06_independent_call_scope(): void
    {
        $other = $this->s->createCall($this->authority, ['title' => 'Otra DEMO', 'scope' => 'UAGRM', 'opens_at' => now(), 'closes_at' => now()->addDay(), 'exam_at' => now()->addDays(2)]);
        $this->assertFalse(Access::has($this->teacher, $other, ['evaluator']));
        $this->assertTrue(Access::has($this->teacher, $this->call, ['evaluator']));
    }

    public function test_g07_revoked_evaluator_cannot_grade_with_stale_user(): void
    {
        $id = $this->ready();
        $this->evaluation();
        $m = DB::table('memberships')->where('user_id', $this->teacher->id)->first();
        app(Administration::class)->revoke($this->admin(), $m->id, 1, 'Revocación sintética de prueba.');
        $this->expectException(HttpException::class);
        $this->s->grade($this->teacher, $id, DB::table('assignments')->value('id'), '90');
    }

    public function test_g08_last_active_admin_cannot_be_disabled(): void
    {
        $u = $this->admin();
        $this->rejects(fn () => app(Administration::class)->profile($u, $u->id, ['active' => false], 1, 'Desactivación de prueba sintética.'));
        $this->assertTrue($u->fresh()->active);
    }

    public function test_g09_profile_changes_require_current_version(): void
    {
        $u = $this->admin();
        $svc = app(Administration::class);
        $svc->profile($u, $this->student->id, ['name' => 'Nombre actualizado'], 1, 'Corrección del perfil sintético.');
        $this->rejects(fn () => $svc->profile($u, $this->student->id, ['name' => 'Cambio obsoleto'], 1, 'Segunda modificación concurrente.'));
        $this->assertSame('Nombre actualizado', $this->student->fresh()->name);
    }

    public function test_g10_revocation_is_versioned_and_not_deleted(): void
    {
        $u = $this->admin();
        $m = DB::table('memberships')->where('user_id', $this->teacher->id)->first();
        $svc = app(Administration::class);
        $svc->revoke($u, $m->id, 1, 'Revocación de prueba sintética.');
        $this->rejects(fn () => $svc->revoke($u, $m->id, 1, 'Repetición de operación obsoleta.'));
        $this->assertDatabaseHas('memberships', ['id' => $m->id, 'version' => 2, 'revoked_by' => $u->id]);
    }

    public function test_g11_reassignment_preserves_original_evaluator(): void
    {
        $id = $this->ready();
        $this->evaluation();
        $this->notes($id);
        $as = DB::table('assignments')->first();
        $new = User::factory()->create(['role' => 'staff']);
        app(Administration::class)->grant($this->admin(), $this->call, $new->id, 'evaluator', null, null, 'Nuevo evaluador sintético.');
        app(Administration::class)->reassign($this->authority, $as->id, $new->id, 1, 'Reasignación autorizada sintética.');
        $this->assertDatabaseHas('grades', ['assignment_id' => $as->id, 'user_id' => $this->teacher->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'assignment.reassigned']);
        $this->expectException(HttpException::class);
        $this->s->grade($this->teacher, $id, $as->id, '99');
    }

    public function test_g12_merit_approved_exact_recalculation(): void
    {
        $id = $this->publishedResult();
        $request = $this->requestMerit($id);
        $this->approveRequest($request);
        $this->assertDatabaseHas('applications', ['id' => $id, 'merit' => '25.25', 'exam' => '48.00', 'final' => '73.25', 'merit_version' => 2, 'selection' => 'under_review']);
        $this->assertDatabaseHas('offers', ['id' => $this->offer, 'result_revision' => 1, 'needs_publication_review' => true]);
    }

    public function test_g13_merit_rejection_keeps_score(): void
    {
        $id = $this->publishedResult();
        $request = $this->requestMerit($id);
        app(Rectifications::class)->decide($this->authority, $request, false, 'Respaldo insuficiente para corregir.');
        $this->assertDatabaseHas('applications', ['id' => $id, 'merit' => '20.10', 'final' => '68.10']);
        $this->assertDatabaseCount('result_revisions', 0);
    }

    public function test_g14_exam_approved_preserves_original_and_versions(): void
    {
        $id = $this->publishedResult();
        $g = DB::table('grades')->where('application_id', $id)->first();
        $r = app(Rectifications::class)->request($this->teacher, $id, $g->id, '90', 'Error de transcripción verificado.', 'ACTA-DEMO-RESPALDO-02');
        $this->approveRequest($r);
        $this->assertDatabaseHas('score_versions', ['grade_id' => $g->id, 'version' => 1, 'score' => $g->score]);
        $this->assertDatabaseHas('score_versions', ['grade_id' => $g->id, 'version' => 2, 'score' => '90.00']);
        $this->assertDatabaseHas('grades', ['id' => $g->id, 'user_id' => $this->teacher->id, 'version' => 2]);
        $this->assertDatabaseHas('correction_requests', ['id' => $r, 'original_evaluator_id' => $this->teacher->id, 'decided_by' => $this->authority->id]);
    }

    public function test_g15_reason_and_evidence_required(): void
    {
        $id = $this->publishedResult();
        $this->rejects(fn () => app(Rectifications::class)->request($this->reviewer, $id, null, '30', '', ''));
        $this->assertDatabaseCount('correction_requests', 0);
    }

    public function test_g16_requester_cannot_approve_own_request(): void
    {
        $id = $this->publishedResult();
        $r = app(Rectifications::class)->request($this->authority, $id, null, '30', 'Solicitud propia para prueba DEMO.', 'CPD-SINTETICO-PRUEBA');
        $this->rejects(fn () => $this->approveRequest($r));
        $this->assertDatabaseHas('correction_requests', ['id' => $r, 'status' => 'pending']);
    }

    public function test_g17_historical_publication_byte_preservation_and_new_approval(): void
    {
        $id = $this->publishedResult();
        $old = DB::table('publications')->first();
        $this->approveRequest($this->requestMerit($id));
        $this->assertSame($old->snapshot, DB::table('publications')->where('id', $old->id)->value('snapshot'));
        $this->assertDatabaseCount('publications', 1);
        $this->s->approve($this->authority, $this->offer, 'Acta sintética de republicación revisada.');
        $this->assertDatabaseHas('publications', ['version' => 2, 'result_revision' => 1, 'previous_publication_id' => $old->id]);
        $this->assertDatabaseHas('offers', ['id' => $this->offer, 'needs_publication_review' => false]);
    }

    public function test_g18_database_blocks_direct_snapshot_update(): void
    {
        $this->publishedResult();
        $this->expectException(QueryException::class);
        DB::table('publications')->update(['snapshot' => '{}']);
    }

    public function test_g19_conflicting_corrections_only_one_version_approved(): void
    {
        $id = $this->publishedResult();
        $one = $this->requestMerit($id, '25');
        $two = $this->requestMerit($id, '30');
        $this->approveRequest($one);
        $this->rejects(fn () => $this->approveRequest($two));
        $this->assertDatabaseHas('applications', ['id' => $id, 'merit' => '25.00', 'merit_version' => 2]);
        $this->assertDatabaseHas('correction_requests', ['id' => $two, 'status' => 'pending']);
        $this->assertDatabaseCount('result_revisions', 1);
    }

    public function test_g20_pending_correction_prevents_publication(): void
    {
        $id = $this->ready();
        $this->evaluation();
        $this->notes($id);
        $this->requestMerit($id);
        $this->rejects(fn () => $this->s->approve($this->authority, $this->offer, 'Acta sintética no autorizable.'));
        $this->assertDatabaseCount('publications', 0);
    }

    public function test_g21_changed_ranking_and_new_tie_do_not_reassign_seats(): void
    {
        $a = $this->ready();
        $b = $this->ready(User::factory()->create());
        DB::table('offers')->where('id', $this->offer)->update(['seats' => 1]);
        $this->evaluation();
        $this->notes($a);
        $this->notes($b, ['Soporte' => '70', 'Redes' => '60', 'Software' => '100']);
        $this->s->approve($this->authority, $this->offer, 'Acta inicial sin empate sintético.');
        $this->approveRequest($this->requestMerit($b, '23.10'));
        $ranking = $this->s->ranking($this->offer);
        $this->assertEqualsCanonicalizing([$a, $b], $ranking['tied']);
        $this->assertDatabaseHas('applications', ['id' => $b, 'selection' => 'under_review']);
        $this->rejects(fn () => $this->s->approve($this->authority, $this->offer, 'Acta no válida sin nuevo examen.'));
        $this->assertDatabaseCount('publications', 1);
        $this->approveRequest($this->requestMerit($b, '24.10'));
        $this->assertSame($b, $this->s->ranking($this->offer)['rank'][0]->id);
        $changes = Selection::json(DB::table('result_revisions')->orderByDesc('revision')->value('changes'));
        $this->assertSame($b, $changes[0]['application_id']);
        $this->assertSame(1, $changes[0]['new_position']);
    }

    public function test_g22_ui_renders_permissions_corrections_and_revisions(): void
    {
        $id = $this->publishedResult();
        $this->approveRequest($this->requestMerit($id));
        $this->actingAs($this->authority)->get('/postulaciones/'.$id.'/rectificaciones')->assertOk()->assertSee('25.25')->assertSee('20.10');
        $this->get('/ofertas/'.$this->offer.'/resultados')->assertOk()->assertSee('Revisión 1 pendiente');
        $this->actingAs($this->admin())->get('/usuarios')->assertOk()->assertSee('Permisos efectivos');
    }

    public function test_g23_student_cannot_view_correction_evidence(): void
    {
        $id = $this->publishedResult();
        $this->requestMerit($id);
        $this->actingAs($this->student)->get('/postulaciones/'.$id.'/rectificaciones')->assertForbidden();
    }

    public function test_g24_database_blocks_modifying_score_history(): void
    {
        $this->publishedResult();
        $this->expectException(QueryException::class);
        DB::table('score_versions')->update(['score' => '0.00']);
    }

    public function test_g25_disabled_account_loses_effective_permissions(): void
    {
        app(Administration::class)->profile($this->admin(), $this->teacher->id, ['active' => false], 1, 'Desactivación sintética autorizada.');
        $this->assertFalse(Access::has($this->teacher, $this->call, ['evaluator']));
    }

    public function test_g26_correction_after_call_results_requires_republication_before_archive(): void
    {
        $id = $this->publishedResult();
        $this->s->transition($this->authority, $this->call, 'results');
        $this->approveRequest($this->requestMerit($id));
        $this->rejects(fn () => $this->s->transition($this->authority, $this->call, 'archived'));
        $this->s->approve($this->authority, $this->offer, 'Nueva acta sintética luego del cierre.');
        $this->s->transition($this->authority, $this->call, 'archived');
        $this->assertDatabaseHas('calls', ['id' => $this->call, 'status' => 'archived']);
        $this->rejects(fn () => $this->requestMerit($id, '26'));
    }

    public function test_g27_merit_correction_before_exam_keeps_result_incomplete(): void
    {
        $id = $this->ready();
        $this->evaluation();
        $this->approveRequest($this->requestMerit($id));
        $this->assertDatabaseHas('applications', ['id' => $id, 'merit' => '25.25', 'exam' => null, 'final' => null]);
        $this->rejects(fn () => $this->s->approve($this->authority, $this->offer, 'Acta bloqueada por notas faltantes.'));
        $this->notes($id);
        $this->s->approve($this->authority, $this->offer, 'Acta completa sintética luego del examen.');
        $this->assertDatabaseHas('applications', ['id' => $id, 'final' => '73.25', 'selection' => 'selected']);
    }

    public function test_g29_http_request_decision_and_scoped_queue(): void
    {
        $id = $this->publishedResult();
        $grade = DB::table('grades')->where('application_id', $id)->first();
        $this->actingAs($this->teacher)->post('/postulaciones/'.$id.'/rectificaciones', ['grade_id' => $grade->id, 'score' => '90', 'reason' => 'Rectificación sintética por error verificado.', 'evidence' => 'ACTA-SINTETICA-HTTP-01'])->assertSessionHasNoErrors()->assertRedirect();
        $request = DB::table('correction_requests')->value('id');
        $this->actingAs($this->authority)->get('/rectificaciones')->assertOk()->assertSee('1 pendientes');
        $this->post('/rectificaciones/'.$request.'/decision', ['approved' => '1', 'reason' => 'Decisión administrativa técnica de prueba.'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('correction_requests', ['id' => $request, 'status' => 'approved']);
        $this->actingAs($this->student)->get('/rectificaciones')->assertForbidden();
        $this->get('/login')->assertOk();
        $this->post('/salir')->assertRedirect('/login');
        $this->get('/login')->assertOk();
    }

    public function test_g30_old_resolved_tie_cannot_resolve_a_new_revision(): void
    {
        $a = $this->ready();
        $b = $this->ready(User::factory()->create());
        DB::table('offers')->where('id', $this->offer)->update(['seats' => 1]);
        $this->evaluation();
        $this->notes($a);
        $this->notes($b);
        $tie = $this->s->openTie($this->authority, $this->offer, ['commission' => ['Comisión DEMO'], 'conditions' => 'Condiciones sintéticas por comisión.', 'scheduled_at' => now()->subHour()]);
        $this->s->resolveTie($this->authority, $tie, [$a => 'Acta decide', $b => 'Acta decide'], [$a], 'Acta sintética del examen original.');
        $this->s->approve($this->authority, $this->offer, 'Acta sintética de publicación original.');
        $this->approveRequest($this->requestMerit($a, '21.10'));
        $this->approveRequest($this->requestMerit($b, '21.10'));
        $this->rejects(fn () => $this->s->approve($this->authority, $this->offer, 'No puede reutilizar acta de otro ranking.'));
        $next = $this->s->openTie($this->authority, $this->offer, ['commission' => ['Comisión DEMO'], 'conditions' => 'Condiciones sintéticas para nueva revisión.', 'scheduled_at' => now()->subHour()]);
        $this->assertDatabaseHas('tie_exams', ['id' => $next, 'result_revision' => 2]);
        $this->assertDatabaseHas('tie_exams', ['id' => $tie, 'result_revision' => 0, 'status' => 'resolved']);
        $this->assertDatabaseCount('publications', 1);
    }

    public function test_g31_legacy_session_without_version_cannot_survive_revocation(): void
    {
        $m = DB::table('memberships')->where('user_id', $this->teacher->id)->first();
        app(Administration::class)->revoke($this->admin(), $m->id, 1, 'Revocación antes de actualizar la sesión antigua.');
        $this->actingAs($this->teacher)->withSession(['legacy_session' => true])->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_g28_successive_tie_exams_keep_previous_round_and_do_not_invent_winners(): void
    {
        $a = $this->ready();
        $b = $this->ready(User::factory()->create());
        DB::table('offers')->where('id', $this->offer)->update(['seats' => 1]);
        $this->evaluation();
        $this->notes($a);
        $this->notes($b);
        $conditions = ['commission' => ['Comisión DEMO'], 'conditions' => 'Condiciones sintéticas definidas por comisión.', 'scheduled_at' => now()->subHours(2)];
        $one = $this->s->openTie($this->authority, $this->offer, $conditions);
        $two = $this->s->continueTie($this->authority, $one, [$a => 'Persiste empate según acta', $b => 'Persiste empate según acta'], array_merge($conditions, ['scheduled_at' => now()->subHour(), 'minutes' => 'Acta sintética: persiste el empate.']));
        $this->assertDatabaseHas('tie_exams', ['id' => $one, 'status' => 'continued', 'winners' => null]);
        $this->assertDatabaseHas('tie_exams', ['id' => $two, 'previous_tie_id' => $one, 'round' => 2, 'status' => 'scheduled']);
        $this->rejects(fn () => $this->s->approve($this->authority, $this->offer, 'Acta bloqueada hasta concluir nuevo examen.'));
        $this->s->resolveTie($this->authority, $two, [$a => 'Resultado registrado por comisión', $b => 'Resultado registrado por comisión'], [$b], 'Acta sintética resuelve segunda ronda.');
        $this->s->approve($this->authority, $this->offer, 'Acta sintética aprueba luego de nueva ronda.');
        $this->assertDatabaseHas('applications', ['id' => $b, 'selection' => 'selected']);
    }
}
