<?php

namespace App\Console\Commands;

use App\Domain\DocumentProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ScanEnqueueCommand extends Command
{
    protected $signature = 'ficct:scan-enqueue {source_id}';

    protected $description = 'Conservar una solicitud de análisis pendiente en PostgreSQL';

    public function handle(): int
    {
        $id = filter_var($this->argument('source_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || ! DB::table('institutional_sources')->where('id', $id)->exists()) {
            $this->error('Fuente inexistente o identificador inválido.');

            return 1;
        }
        $this->line(json_encode(['task_id' => app(DocumentProcessor::class)->enqueue($id), 'state' => 'queued']));

        return 0;

        return self::SUCCESS;
    }
}
