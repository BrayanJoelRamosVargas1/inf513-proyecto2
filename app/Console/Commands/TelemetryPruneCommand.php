<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class TelemetryPruneCommand extends Command
{
    protected $signature = 'ficct:telemetry-prune {--apply : Aplicar eliminación únicamente a telemetría vencida}';

    protected $description = 'Revisar y aplicar retención DEMO de telemetría; no toca expedientes, auditoría ni contadores';

    public function handle(): int
    {
        $days = config('portal.telemetry_retention_days', 90);
        $query = DB::table('access_events')->where('created_at', '<', now()->subDays($days));
        $count = (clone $query)->count();
        if ($this->option('apply')) {
            $deleted = $query->delete();
            $this->info("Telemetría anterior a {$days} días eliminada: {$deleted} eventos. No se modificó auditoría académica.");
        } else {
            $this->info("Simulación: {$count} eventos anteriores a {$days} días. No se eliminó nada. Use --apply para aplicar.");
        }

        return self::SUCCESS;
    }
}
