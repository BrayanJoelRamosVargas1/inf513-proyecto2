<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class InstitutionalExperience
{
    public function handle(Request $request, Closure $next)
    {
        $mode = config('portal.experience');
        abort_unless(in_array($mode, ['training', 'institutional'], true), 503, 'La experiencia de la instalación no está configurada.');
        if ($mode === 'institutional') {
            // Falla cerrada ante una base mezclada; nunca borra ni reetiqueta datos de entrenamiento. Los correos de ejemplo se
            // revisan en ficct:verificar-instalacion y se rechazan al registrar cuentas; no pueden dejar fuera de servicio el sistema.
            abort_if(DB::table('calls')->where('mode', 'DEMO')->exists(), 503,
                'La instalación institucional requiere una base y almacenamiento independientes de entrenamiento.');
            abort_if($request->is('ejemplos/*', 'api/v1/sandbox', 'api/v1/sandbox/*', 'p2/entrenamiento'), 404);
            // La instalación institucional usa solo la aplicación React (/p2) y la API; las vistas Blade son del modo entrenamiento.
            if (! $request->is('p2', 'p2/*', 'api/*', 'up')) {
                abort_unless($request->isMethod('GET'), 404);

                return redirect('/p2');
            }
        }

        return $next($request);
    }
}
