<?php

namespace App\Console\Commands;

use App\Domain\DemoJourney;
use Illuminate\Console\Command;

final class DemoRecorridoCommand extends Command
{
    protected $signature = 'ficct:demo-recorrido';

    protected $description = 'Ejecutar flujo DEMO completo con documentos ficticios y examen de desempate';

    public function handle(): int
    {
        $id = app(DemoJourney::class)->run();
        $this->info('Recorrido sintético ejecutado. Convocatoria #'.$id.'; se mantiene separada la convocatoria abierta.');

        return self::SUCCESS;
    }
}
