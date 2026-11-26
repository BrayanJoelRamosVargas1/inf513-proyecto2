<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class TelemetryPruneCommand extends Command
{
    protected $signature = 'ficct:telemetry-prune {--apply : Aplicar eliminación únicamente a telemetría vencida}';

    protected $description = 'Revisar y aplicar la retención de la telemetría de navegación; no toca expedientes, auditoría ni contadores';

    public function handle(): int
    {
        $days = config('portal.telemetry_retention_days', 90);
        $cutoff = now()->subDays($days);
        $query = DB::table('access_events')->where('created_at', '<', $cutoff);
        $count = (clone $query)->count();
        if ($this->option('apply')) {
            $deleted = $query->delete();
            // Los recibos de visitas solo evitan contar dos veces una misma navegación: pasada la retención no sirven.
            DB::table('visit_receipts')->where('created_at', '<', $cutoff)->delete();
            DB::table('public_visit_receipts')->where('created_at', '<', $cutoff)->delete();
            $this->info("Telemetría anterior a {$days} días eliminada: {$deleted} eventos. No se modificó auditoría académica.");
        } else {
            $this->info("Simulación: {$count} eventos anteriores a {$days} días. No se eliminó nada. Use --apply para aplicar.");
        }

        return self::SUCCESS;
    }
}
