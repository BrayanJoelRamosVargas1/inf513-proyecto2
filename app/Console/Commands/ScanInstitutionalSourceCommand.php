<?php

namespace App\Console\Commands;

use App\Domain\DocumentProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ScanInstitutionalSourceCommand extends Command
{
    protected $signature = 'ficct:scan-institutional-source {source_id}';

    protected $description = 'Analizar una fuente institucional privada; sin opciones de aprobación manual';

    public function handle(): int
    {
        $id = filter_var($this->argument('source_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || ! DB::table('institutional_sources')->where('id', $id)->exists()) {
            $this->error('Fuente inexistente o identificador inválido.');

            return 1;
        }
        $processor = app(DocumentProcessor::class);
        $task = $processor->enqueue($id);
        $processor->recover(true);
        $result = $processor->runOne($task);
        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE));

        return $result['state'] === 'clean' ? 0 : 1;

        return self::SUCCESS;
    }
}
