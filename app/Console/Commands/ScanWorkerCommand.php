<?php

namespace App\Console\Commands;

use App\Domain\DocumentProcessor;
use Illuminate\Console\Command;

final class ScanWorkerCommand extends Command
{
    protected $signature = 'ficct:scan-worker {--once} {--max-jobs=20} {--max-seconds=300}';

    protected $description = 'Procesar tareas con reserva temporal y recuperación, sin aprobar resultados manualmente';

    public function handle(): int
    {
        $jobs = filter_var($this->option('max-jobs'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        $seconds = filter_var($this->option('max-seconds'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 3600]]);
        if ($jobs === false || $seconds === false) {
            $this->error('Límites de trabajador inválidos.');

            return 1;
        }
        $processor = app(DocumentProcessor::class);
        $start = microtime(true);
        $processor->cleanup(true);
        for ($i = 0; $i < $jobs && microtime(true) - $start < $seconds; $i++) {
            $processor->recover(true);
            $processor->recoverUnmanaged(true);
            $result = $processor->runOne();
            $this->line(json_encode($result));
            if ($this->option('once') || $result['state'] === 'idle') {
                break;
            }
        }

        return 0;

        return self::SUCCESS;
    }
}
