<?php

namespace Tests;

use App\Domain\DocumentProcessor;
use App\Domain\DocumentScanner;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        if (config('database.connections.'.config('database.default').'.database') !== 'ficct_test') {
            throw new \RuntimeException('Las pruebas requieren una base separada llamada ficct_test. Use python scripts/test.py');
        }

        return $app;
    }

    /** Historical workflow fixtures explicitly run a synthetic scanner before academic actions. */
    protected function scanApplicantFixtures(): void
    {
        $original = app(DocumentScanner::class);
        $this->useApplicantFixtureScanner();
        /* fixture scanner installed explicitly */
        try {
            foreach (DB::table('institutional_scan_tasks')->whereNotNull('document_id')->where('state', 'queued')->get() as $task) {
                app(DocumentProcessor::class)->runOne($task->id);
            }
        } finally {
            app()->instance(DocumentScanner::class, $original);
        }
    }

    protected function useApplicantFixtureScanner(): void
    {
        app()->instance(DocumentScanner::class, new class implements DocumentScanner
        {
            public function scan(string $path): array
            {
                return ['state' => 'clean', 'engine' => 'TEST-HISTORICAL-FIXTURE', 'reason' => 'synthetic_fixture'];
            }
        });

    }
}
