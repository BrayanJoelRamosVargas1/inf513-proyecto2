<?php

use App\Domain\DemoJourney;
use App\Domain\DocumentaryJourney;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('ficct:telemetry-prune {--apply : Aplicar eliminación únicamente a telemetría vencida}', function () {
    $days = config('portal.telemetry_retention_days', 90);
    $query = DB::table('access_events')->where('created_at', '<', now()->subDays($days));
    $count = (clone $query)->count();
    if ($this->option('apply')) {
        $deleted = $query->delete();
        $this->info("Telemetría anterior a {$days} días eliminada: {$deleted} eventos. No se modificó auditoría académica.");
    } else {
        $this->info("Simulación: {$count} eventos anteriores a {$days} días. No se eliminó nada. Use --apply para aplicar.");
    }
})->purpose('Revisar y aplicar retención DEMO de telemetría; no toca expedientes, auditoría ni contadores');

Artisan::command('ficct:demo-documental', function () {
    $this->info(json_encode(app(DocumentaryJourney::class)->run(), JSON_UNESCAPED_UNICODE));
})->purpose('Agregar prácticas con PDF A4 sin reemplazar datos existentes');

Artisan::command('ficct:demo-recorrido', function () {
    $id = app(DemoJourney::class)->run();
    $this->info('Recorrido sintético ejecutado. Convocatoria #'.$id.'; se mantiene separada la convocatoria abierta.');
})->purpose('Ejecutar flujo DEMO completo con documentos ficticios y examen de desempate');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
