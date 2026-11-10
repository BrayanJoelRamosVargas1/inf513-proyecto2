<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class SecureResponse
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()) {
            $user = $request->user()->refresh();
            $version = $request->session()->get('security_version', 0);
            if ($version !== $user->security_version) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect('/login')->withErrors(['email' => 'Los permisos cambiaron. Inicie sesión nuevamente.']);
            }
            $request->session()->put('security_version', $user->security_version);
        }
        if ($request->user() && ! $request->user()->active) {
            abort(403, 'Cuenta desactivada.');
        }
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; style-src 'self'; img-src 'self' data:; script-src 'none'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
