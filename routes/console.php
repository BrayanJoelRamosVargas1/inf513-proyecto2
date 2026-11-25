<?php

use Illuminate\Support\Facades\Schedule;

// El análisis caduca a las 24 h (config/document_safety.php): requiere un planificador del sistema operativo
// que ejecute `php artisan schedule:run` cada minuto (cron en Linux, Programador de tareas en Windows).
Schedule::command('ficct:scan-renew --apply')->hourly()->withoutOverlapping();
Schedule::command('ficct:scan-worker --max-jobs=200 --max-seconds=240')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('ficct:scan-maintain --apply')->hourly()->withoutOverlapping();

