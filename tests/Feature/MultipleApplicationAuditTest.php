<?php

namespace Tests\Feature;

use App\Domain\Rectifications;
use App\Domain\Selection;
use App\Models\User;
use App\ReadModels\CallConsolidation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class MultipleApplicationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_offers_keep_documents_scores_rankings_and_historical_publications_independent(): void
    {
        Storage::fake('local');
        $service = app(Selection::class);
        $authority = User::factory()->create(['role' => 'authority']);
        $reviewer = User::factory()->create(['role' => 'staff']);
        $evaluator = User::factory()->create(['role' => 'staff']);
        $student = User::factory()->create();
        $call = $service->createCall($authority, [
            'title' => 'Auditoría de independencia',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'exam_at' => now()->addDays(2),
            'scope' => 'UAGRM',
            'source' => 'Datos exclusivamente sintéticos',
        ]);
        foreach ([[$reviewer, 'reviewer'], [$evaluator, 'evaluator']] as [$user, $role]) {
            DB::table('memberships')->insert(['user_id' => $user->id, 'call_id' => $call, 'role' => $role]);
        }
        $category = DB::table('categories')->insertGetId(['name' => 'Auditoría sintética', 'regime' => 'laboratorio', 'active' => true]);
        // Resultados esperados calculados fuera del motor: 35.25+81*0.60,
        // 20.10+75*0.60 y 40.00+64*0.60, respectivamente.
        $cases = [
            [
                'weights' => ['Soporte' => '50', 'Redes' => '30', 'Software' => '20'],
                'notes' => ['Soporte' => '80', 'Redes' => '90', 'Software' => '70'],
                'merit' => '35.25',
                'exam' => '48.60',
                'final' => '83.85',
                'kind' => 'cpd',
            ],
            [
                'weights' => ['Software' => '100'],
                'notes' => ['Software' => '75'],
                'merit' => '20.10',
                'exam' => '45.00',
                'final' => '65.10',
                'kind' => 'ci',
            ],
            [
                'weights' => ['Soporte' => '25', 'Electronica' => '75'],
                'notes' => ['Soporte' => '100', 'Electronica' => '52'],
                'merit' => '40.00',
                'exam' => '38.40',
                'final' => '78.40',
                'kind' => 'academico',
            ],
        ];
        foreach ($cases as $i => &$case) {
            $case['offer'] = $service->saveOffer($authority, $call, [
                'category_id' => $category,
                'title' => 'Oferta '.($i + 1),
                'seats' => 1,
                'is_subject' => false,
                'modality' => 'Presencial',
                'documents' => ['cpd' => 'CPD sintético', $case['kind'] => 'Respaldo propio'],
                'requirements' => ['activo' => 'Estudiante activo'],
                'weights' => $case['weights'],
                'scheme' => 'DEMO_AREAS_100',
                'source' => 'Ejercicio técnico sin rúbrica institucional',
            ]);
            foreach (array_keys($case['weights']) as $area) {
                $service->assign($authority, $case['offer'], $evaluator->id, $area);
            }
        }
        unset($case);
        $service->transition($authority, $call, 'published');
        foreach ($cases as $i => &$case) {
            $id = $case['application'] = $service->apply($student, $case['offer']);
            $service->upload($student, $id, $case['kind'], UploadedFile::fake()->image('original-'.($i + 1).'.png', 120 + $i, 160));
            if ($case['kind'] !== 'cpd') {
                $service->upload($student, $id, 'cpd', UploadedFile::fake()->image('cpd-'.($i + 1).'.png', 200, 300));
            }
            $this->scanApplicantFixtures();
            $service->submit($student, $id);
            $service->review($reviewer, $id, [
                'doc:cpd' => 'meets',
                'doc:'.$case['kind'] => 'meets',
                'activo' => 'meets',
            ], 'Documento cotejado en escenario sintético');
            $service->eligibility($reviewer, $id, true, 'Requisitos sintéticos comprobados');
            $service->merit($reviewer, $id, $case['merit'], 'Respaldo CPD sintético independiente');
        }
        unset($case);
        $service->transition($authority, $call, 'closed');
        $service->transition($authority, $call, 'evaluation');
        foreach ($cases as $case) {
            foreach (DB::table('assignments')->where('offer_id', $case['offer'])->get() as $assignment) {
                $service->grade($evaluator, $case['application'], $assignment->id, $case['notes'][$assignment->area]);
            }
            $service->calculate($authority, $case['application']);
            $this->assertDatabaseHas('applications', [
                'id' => $case['application'],
                'merit' => $case['merit'],
                'exam' => $case['exam'],
                'final' => $case['final'],
            ]);
            $ranking = $service->ranking($case['offer']);
            $this->assertSame([$case['application']], array_column($ranking['rank'], 'id'));
            $this->assertSame([], $ranking['tied']);
            $this->assertSame(0, $ranking['vacancies']);
            $service->approve($authority, $case['offer'], 'Acta sintética independiente de publicación');
            $document = DB::table('documents')->where('application_id', $case['application'])->where('kind', $case['kind'])->sole();
            $this->actingAs($authority)->getJson('/api/v1/applications/'.$case['application'])->assertOk()->assertJsonPath('offer.id', $case['offer'])->assertJsonPath('application.final', $case['final']);
            $this->actingAs($student)->get('/api/v1/documents/'.$document->id.'/download')->assertOk()->assertContent(Storage::disk('local')->get($document->path));
        }
        $beforeApps = DB::table('applications')->orderBy('id')->get()->keyBy('id');
        $beforeDocs = DB::table('documents')->orderBy('id')->get();
        $beforePublications = DB::table('publications')->orderBy('id')->get();
        $first = $cases[0];
        $grade = DB::table('grades')->join('assignments', 'assignments.id', '=', 'grades.assignment_id')->where('grades.application_id', $first['application'])->where('assignments.area', 'Soporte')->select('grades.*')->sole();
        $rectifications = app(Rectifications::class);
        $request = $rectifications->request($evaluator, $first['application'], $grade->id, '90', 'Corrección sintética de una única oferta', 'Acta sintética de respaldo independiente');
        $rectifications->decide($authority, $request, true, 'Aprobación por responsable distinto al solicitante');
        $this->assertDatabaseHas('applications', [
            'id' => $first['application'],
            'exam' => '51.60',
            'final' => '86.85',
            'selection' => 'under_review',
        ]);
        foreach (array_slice($cases, 1) as $case) {
            $this->assertEquals($beforeApps[$case['application']], DB::table('applications')->find($case['application']));
        }
        $this->assertEquals($beforeDocs, DB::table('documents')->orderBy('id')->get());
        $this->assertEquals($beforePublications, DB::table('publications')->orderBy('id')->get());
        $consolidated = app(CallConsolidation::class)->read($authority, $call);
        $this->assertSame(3, $consolidated['totals']['applications']);
        foreach ($consolidated['groups'] as $i => $group) {
            $this->assertSame($cases[$i]['application'], $group['rows'][0]['id']);
            $this->assertSame($i === 0 ? '86.85' : $cases[$i]['final'], $group['rows'][0]['final']);
            $this->assertCount(count($cases[$i]['weights']), $group['rows'][0]['areas']);
            $this->assertContains($cases[$i]['kind'], array_column($group['rows'][0]['documents'], 'kind'));
        }
    }
}
