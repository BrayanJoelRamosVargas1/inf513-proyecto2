<?php

namespace Tests\Feature;

use App\Domain\Participation;
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

final class ParticipationTest extends TestCase
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

    private function observed(): int
    {
        $id = $this->app();
        $this->documents($id);
        $this->s->submit($this->student, $id);
        $this->s->review($this->reviewer, $id, [
            'doc:cpd' => 'meets',
            'doc:ci' => 'observed',
            'activo' => 'meets',
        ], 'Documento observado con evidencia sintética.');

        return $id;
    }

    private function window(int $id, array $kinds = ['ci']): int
    {
        return app(Participation::class)->open($this->reviewer, $id, $kinds, now()->addHour()->toIso8601String(), 'Observación sintética que requiere subsanar.', 'ACTA-DEMO-OBSERVACION-001');
    }

    public function test_workspace_distinguishes_student_correction_from_reviewer_work(): void
    {
        $id = $this->observed();
        $window = $this->window($id);
        $this->actingAs($this->student)->getJson('/api/v1/applications?workspace=own')
            ->assertOk()->assertJsonPath('applications.0.pending_action', 'correct_documents');
        $this->actingAs($this->reviewer)->getJson('/api/v1/applications?workspace=reviewer')
            ->assertOk()->assertJsonPath('applications.0.pending_action', null);
        $this->seal($window);
        $this->actingAs($this->student)->getJson('/api/v1/applications?workspace=own')
            ->assertOk()->assertJsonPath('applications.0.pending_action', null);
        $this->actingAs($this->reviewer)->getJson('/api/v1/applications?workspace=reviewer')
            ->assertOk()->assertJsonPath('applications.0.pending_action', 'review_amendment');
        app(Participation::class)->review($this->reviewer, $window,
            ['doc:cpd' => 'meets', 'doc:ci' => 'meets', 'activo' => 'meets'], 'Nueva versión contrastada para la prueba de bandejas.');
        $this->getJson('/api/v1/applications?workspace=reviewer')
            ->assertOk()->assertJsonPath('applications.0.pending_action', null);
    }

    public function test_workspace_expired_correction_belongs_to_reviewer_and_respects_membership(): void
    {
        $id = $this->observed();
        $this->window($id);
        $this->travel(2)->hours();
        $this->actingAs($this->student)->getJson('/api/v1/applications?workspace=own')
            ->assertOk()->assertJsonPath('applications.0.pending_action', null);
        $this->actingAs($this->reviewer)->getJson('/api/v1/applications?workspace=reviewer')
            ->assertOk()->assertJsonPath('applications.0.pending_action', 'close_expired_amendment');
        DB::table('memberships')->where('user_id', $this->reviewer->id)->update(['revoked_at' => now()]);
        $this->getJson('/api/v1/applications?workspace=reviewer')->assertForbidden();
        $this->actingAs(User::factory()->create())->getJson('/api/v1/applications?workspace=own')
            ->assertOk()->assertJsonCount(0, 'applications');
    }

    private function seal(int $window): void
    {
        app(Participation::class)->upload($this->student, $window, 'ci', UploadedFile::fake()->image('ci-nueva.png'));
        $this->scanApplicantFixtures();
        app(Participation::class)->submit($this->student, $window);
    }

    private function decide(int $request, bool $yes = true): void
    {
        app(Participation::class)->decide($this->authority, $request, $yes, 'Decisión técnica DEMO documentada con evidencia.');
    }

    private function participationRequest(int $id, string $kind, ?User $user = null): int
    {
        return app(Participation::class)->request($user ?? $this->student, $id, $kind, 'Solicitud sintética voluntaria documentada.', 'ACTA-DEMO-SOLICITUD-001');
    }

    private function published(bool $spare = false): array
    {
        $a = $this->ready();
        $b = $spare ? $this->ready(User::factory()->create()) : null;
        DB::table('offers')->where('id', $this->offer)->update(['seats' => 1]);
        $this->evaluation();
        $this->notes($a);
        if ($b) {
            $this->notes($b, ['Soporte' => '70', 'Redes' => '60', 'Software' => '100']);
        }
        $this->s->approve($this->authority, $this->offer, 'Acta sintética original de selección.');

        return [$a, $b];
    }

    public function test_p01_observed_dossier_opens_window_without_overwriting_submission(): void
    {
        $id = $this->observed();
        $before = DB::table('dossier_snapshots')->first();
        $window = $this->window($id);
        $this->assertDatabaseHas('amendments', ['id' => $window, 'base_version' => 1, 'status' => 'open']);
        $this->assertSame($before->snapshot, DB::table('dossier_snapshots')->where('id', $before->id)->value('snapshot'));
        $this->assertDatabaseHas('applications', ['id' => $id, 'status' => 'submitted', 'dossier_version' => 1, 'eligibility' => 'pending']);
    }

    public function test_p02_new_documents_and_snapshot_are_versioned(): void
    {
        $id = $this->observed();
        $old = DB::table('documents')->where('kind', 'ci')->first();
        $window = $this->window($id);
        $this->seal($window);
        $this->assertDatabaseHas('documents', ['id' => $old->id, 'sha256' => $old->sha256, 'path' => $old->path, 'version' => 1]);
        $this->assertDatabaseHas('documents', ['amendment_id' => $window, 'kind' => 'ci', 'version' => 2]);
        $this->assertDatabaseHas('dossier_snapshots', ['application_id' => $id, 'version' => 2, 'phase' => 'submitted']);
        $this->assertDatabaseHas('applications', ['id' => $id, 'dossier_version' => 2, 'status' => 'submitted']);
    }

    public function test_p03_reviewer_repeats_all_criteria_and_enables_new_version(): void
    {
        $id = $this->observed();
        $m = $this->window($id);
        $this->seal($m);
        app(Participation::class)->review($this->reviewer, $m, [
            'doc:cpd' => 'meets',
            'doc:ci' => 'meets',
            'activo' => 'meets',
        ], 'Nueva revisión completa documentada.');
        $this->assertDatabaseHas('applications', ['id' => $id, 'eligibility' => 'enabled']);
        $this->assertDatabaseHas('amendments', ['id' => $m, 'status' => 'reviewed']);
        $this->assertDatabaseHas('dossier_snapshots', ['application_id' => $id, 'version' => 2, 'phase' => 'reviewed']);
        $prior = DB::table('dossier_snapshots')->where('phase', 'pre_amendment_review')->value('snapshot');
        $this->assertStringContainsString('observed', $prior);
    }

    public function test_p04_partial_review_rejected_and_observed_review_disables(): void
    {
        $id = $this->observed();
        $m = $this->window($id);
        $this->seal($m);
        $s = app(Participation::class);
        $this->rejects(fn () => $s->review($this->reviewer, $m, ['doc:ci' => 'meets'], 'Revisión insuficiente de prueba.'));
        $s->review($this->reviewer, $m, [
            'doc:cpd' => 'meets',
            'doc:ci' => 'observed',
            'activo' => 'meets',
        ], 'Persisten observaciones según evidencia.');
        $this->assertDatabaseHas('applications', ['id' => $id, 'eligibility' => 'disabled']);
    }

    public function test_p05_cannot_upload_after_deadline(): void
    {
        $id = $this->observed();
        $m = $this->window($id);
        $this->travel(2)->hours();
        $this->rejects(fn () => app(Participation::class)->upload($this->student, $m, 'ci', UploadedFile::fake()->image('tardia.png')));
        $this->assertDatabaseMissing('documents', ['amendment_id' => $m]);
    }

    public function test_p06_submission_after_deadline_is_not_accepted(): void
    {
        $id = $this->observed();
        $m = $this->window($id);
        app(Participation::class)->upload($this->student, $m, 'ci', UploadedFile::fake()->image('ci.png'));
        $this->scanApplicantFixtures();
        $this->travel(2)->hours();
        $this->rejects(fn () => app(Participation::class)->submit($this->student, $m));
        $this->assertDatabaseHas('applications', ['id' => $id, 'dossier_version' => 1]);
    }

    public function test_p07_unauthorized_uploader_is_forbidden(): void
    {
        $id = $this->observed();
        $m = $this->window($id);
        $this->expectException(HttpException::class);
        app(Participation::class)->upload(User::factory()->create(), $m, 'ci', UploadedFile::fake()->image('ci.png'));
        $this->scanApplicantFixtures();
    }

    public function test_p08_only_authorized_kinds_and_complete_submission(): void
    {
        $id = $this->observed();
        $m = $this->window($id, ['ci', 'cpd']);
        $s = app(Participation::class);
        $this->rejects(fn () => $s->upload($this->student, $m, 'cv', UploadedFile::fake()->image('cv.png')));
        $s->upload($this->student, $m, 'ci', UploadedFile::fake()->image('ci.png'));
        $this->scanApplicantFixtures();
        $this->rejects(fn () => $s->submit($this->student, $m));
    }

    public function test_p09_sealed_amendment_rejects_further_uploads(): void
    {
        $id = $this->observed();
        $m = $this->window($id);
        $this->seal($m);
        $this->rejects(fn () => app(Participation::class)->upload($this->student, $m, 'ci', UploadedFile::fake()->image('extra.png')));
        $this->rejects(fn () => app(Participation::class)->submit($this->student, $m));
    }

    public function test_p10_legacy_review_and_stage_transition_cannot_bypass_amendment(): void
    {
        $id = $this->observed();
        $this->window($id);
        $this->rejects(fn () => $this->s->review($this->reviewer, $id, ['doc:ci' => 'meets'], 'Intento de saltar nueva revisión.'));
        $this->rejects(fn () => $this->s->eligibility($this->reviewer, $id, true, 'Intento de habilitación anticipada.'));
        $this->s->transition($this->authority, $this->call, 'closed');
        $this->rejects(fn () => $this->s->transition($this->authority, $this->call, 'evaluation'));
    }

    public function test_p11_expired_uploads_do_not_leak_into_next_dossier(): void
    {
        $id = $this->observed();
        $originalCpd = DB::table('documents')->where('kind', 'cpd')->value('id');
        $m = $this->window($id, ['ci', 'cpd']);
        $s = app(Participation::class);
        $unused = $s->upload($this->student, $m, 'cpd', UploadedFile::fake()->image('descartada.png'));
        $this->scanApplicantFixtures();
        $this->travel(2)->hours();
        $s->expire($this->reviewer, $m, 'Cierre DEMO documentado sin entrega completa.');
        $this->assertDatabaseHas('applications', ['id' => $id, 'eligibility' => 'disabled']);
        $new = $this->window($id);
        $this->seal($new);
        $snap = Selection::json(DB::table('dossier_snapshots')->orderByDesc('id')->value('snapshot'));
        $this->assertContains($originalCpd, array_column($snap['documents'], 'id'));
        $this->assertNotContains($unused, array_column($snap['documents'], 'id'));
    }

    public function test_p12_snapshot_and_document_metadata_are_immutable(): void
    {
        $this->observed();
        $this->expectException(QueryException::class);
        DB::table('dossier_snapshots')->update(['snapshot' => '{}']);
    }

    public function test_p13_original_document_cannot_be_overwritten_in_database(): void
    {
        $this->observed();
        $this->expectException(QueryException::class);
        DB::table('documents')->update(['sha256' => str_repeat('0', 64)]);
    }

    public function test_p14_draft_withdrawal_requires_approval_and_keeps_history(): void
    {
        $id = $this->app();
        $r = $this->participationRequest($id, 'withdrawal');
        $this->assertDatabaseHas('applications', ['id' => $id, 'participation' => 'active']);
        $this->decide($r);
        $this->assertDatabaseHas('applications', ['id' => $id, 'participation' => 'withdrawn', 'status' => 'draft']);
        $this->assertDatabaseCount('participation_decisions', 1);
        $this->rejects(fn () => $this->s->submit($this->student, $id));
    }

    public function test_p15_rejected_withdrawal_does_not_change_participation(): void
    {
        $id = $this->app();
        $this->decide($this->participationRequest($id, 'withdrawal'), false);
        $this->assertDatabaseHas('applications', ['id' => $id, 'participation' => 'active']);
        $this->assertDatabaseCount('result_revisions', 0);
    }

    public function test_p16_awarded_candidate_must_request_renunciation_not_withdrawal(): void
    {
        [$id] = $this->published();
        $this->rejects(fn () => $this->participationRequest($id, 'withdrawal'));
        $this->assertGreaterThan(0, $this->participationRequest($id, 'renunciation'));
    }

    public function test_p17_unawarded_candidate_cannot_renounce_a_seat(): void
    {
        $id = $this->app();
        $this->rejects(fn () => $this->participationRequest($id, 'renunciation'));
    }

    public function test_p18_renunciation_keeps_grades_publication_and_vacancy_without_replacement(): void
    {
        [$a,$b] = $this->published(true);
        $old = DB::table('publications')->first();
        $notes = DB::table('grades')->orderBy('id')->get()->toJson();
        $r = $this->participationRequest($a, 'renunciation');
        $this->decide($r);
        $this->assertDatabaseHas('applications', ['id' => $a, 'participation' => 'resigned', 'final' => '68.10']);
        $this->assertDatabaseHas('offers', ['id' => $this->offer, 'seats' => 1, 'held_vacancies' => 1, 'needs_publication_review' => true]);
        $this->assertSame($old->snapshot, DB::table('publications')->where('id', $old->id)->value('snapshot'));
        $this->assertSame($notes, DB::table('grades')->orderBy('id')->get()->toJson());
        $this->assertDatabaseHas('vacancy_events', ['participation_request_id' => $r, 'publication_id' => $old->id]);
        $this->assertDatabaseHas('result_revisions', ['participation_request_id' => $r]);
        $this->s->approve($this->authority, $this->offer, 'Acta DEMO ratifica vacante sin reemplazante.');
        $snapshot = Selection::json(DB::table('publications')->orderByDesc('version')->value('snapshot'));
        $this->assertSame([], $snapshot['selected']);
        $this->assertDatabaseHas('applications', ['id' => $b, 'selection' => 'not_selected']);
        $this->assertSame(1, $this->s->ranking($this->offer)['vacancies']);
        $this->actingAs($this->authority)->get('/ofertas/'.$this->offer.'/resultados')->assertOk()->assertSee('1 plaza vacante retenida')->assertSee('Participación #'.$r);
    }

    public function test_p19_pending_participation_blocks_publication(): void
    {
        [$a] = $this->published();
        $this->participationRequest($a, 'renunciation');
        $this->rejects(fn () => $this->s->approve($this->authority, $this->offer, 'No se permite publicación con petición pendiente.'));
    }

    public function test_p20_only_scoped_authority_can_decide(): void
    {
        $id = $this->app();
        $r = $this->participationRequest($id, 'withdrawal');
        $other = User::factory()->create(['role' => 'authority']);
        $this->expectException(HttpException::class);
        app(Participation::class)->decide($other, $r, true, 'Autoridad fuera del ámbito de la convocatoria.');
    }

    public function test_p21_duplicate_request_and_decision_rejected(): void
    {
        $id = $this->app();
        $r = $this->participationRequest($id, 'withdrawal');
        $this->rejects(fn () => $this->participationRequest($id, 'withdrawal'));
        $this->decide($r);
        $this->rejects(fn () => $this->decide($r));
        $this->assertDatabaseCount('participation_decisions', 1);
    }

    public function test_p22_withdrawn_application_cannot_be_graded_or_corrected(): void
    {
        $a = $this->ready();
        $this->evaluation();
        $this->notes($a);
        $this->decide($this->participationRequest($a, 'withdrawal'));
        $this->rejects(fn () => $this->s->calculate($this->authority, $a));
        $this->rejects(fn () => app(Rectifications::class)->request($this->reviewer, $a, null, '30', 'Solicitud sobre expediente ya retirado.', 'CPD-SINTETICO-RETIRADO'));
        $this->assertSame([], $this->s->ranking($this->offer)['rank']);
    }

    public function test_p23_acceptance_is_versioned_and_does_not_change_publication(): void
    {
        [$a] = $this->published();
        $pub = DB::table('publications')->first();
        $s = app(Participation::class);
        $s->choose($this->student, $a, $pub->id, 'accept', 0, 'Manifestación sintética de aceptación.', 'ACTA-DEMO-ACEPTACION');
        $s->choose($this->student, $a, $pub->id, 'defer', 1, 'Cambio de manifestación para revisión.', 'ACTA-DEMO-DIFERIMIENTO');
        $this->assertDatabaseCount('award_choices', 2);
        $this->assertSame($pub->snapshot, DB::table('publications')->value('snapshot'));
        $this->rejects(fn () => $s->choose($this->student, $a, $pub->id, 'accept', 1, 'Versión obsoleta de manifestación.', 'ACTA-DEMO-OBSOLETA'));
    }

    public function test_p24_acceptance_cannot_reference_an_old_publication(): void
    {
        [$a] = $this->published();
        $old = DB::table('publications')->value('id');
        $r = app(Rectifications::class)->request($this->reviewer, $a, null, '21', 'Rectificación sintética de mérito certificado.', 'CPD-DEMO-REFERENCIA');
        app(Rectifications::class)->decide($this->authority, $r, true, 'Aprobación sintética de otra autoridad.');
        $this->s->approve($this->authority, $this->offer, 'Nueva publicación sintética de prueba.');
        $this->rejects(fn () => app(Participation::class)->choose($this->student, $a, $old, 'accept', 0, 'Intento sobre publicación anterior.', 'ACTA-DEMO-ANTERIOR'));
    }

    public function test_p25_http_process_views_and_private_scope(): void
    {
        $id = $this->observed();
        $this->window($id);
        $this->actingAs($this->student)->get('/postulaciones/'.$id.'/procesos')->assertOk()->assertSee('Subsanación #');
        $this->actingAs($this->reviewer)->get('/postulaciones/'.$id.'/procesos')->assertOk()->assertSee('Documentos autorizados');
        $this->actingAs(User::factory()->create())->get('/postulaciones/'.$id.'/procesos')->assertForbidden();
    }

    public function test_p27_cannot_request_withdrawal_for_another_owner(): void
    {
        $id = $this->app();
        $this->expectException(HttpException::class);
        $this->participationRequest($id, 'withdrawal', User::factory()->create());
    }

    public function test_p28_authority_cannot_accept_in_place_of_candidate(): void
    {
        [$id] = $this->published();
        $pub = DB::table('publications')->value('id');
        $this->expectException(HttpException::class);
        app(Participation::class)->choose($this->authority, $id, $pub, 'accept', 0, 'Manifestación ajena no permitida.', 'ACTA-SINTETICA-PRUEBA');
    }

    public function test_p29_timely_submission_can_be_reviewed_after_upload_deadline(): void
    {
        $id = $this->observed();
        $m = $this->window($id);
        $this->seal($m);
        $this->travel(2)->hours();
        app(Participation::class)->review($this->reviewer, $m, [
            'doc:cpd' => 'meets',
            'doc:ci' => 'meets',
            'activo' => 'meets',
        ], 'Revisión posterior de entrega oportuna.');
        $this->assertDatabaseHas('applications', ['id' => $id, 'eligibility' => 'enabled']);
    }

    public function test_p30_owner_cannot_review_own_dossier_even_with_scoped_role(): void
    {
        $id = $this->observed();
        $m = $this->window($id);
        $this->seal($m);
        DB::table('memberships')->insert(['call_id' => $this->call, 'user_id' => $this->student->id, 'role' => 'reviewer']);
        $this->expectException(HttpException::class);
        app(Participation::class)->review($this->student, $m, [
            'doc:cpd' => 'meets',
            'doc:ci' => 'meets',
            'activo' => 'meets',
        ], 'Intento de revisión del expediente propio.');
    }

    public function test_p31_acceptance_history_cannot_be_overwritten(): void
    {
        [$id] = $this->published();
        $pub = DB::table('publications')->value('id');
        app(Participation::class)->choose($this->student, $id, $pub, 'accept', 0, 'Manifestación sintética de aceptación.', 'ACTA-SINTETICA-ACEPTACION');
        $this->expectException(QueryException::class);
        DB::table('award_choices')->update(['choice' => 'defer']);
    }

    public function test_p32_new_correction_preserves_a_previously_renounced_state(): void
    {
        [$a,$b] = $this->published(true);
        $this->decide($this->participationRequest($a, 'renunciation'));
        $r = app(Rectifications::class)->request($this->reviewer, $b, null, '30', 'Corrección de mérito de candidato activo.', 'CPD-SINTETICO-ACTIVO');
        app(Rectifications::class)->decide($this->authority, $r, true, 'Decisión sintética posterior a una renuncia.');
        $this->assertDatabaseHas('applications', ['id' => $a, 'participation' => 'resigned', 'selection' => 'resigned']);
        $this->s->approve($this->authority, $this->offer, 'Acta conserva vacante pese a nuevo ranking.');
        $this->assertDatabaseHas('applications', ['id' => $b, 'selection' => 'not_selected']);
    }

    public function test_v01_evaluator_sees_restricted_review_without_false_pending_or_private_files(): void
    {
        $id = $this->ready();
        $this->evaluation();
        $before = DB::table('reviews')->orderBy('id')->get()->toJson();
        $response = $this->actingAs($this->teacher)->get('/postulaciones/'.$id);
        $response->assertOk()->assertSee('Habilitado')->assertDontSee('Revisión y habilitación')->assertDontSee('Expediente digital');
        $this->assertStringNotContainsString('Evidencia sintética cotejada', $response->getContent());
        $this->actingAs($this->teacher)->get('/documentos/'.DB::table('documents')->value('id'))->assertForbidden();
        $this->assertSame($before, DB::table('reviews')->orderBy('id')->get()->toJson());
        $this->assertDatabaseHas('applications', ['id' => $id, 'eligibility' => 'enabled']);
    }

    public function test_v02_authorized_profiles_keep_actual_review_details(): void
    {
        $id = $this->ready();
        foreach ([$this->student, $this->reviewer, $this->authority] as $user) {
            $this->actingAs($user)->get('/postulaciones/'.$id)->assertOk()
                ->assertSee('Cumple')->assertDontSee('Detalle de revisión restringido');
        }
    }

    public function test_v03_unreviewed_owner_still_sees_genuinely_pending_criteria(): void
    {
        $id = $this->app();
        $response = $this->actingAs($this->student)->get('/postulaciones/'.$id);
        $response->assertOk()->assertDontSee('Detalle de revisión restringido');
        $section = explode('<h2>Revisión y habilitación</h2>', $response->getContent(), 2)[1];
        $section = explode('</section>', $section, 2)[0];
        $this->assertStringContainsString('Pendiente', $section);
        $this->assertDatabaseCount('reviews', 0);
    }

    private function vacancyScenario(int $seats, int $candidates, int $renunciations): void
    {
        DB::table('offers')->where('id', $this->offer)->update(['seats' => $seats]);
        $ids = [];
        for ($i = 0; $i < $candidates; $i++) {
            $ids[] = $this->ready($i === 0 ? $this->student : User::factory()->create());
        }
        $this->evaluation();
        foreach ($ids as $i => $id) {
            $score = (string) (90 - $i * 10);
            $this->notes($id, ['Soporte' => $score, 'Redes' => $score, 'Software' => $score]);
        }
        $this->s->approve($this->authority, $this->offer, 'Acta sintética para verificar motivos de vacantes.');
        foreach (array_slice($ids, 0, $renunciations) as $id) {
            $owner = User::findOrFail(Selection::row('applications', $id)->user_id);
            $this->decide($this->participationRequest($id, 'renunciation', $owner));
        }
        if ($renunciations) {
            $this->s->approve($this->authority, $this->offer, 'Nueva publicación sintética que conserva vacantes retenidas.');
        }
    }

    private function assertVacancyPresentation(array $visible, array $absent): void
    {
        $tables = [
            'publications',
            'grades',
            'documents',
            'memberships',
            'applications',
            'reviews',
            'offers',
            'vacancy_events',
            'award_choices',
            'dossier_snapshots',
        ];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $response = $this->actingAs($this->authority)->get('/ofertas/'.$this->offer.'/resultados');
        $response->assertOk();
        foreach ($visible as $text) {
            $response->assertSee($text);
        }
        foreach ($absent as $text) {
            $response->assertDontSee($text);
        }
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson(), $table);
        }
    }

    public function test_v04_retained_vacancy_with_other_candidate_is_not_labeled_insufficiency(): void
    {
        [$a] = $this->published(true);
        $this->decide($this->participationRequest($a, 'renunciation'));
        $this->s->approve($this->authority, $this->offer, 'Acta sintética posterior a renuncia sin reemplazante.');
        $this->assertVacancyPresentation([
            '1 plaza sin cubrir',
            '1 plaza retenida por renuncia',
            '1 plaza · Méritos',
        ], ['insuficiencia de candidatos', 'sin candidato suficiente', '1 plazas']);
        $this->assertSame(1, count($this->s->ranking($this->offer)['rank']));
    }

    public function test_v05_insufficient_candidates_without_renunciation(): void
    {
        $this->vacancyScenario(2, 1, 0);
        $this->assertVacancyPresentation([
            '1 plaza sin cubrir',
            '1 plaza sin cubrir por insuficiencia de candidatos',
        ], ['retenida por renuncia', 'retenidas por renuncia', '1 plazas']);
    }

    public function test_v06_mixed_vacancies_keep_separate_causes_and_total(): void
    {
        $this->vacancyScenario(3, 2, 1);
        $this->assertVacancyPresentation([
            '2 plazas sin cubrir',
            '1 plaza retenida por renuncia',
            '1 plaza sin cubrir por insuficiencia de candidatos',
        ], ['2 plazas retenidas por renuncia', '2 plazas sin cubrir por insuficiencia']);
    }

    public function test_v07_plural_retained_and_insufficient_vacancies(): void
    {
        $this->vacancyScenario(4, 2, 2);
        $this->assertVacancyPresentation([
            '4 plazas sin cubrir',
            '2 plazas retenidas por renuncia',
            '2 plazas sin cubrir por insuficiencia de candidatos',
        ], ['1 plazas', '2 plaza retenida']);
    }

    public function test_v08_filled_offer_does_not_invent_a_vacancy_cause(): void
    {
        $this->vacancyScenario(1, 1, 0);
        $this->assertVacancyPresentation(['0 plazas sin cubrir', '1 plaza · Méritos'], [
            'retenida por renuncia',
            'retenidas por renuncia',
            'insuficiencia de candidatos',
            '1 plazas',
        ]);
    }

    public function test_p26_multiple_awards_are_visible_and_not_prohibited(): void
    {
        [$a] = $this->published();
        $call = $this->s->createCall($this->authority, [
            'title' => 'Otra convocatoria DEMO',
            'scope' => 'UAGRM',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'exam_at' => now()->addDays(2),
        ]);
        foreach ([[$this->reviewer, 'reviewer'], [$this->teacher, 'evaluator']] as [$u,$role]) {
            DB::table('memberships')->insert(['call_id' => $call, 'user_id' => $u->id, 'role' => $role]);
        }
        $offer = $this->s->saveOffer($this->authority, $call, [
            'category_id' => DB::table('categories')->value('id'),
            'title' => 'Otra plaza simultánea',
            'modality' => 'DEMO',
            'seats' => 1,
            'is_subject' => false,
            'documents' => ['cpd' => 'CPD'],
            'requirements' => ['activo' => 'Activo'],
            'weights' => ['Software' => '100'],
            'scheme' => 'DEMO_AREAS_100',
            'source' => 'Fixture DEMO',
        ]);
        $this->s->transition($this->authority, $call, 'published');
        $b = $this->s->apply($this->student, $offer);
        $this->s->upload($this->student, $b, 'cpd', UploadedFile::fake()->image('cpd.png'));
        $this->scanApplicantFixtures();
        $this->s->submit($this->student, $b);
        $this->s->review($this->reviewer, $b, ['doc:cpd' => 'meets', 'activo' => 'meets'], 'Evidencia sintética de otra convocatoria.');
        $this->s->eligibility($this->reviewer, $b, true, 'Revisión completa sintética de prueba.');
        $this->s->merit($this->reviewer, $b, '20', 'CPD-DEMO-OTRA');
        $this->s->assign($this->authority, $offer, $this->teacher->id, 'Software');
        $this->s->transition($this->authority, $call, 'closed');
        $this->s->transition($this->authority, $call, 'evaluation');
        $this->s->grade($this->teacher, $b, DB::table('assignments')->where('offer_id', $offer)->value('id'), '80');
        $this->s->calculate($this->authority, $b);
        $this->s->approve($this->authority, $offer, 'Acta sintética de segunda adjudicación.');
        $this->actingAs($this->student)->get('/adjudicaciones')->assertOk()->assertSee('Múltiples adjudicaciones detectadas')->assertSee('Otra plaza simultánea');
        foreach ([$a, $b] as $id) {
            $row = Selection::row('applications', $id);
            app(Participation::class)->choose($this->student, $id, Participation::award($row)->id, 'accept', 0, 'Manifestación simultánea sin regla inventada.', 'ACTA-SINTETICA-MULTIPLE');
        }
        $this->assertDatabaseCount('award_choices', 2);
        $this->assertSame(2, DB::table('applications')->where('selection', 'selected')->count());
        DB::table('memberships')->where('user_id', $this->authority->id)->where('call_id', $call)->update(['revoked_at' => now()]);
        $this->actingAs($this->authority)->get('/adjudicaciones')->assertOk()->assertDontSee('Otra plaza simultánea');
    }
}
