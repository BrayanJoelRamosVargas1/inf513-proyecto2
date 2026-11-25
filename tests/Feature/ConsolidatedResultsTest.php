<?php

namespace Tests\Feature;

use App\Domain\Participation;
use App\Domain\Rectifications;
use App\Domain\Selection;
use App\Models\User;
use App\ReadModels\CallConsolidation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

final class ConsolidatedResultsTest extends TestCase
{
    use RefreshDatabase;

    private Selection $s;

    private User $authority;

    private User $reviewer;

    private User $teacher;

    private User $student;

    private int $call;

    private int $offer;

    private int $empty;

    private int $application;

    private int $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->s = app(Selection::class);
        $this->authority = User::factory()->create(['role' => 'authority']);
        $this->reviewer = User::factory()->create(['role' => 'staff']);
        $this->teacher = User::factory()->create(['role' => 'staff']);
        $this->student = User::factory()->create(['name' => '=SUM(1,2)']);
        $this->call = $this->s->createCall($this->authority, ['title' => 'Consolidado sintético', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'exam_at' => now()->addDays(2), 'scope' => 'UAGRM']);
        foreach ([[$this->reviewer, 'reviewer'], [$this->teacher, 'evaluator']] as [$u, $role]) {
            DB::table('memberships')->insert(['call_id' => $this->call, 'user_id' => $u->id, 'role' => $role]);
        }
        $category = DB::table('categories')->insertGetId(['name' => 'Tipo sintético', 'regime' => 'docencia', 'active' => true]);
        $offer = ['category_id' => $category, 'seats' => 1, 'is_subject' => false, 'modality' => 'Presencial', 'documents' => ['cpd' => 'CPD sintético'], 'requirements' => ['activo' => 'Activo DEMO'], 'weights' => ['Soporte' => '100'], 'scheme' => 'DEMO_AREAS_100', 'source' => 'Fixture sintético sin validez institucional'];
        $this->offer = $this->s->saveOffer($this->authority, $this->call, $offer + ['title' => 'Oferta con candidatos']);
        $this->empty = $this->s->saveOffer($this->authority, $this->call, $offer + ['title' => 'Oferta sin postulaciones']);
        $this->s->transition($this->authority, $this->call, 'published');
        $this->application = $this->ready($this->student);
        $this->s->assign($this->authority, $this->offer, $this->teacher->id, 'Soporte');
        $this->assignment = DB::table('assignments')->where('offer_id', $this->offer)->value('id');
    }

    private function ready(User $u): int
    {
        $id = $this->s->apply($u, $this->offer);
        $this->s->upload($u, $id, 'cpd', UploadedFile::fake()->image('cpd.png'));
        $this->scanApplicantFixtures();
        $this->s->submit($u, $id);
        $this->s->review($this->reviewer, $id, ['doc:cpd' => 'meets', 'activo' => 'meets'], 'Revisión sintética completa.');
        $this->s->eligibility($this->reviewer, $id, true, 'Revisión de todos los criterios.');
        $this->s->merit($this->reviewer, $id, '20.10', 'CPD-SINTETICO');

        return $id;
    }

    private function evaluate(): void
    {
        $this->s->transition($this->authority, $this->call, 'closed');
        $this->s->transition($this->authority, $this->call, 'evaluation');
    }

    private function confirmScore(int $id, string $score = '80'): void
    {
        $this->s->grade($this->teacher, $id, $this->assignment, $score);
        $this->s->calculate($this->authority, $id);
    }

    private function read(): array
    {
        return app(CallConsolidation::class)->read($this->authority, $this->call);
    }

    public function test_c01_authority_sees_all_offers_and_pending_results_without_zero_fabrication(): void
    {
        $d = $this->read();
        $this->assertCount(2, $d['groups']);
        $this->assertNull($d['groups'][0]['rows'][0]['final']);
        $this->assertNull($d['groups'][0]['covered']);
        $this->assertNull($d['groups'][1]['vacant']);
        $this->actingAs($this->authority)->get('/convocatorias/'.$this->call.'/consolidado')->assertOk()->assertSee('Oferta sin postulaciones')->assertSee('cobertura pendiente', false);
    }

    public function test_c02_authority_scope_and_effective_permissions_protect_page_and_exports(): void
    {
        foreach ([$this->student, $this->reviewer, $this->teacher, User::factory()->create(['role' => 'admin']), User::factory()->create(['role' => 'authority'])] as $u) {
            foreach (['', '/pdf', '/xlsx'] as $suffix) {
                $this->actingAs($u)->get('/convocatorias/'.$this->call.'/consolidado'.$suffix)->assertForbidden();
            }
        }
        DB::table('memberships')->where('user_id', $this->authority->id)->update(['expires_at' => now()->subMinute()]);
        $this->actingAs($this->authority)->get('/convocatorias/'.$this->call.'/consolidado')->assertForbidden();
    }

    public function test_c03_equal_scores_keep_equal_positions_and_do_not_choose_a_winner(): void
    {
        $second = $this->ready(User::factory()->create());
        $this->evaluate();
        $this->confirmScore($this->application);
        $this->confirmScore($second);
        $rows = $this->read()['groups'][0]['rows'];
        $this->assertSame([1, 1], array_column($rows, 'position'));
        $this->assertSame([true, true], array_column($rows, 'tie_pending'));
        $this->assertSame(['68.10', '68.10'], array_column($rows, 'final'));
        $this->assertDatabaseCount('publications', 0);
    }

    public function test_c04_draft_grade_hidden_and_confirmed_zero_preserved(): void
    {
        $this->evaluate();
        $this->s->grade($this->teacher, $this->application, $this->assignment, '99', false);
        $this->assertNull($this->read()['groups'][0]['rows'][0]['areas'][0]['score']);
        $this->confirmScore($this->application, '0');
        $row = $this->read()['groups'][0]['rows'][0];
        $this->assertSame('0.00', $row['areas'][0]['score']);
        $this->assertSame('0.00', $row['exam']);
        $this->assertSame('20.10', $row['final']);
    }

    public function test_c05_corrected_results_do_not_replace_the_last_publication(): void
    {
        $this->evaluate();
        $this->confirmScore($this->application);
        $this->s->approve($this->authority, $this->offer, 'Publicación DEMO de prueba inicial.');
        $old = DB::table('publications')->first();
        $r = app(Rectifications::class);
        $request = $r->request($this->reviewer, $this->application, null, '25.00', 'Rectificación de mérito sintética.', 'Referencia sintética verificable.');
        $r->decide($this->authority, $request, true, 'Decisión sintética de otra autoridad.');
        $g = $this->read()['groups'][0];
        $this->assertTrue($g['offer']->needs_publication_review);
        $this->assertSame('73.00', $g['rows'][0]['final']);
        $this->assertTrue($g['rows'][0]['award_active']);
        $this->assertSame($old->snapshot, $g['publication']->snapshot);
        $this->assertDatabaseCount('publications', 1);
    }

    public function test_c06_renunciation_separates_published_selection_from_active_award(): void
    {
        $this->evaluate();
        $this->confirmScore($this->application);
        $this->s->approve($this->authority, $this->offer, 'Publicación de prueba para renuncia.');
        $p = app(Participation::class);
        $id = $p->request($this->student, $this->application, 'renunciation', 'Renuncia sintética de la prueba.', 'Evidencia sintética de renuncia.');
        $p->decide($this->authority, $id, true, 'Decisión sintética de renuncia.');
        $g = $this->read()['groups'][0];
        $this->assertTrue($g['rows'][0]['published_selected']);
        $this->assertFalse($g['rows'][0]['award_active']);
        $this->assertSame(0, $g['covered']);
        $this->assertSame(1, $g['vacant']);
        $this->assertSame(1, $g['offer']->held_vacancies);
    }

    public function test_c07_excel_has_results_areas_documents_and_empty_offers_with_safe_text(): void
    {
        $this->evaluate();
        $this->confirmScore($this->application);
        $response = $this->actingAs($this->authority)->get('/convocatorias/'.$this->call.'/consolidado/xlsx')->assertOk();
        $file = tempnam(sys_get_temp_dir(), 'ficct-xlsx-');
        try {
            file_put_contents($file, $response->streamedContent());
            $book = IOFactory::load($file);
            $this->assertSame(['Resultados', 'Áreas', 'Documentación', 'Plazas'], $book->getSheetNames());
            $cell = $book->getSheetByName('Resultados')->getCell('B4');
            $this->assertSame('s', $cell->getDataType());
            $this->assertSame('=SUM(1,2)', $cell->getValue());
            $this->assertSame('48.00', $book->getSheetByName('Resultados')->getCell('G4')->getValue());
            $this->assertSame('Oferta sin postulaciones', $book->getSheetByName('Plazas')->getCell('A5')->getValue());
            $this->assertSame('100.00', $book->getSheetByName('Áreas')->getCell('D4')->getValue());
            $this->assertSame('Sí', $book->getSheetByName('Documentación')->getCell('D4')->getValue());
        } finally {
            @unlink($file);
        }
        $this->assertDatabaseHas('audit_events', ['action' => 'consolidation.exported', 'call_id' => $this->call]);
    }

    public function test_c08_pdf_is_real_and_queries_preserve_business_history(): void
    {
        $before = DB::table('documents')->get()->toJson();
        $response = $this->actingAs($this->authority)->get('/convocatorias/'.$this->call.'/consolidado/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertSame($before, DB::table('documents')->get()->toJson());
        $this->assertDatabaseCount('publications', 0);
        $this->assertDatabaseCount('score_versions', 1);
    }

    public function test_c09_unsent_amendment_documents_do_not_replace_current_dossier(): void
    {
        // Abrir subsanación requiere observación y no habilitación: otro expediente.
        $u = User::factory()->create();
        $id = $this->s->apply($u, $this->offer);
        $this->s->upload($u, $id, 'cpd', UploadedFile::fake()->image('original.png'));
        $this->scanApplicantFixtures();
        $this->s->submit($u, $id);
        $this->s->review($this->reviewer, $id, ['doc:cpd' => 'observed', 'activo' => 'meets'], 'Observación sintética del documento.');
        $p = app(Participation::class);
        $window = $p->open($this->reviewer, $id, ['cpd'], now()->addHour()->toIso8601String(), 'Subsanación sintética autorizada.', 'Referencia de observación sintética.');
        $p->upload($u, $window, 'cpd', UploadedFile::fake()->image('nuevo.png'));
        $this->scanApplicantFixtures();
        $row = collect($this->read()['groups'][0]['rows'])->firstWhere('id', $id);
        $this->assertSame(1, $row['documents'][0]['version']);
        $p->submit($u, $window);
        $row = collect($this->read()['groups'][0]['rows'])->firstWhere('id', $id);
        $this->assertSame(2, $row['documents'][0]['version']);
        $this->assertSame('pending', $row['documents'][0]['review']);
    }
}
