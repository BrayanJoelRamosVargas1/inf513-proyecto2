<?php

namespace App\Console\Commands;

use App\Domain\DocumentProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ScanEnqueueDocumentCommand extends Command
{
    protected $signature = 'ficct:scan-enqueue-document {document_id}';

    protected $description = 'Encolar análisis de una versión documental de postulante; sin liberación manual';

    public function handle(): int
    {
        $id = filter_var($this->argument('document_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || ! DB::table('documents')->where('id', $id)->exists()) {
            return 1;
        }
        $this->line(json_encode(['task_id' => app(DocumentProcessor::class)->enqueue($id, 'applicant')]));

        return 0;

        return self::SUCCESS;
    }
}
