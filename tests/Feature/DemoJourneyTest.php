<?php

namespace Tests\Feature;

use App\Domain\DemoJourney;
use App\Domain\DocumentScanner;
use App\Domain\Selection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class DemoJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->detectEnvironment(fn () => 'local');
        Storage::fake('local');
        foreach (['autoridad' => 'authority', 'revisor' => 'staff', 'docente' => 'staff', 'estudiante' => 'student', 'bruno' => 'student'] as $name => $role) {
            User::factory()->create(['email' => $name.'@ficct.test', 'role' => $role]);
        }
        DB::table('categories')->insert(['name' => 'Sala de cómputo', 'regime' => 'docencia', 'active' => true]);
    }

    public function test_existing_incomplete_journey_is_not_reported_as_success(): void
    {
        $id = app(Selection::class)->createCall(User::where('email', 'autoridad@ficct.test')->firstOrFail(), [
            'title' => 'Recorrido probado · Evaluación y desempate DEMO',
            'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'exam_at' => now()->addDays(2),
            'scope' => 'UAGRM', 'source' => 'Escenario de regresión incompleto, no es concurso real.',
        ]);
        try {
            app(DemoJourney::class)->run();
            $this->fail('Un recorrido sin oferta/publicación no puede declarar ejecución completa.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('incompleto', implode(' ', array_merge(...array_values($e->errors()))));
        }
        $this->assertSame(1, DB::table('calls')->count());
        $this->assertSame('draft', Selection::row('calls', $id)->status);
        $this->assertSame(0, DB::table('publications')->count());
    }

    public function test_failed_scan_retry_preserves_partial_work_and_still_fails(): void
    {
        app()->instance(DocumentScanner::class, new class implements DocumentScanner {
            public function scan(string $path): array
            {
                return ['state' => 'error', 'engine' => 'TEST-FAILURE', 'reason' => 'unavailable_fixture'];
            }
        });
        try {
            app(DemoJourney::class)->run();
            $this->fail('La falta de análisis válido debe bloquear la revisión.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $before = ['calls' => DB::table('calls')->count(), 'applications' => DB::table('applications')->count(), 'documents' => DB::table('documents')->count(), 'scans' => DB::table('institutional_scan_jobs')->count()];
        $this->assertSame(1, $before['calls']);
        try {
            app(DemoJourney::class)->run();
            $this->fail('El reintento no debe convertir el trabajo parcial en éxito.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table === 'scans' ? 'institutional_scan_jobs' : $table)->count());
        }
        $this->assertSame(0, DB::table('publications')->count());
    }

    public function test_complete_journey_is_idempotent_and_preserves_its_publication(): void
    {
        $this->useApplicantFixtureScanner();
        $id = app(DemoJourney::class)->run();
        $this->assertSame(1, DB::table('publications')->count());
        $this->assertSame(1, DB::table('tie_exams')->where('status', 'resolved')->count());
        $this->assertSame(2, DB::table('applications')->whereNotNull('final')->count());
        $publication = DB::table('publications')->first();
        $audit = DB::table('audit_events')->count();
        $this->assertSame($id, app(DemoJourney::class)->run());
        $this->assertSame($publication->snapshot, DB::table('publications')->value('snapshot'));
        $this->assertSame($audit, DB::table('audit_events')->count());
    }

    public function test_both_demo_commands_reject_institutional_mode_before_creating_data(): void
    {
        config(['portal.experience' => 'institutional']);
        foreach ([DemoJourney::class, \App\Domain\DocumentaryJourney::class] as $journey) {
            try {
                app($journey)->run();
                $this->fail('Un comando DEMO no debe ejecutarse en una instalación institucional.');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
        $this->assertSame(0, DB::table('calls')->count());
    }
}
