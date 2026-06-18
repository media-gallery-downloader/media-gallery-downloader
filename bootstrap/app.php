<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use JustBetter\Http3EarlyHints\Middleware\AddHttp3EarlyHints;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // The app runs behind the Caddy reverse proxy (HTTPS termination on the
        // shared `edge` network) and is only reachable through it. Trust the
        // forwarded headers so the app sees the real https scheme and host;
        // without this every request looks like plain HTTP, which breaks https
        // URL/signed-URL generation and secure-cookie/session handling.
        $middleware->trustProxies(at: '*');

        $middleware->appendToGroup('web', [
            AddHttp3EarlyHints::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
