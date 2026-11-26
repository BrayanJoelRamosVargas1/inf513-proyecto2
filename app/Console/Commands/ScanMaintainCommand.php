<?php

namespace App\Console\Commands;

use App\Domain\DocumentProcessor;
use Illuminate\Console\Command;

final class ScanMaintainCommand extends Command
{
    protected $signature = 'ficct:scan-maintain {--apply}';

    protected $description = 'Inspeccionar recuperación y temporales; requiere --apply para cambiar datos';

    public function handle(): int
    {
        $p = app(DocumentProcessor::class);
        $apply = (bool) $this->option('apply');
        $this->line(json_encode(['apply' => $apply, 'leases' => $p->recover($apply),
            'unmanaged' => $p->recoverUnmanaged($apply), 'temporaries' => $p->cleanup($apply)]));

        return 0;

        return self::SUCCESS;
    }
}
