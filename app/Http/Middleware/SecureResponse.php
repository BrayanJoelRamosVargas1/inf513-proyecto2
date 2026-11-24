<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Api\SessionController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as LaravelResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class SecureResponse
{
    public function handle(Request $request, Closure $next)
    {
        app()->setLocale('es');
        if ($request->user()) {
            $user = $request->user()->refresh();
            $version = $request->session()->get('security_version', 0);
            if ($version !== $user->security_version) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->is('api/*')) {
                    return self::secure(response()->json(['message' => 'Los permisos cambiaron. Inicie sesión nuevamente.'], 401));
                }

                return self::secure(redirect('/login')->withErrors(['email' => 'Los permisos cambiaron. Inicie sesión nuevamente.']));
            }
            $request->session()->put('security_version', $user->security_version);
        }
        if ($request->user() && ! $request->user()->active) {
            abort(403, 'Cuenta desactivada.');
        }

        $response = $next($request);
        $marker = '<!--ficct:legacy-visit-count-->';
        if ($request->isMethod('GET') && ! $request->is('api/*', 'p2', 'p2/*')
            && $response->getStatusCode() === 200
            && str_contains($response->headers->get('Content-Type', ''), 'text/html')
            && str_contains((string) $response->getContent(), $marker)) {
            // Count after successful rendering, once per response, preserving SPA receipts.
            $path = $request->getPathInfo();
            $total = DB::transaction(function () use ($request, $path) {
                DB::table('page_visits')->insertOrIgnore(['path' => $path, 'total' => 0]);
                DB::table('page_visits')->where('path', $path)->increment('total');
                SessionController::event($request, 'page.view', 200, $path);

                return DB::table('page_visits')->where('path', $path)->value('total');
            });
            $original = $response instanceof LaravelResponse ? $response->getOriginalContent() : null;
            $response->setContent(str_replace($marker, '<span>Visitas de esta vista: '.$total.'</span>', $response->getContent()));
            if ($response instanceof LaravelResponse) {
                $response->original = $original;
            }
        }

        return self::secure($response);
    }

    public static function secure(Response $response): Response
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; style-src 'self'; img-src 'self' data:; script-src ".(request()->is('p2', 'p2/*') ? "'self'" : "'none'")."; object-src 'none'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
