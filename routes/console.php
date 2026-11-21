<?php

use App\Domain\DemoJourney;
use App\Domain\DocumentaryJourney;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

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
