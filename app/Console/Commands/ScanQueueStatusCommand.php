<?php

namespace App\Console\Commands;

use App\Domain\DocumentProcessor;
use Illuminate\Console\Command;

final class ScanQueueStatusCommand extends Command
{
    protected $signature = 'ficct:scan-queue-status {--check : Devolver código 1 si hay alertas operativas}';

    protected $description = 'Consultar estado agregado del procesador sin revelar expedientes';

    public function handle(): int
    {
        $status = app(DocumentProcessor::class)->queueStatus();
        $this->line(json_encode($status));

        return $this->option('check') && $status['health'] !== 'ok' ? 1 : 0;

        return self::SUCCESS;
    }
}
