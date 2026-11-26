<?php

use App\Http\Middleware\InstitutionalExperience;
use App\Http\Middleware\SecureResponse;
use App\Http\Middleware\SubpathLinks;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        then: function () {
            require __DIR__.'/../routes/api.php';
        },
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Detrás de un proxy HTTPS: TRUSTED_PROXIES="10.0.0.1,10.0.0.2" (o "*" si el proxy es el único acceso).
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
        // La experiencia se decide antes que la autenticación: en la instalación institucional las vistas Blade redirigen a /p2.
        $middleware->web(prepend: [InstitutionalExperience::class], append: [SecureResponse::class, SubpathLinks::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(fn (Response $response) => SecureResponse::secure($response));
    })->create();
