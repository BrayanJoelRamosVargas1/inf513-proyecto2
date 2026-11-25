<?php

namespace App\Console\Commands;

use App\Domain\ScanRenewal;
use Illuminate\Console\Command;

final class ScanRenewCommand extends Command
{
    protected $signature = 'ficct:scan-renew {--apply : Encolar los análisis; sin esta opción solo simula} {--margin-hours=6 : Horas previas al vencimiento en que se renueva}';

    protected $description = 'Renovar análisis vencidos o próximos a vencer de documentos vigentes; simulación por defecto, nunca libera archivos';

    public function handle(): int
    {
        $margin = filter_var($this->option('margin-hours'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 23]]);
        if ($margin === false) {
            $this->error('Margen inválido: use un entero entre 0 y 23 horas.');

            return 1;
        }
        $this->line(json_encode(app(ScanRenewal::class)->run((bool) $this->option('apply'), $margin), JSON_UNESCAPED_UNICODE));

        return 0;

        return self::SUCCESS;
    }
}
