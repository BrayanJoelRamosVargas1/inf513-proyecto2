<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class SecureResponse
{
    public function handle(Request $request, Closure $next)
    {
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
