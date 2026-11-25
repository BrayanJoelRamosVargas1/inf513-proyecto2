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
            // Fail closed on a mixed database; never delete or relabel historical test data.
            abort_if(DB::table('calls')->where('mode', 'DEMO')->exists()
                || DB::table('users')->where('email', 'ilike', '%.test')->exists(), 503,
                'La instalación institucional requiere una base y almacenamiento independientes de entrenamiento.');
            abort_if($request->is('ejemplos/*', 'api/v1/sandbox', 'api/v1/sandbox/*', 'p2/entrenamiento'), 404);
        }

        return $next($request);
    }
}
