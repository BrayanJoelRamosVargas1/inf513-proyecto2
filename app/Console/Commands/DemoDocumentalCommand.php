<?php

namespace App\Console\Commands;

use App\Domain\DocumentaryJourney;
use Illuminate\Console\Command;

final class DemoDocumentalCommand extends Command
{
    protected $signature = 'ficct:demo-documental';

    protected $description = 'Agregar prácticas con PDF A4 sin reemplazar datos existentes';

    public function handle(): int
    {
        $this->info(json_encode(app(DocumentaryJourney::class)->run(), JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
