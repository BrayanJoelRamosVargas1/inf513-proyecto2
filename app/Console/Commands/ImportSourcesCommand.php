<?php

namespace App\Console\Commands;

use App\Domain\InstitutionalCatalogue;
use Illuminate\Console\Command;

final class ImportSourcesCommand extends Command
{
    protected $signature = 'ficct:import-sources {--first= : ID histórico de 01/2026} {--second= : ID histórico de 02/2026}';

    protected $description = 'Importar fuentes 2026 verificadas sin reabrir procesos ni inventar plazas';

    public function handle(): int
    {
        if (! ctype_digit((string) $this->option('first')) || ! ctype_digit((string) $this->option('second'))) {
            $this->error('Indique ambos identificadores históricos explícitamente.');

            return 1;
        }
        $this->line(json_encode(app(InstitutionalCatalogue::class)->import((int) $this->option('first'), (int) $this->option('second')), JSON_UNESCAPED_UNICODE));

        return 0;

        return self::SUCCESS;
    }
}
