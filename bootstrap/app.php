<?php

use App\Http\Middleware\SecureResponse;
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
        $middleware->web(append: [SecureResponse::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(fn (Response $response) => SecureResponse::secure($response));
    })->create();
