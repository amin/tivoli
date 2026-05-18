<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToGroup('api', \App\Http\Middleware\EnsureFrontendRequestsAreStateful::class);
        $middleware->prepend(\App\Http\Middleware\PublicEndpointCors::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
