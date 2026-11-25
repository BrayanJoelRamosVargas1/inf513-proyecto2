<?php

use App\Domain\DemoJourney;
use App\Domain\DocumentaryJourney;
use App\Domain\DocumentProcessor;
use App\Domain\ScanRenewal;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('ficct:scan-institutional-source {source_id}', function () {
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
})->purpose('Analizar una fuente institucional privada; sin opciones de aprobación manual');

Artisan::command('ficct:scan-enqueue {source_id}', function () {
    $id = filter_var($this->argument('source_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false || ! DB::table('institutional_sources')->where('id', $id)->exists()) {
        $this->error('Fuente inexistente o identificador inválido.');

        return 1;
    }
    $this->line(json_encode(['task_id' => app(DocumentProcessor::class)->enqueue($id), 'state' => 'queued']));

    return 0;
})->purpose('Conservar una solicitud de análisis pendiente en PostgreSQL');

Artisan::command('ficct:scan-enqueue-document {document_id}', function () {
    $id = filter_var($this->argument('document_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false || ! DB::table('documents')->where('id', $id)->exists()) {
        return 1;
    }
    $this->line(json_encode(['task_id' => app(DocumentProcessor::class)->enqueue($id, 'applicant')]));

    return 0;
})->purpose('Encolar análisis de una versión documental de postulante; sin liberación manual');

Artisan::command('ficct:scan-worker {--once} {--max-jobs=20} {--max-seconds=300}', function () {
    $jobs = filter_var($this->option('max-jobs'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
    $seconds = filter_var($this->option('max-seconds'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 3600]]);
    if ($jobs === false || $seconds === false) {
        $this->error('Límites de trabajador inválidos.');

        return 1;
    }
    $processor = app(DocumentProcessor::class);
    $start = microtime(true);
    $processor->cleanup(true);
    for ($i = 0; $i < $jobs && microtime(true) - $start < $seconds; $i++) {
        $processor->recover(true);
        $processor->recoverUnmanaged(true);
        $result = $processor->runOne();
        $this->line(json_encode($result));
        if ($this->option('once') || $result['state'] === 'idle') {
            break;
        }
    }

    return 0;
})->purpose('Procesar tareas con reserva temporal y recuperación, sin aprobar resultados manualmente');

Artisan::command('ficct:scan-maintain {--apply}', function () {
    $p = app(DocumentProcessor::class);
    $apply = (bool) $this->option('apply');
    $this->line(json_encode(['apply' => $apply, 'leases' => $p->recover($apply),
        'unmanaged' => $p->recoverUnmanaged($apply), 'temporaries' => $p->cleanup($apply)]));

    return 0;
})->purpose('Inspeccionar recuperación y temporales; requiere --apply para cambiar datos');

Artisan::command('ficct:scan-renew {--apply : Encolar los análisis; sin esta opción solo simula} {--margin-hours=6 : Horas previas al vencimiento en que se renueva}', function () {
    $margin = filter_var($this->option('margin-hours'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 23]]);
    if ($margin === false) {
        $this->error('Margen inválido: use un entero entre 0 y 23 horas.');

        return 1;
    }
    $this->line(json_encode(app(ScanRenewal::class)->run((bool) $this->option('apply'), $margin), JSON_UNESCAPED_UNICODE));

    return 0;
})->purpose('Renovar análisis vencidos o próximos a vencer de documentos vigentes; simulación por defecto, nunca libera archivos');

// El análisis caduca a las 24 h (config/document_safety.php): requiere un planificador del sistema operativo
// que ejecute `php artisan schedule:run` cada minuto (cron en Linux, Programador de tareas en Windows).
Schedule::command('ficct:scan-renew --apply')->hourly()->withoutOverlapping();
Schedule::command('ficct:scan-worker --max-jobs=200 --max-seconds=240')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('ficct:scan-maintain --apply')->hourly()->withoutOverlapping();

Artisan::command('ficct:scan-queue-status {--check : Devolver código 1 si hay alertas operativas}', function () {
    $status = app(DocumentProcessor::class)->queueStatus();
    $this->line(json_encode($status));

    return $this->option('check') && $status['health'] !== 'ok' ? 1 : 0;
})->purpose('Consultar estado agregado del procesador sin revelar expedientes');

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

Artisan::command('ficct:import-sources {--first= : ID histórico de 01/2026} {--second= : ID histórico de 02/2026}', function () {
    if (! ctype_digit((string) $this->option('first')) || ! ctype_digit((string) $this->option('second'))) {
        $this->error('Indique ambos identificadores históricos explícitamente.');
        return 1;
    }
    $this->line(json_encode(app(\App\Domain\InstitutionalCatalogue::class)->import((int) $this->option('first'), (int) $this->option('second')), JSON_UNESCAPED_UNICODE));
    return 0;
})->purpose('Importar fuentes 2026 verificadas sin reabrir procesos ni inventar plazas');
