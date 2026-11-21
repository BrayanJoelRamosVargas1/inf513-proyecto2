<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class SecureResponse
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('api/*')) {
            app()->setLocale('es');
        }
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

        return self::secure($next($request));
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
