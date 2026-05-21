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

        // Laravel 11's default `redirectGuestsTo(fn () => route('login'))`
        // explodes here because we have no route named 'login' — this is a
        // JSON API, not an HTML app. Disable the redirect so the auth
        // middleware throws AuthenticationException directly, which our
        // shouldRenderJsonWhen renders as 401 JSON.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // This is a JSON-only API — there is no HTML view layer. Render every
        // framework-thrown exception (auth, validation, 404, etc.) as JSON
        // regardless of the request's Accept header so clients don't need to
        // remember to send `Accept: application/json`.
        $exceptions->shouldRenderJsonWhen(fn () => true);
    })->create();
