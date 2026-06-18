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
        // This app is intended to run behind a reverse proxy that terminates TLS
        // (see the README "Using a Reverse Proxy" section). Trusting the proxy's
        // X-Forwarded-* headers lets the app see the real https scheme/host;
        // without it every request looks like plain HTTP, which breaks https /
        // signed-URL generation and secure-cookie/session handling.
        //
        // TRUSTED_PROXIES controls which proxies are trusted:
        //   "*"                  trust any proxy (default — safe when the app is
        //                        only reachable through your proxy)
        //   "10.0.0.0/8,1.2.3.4" trust specific proxy IPs/CIDRs
        //   ""  (empty)          trust no proxies — set this when exposing the
        //                        app directly, with no reverse proxy in front
        $trustedProxies = env('TRUSTED_PROXIES', '*');
        if ($trustedProxies !== null && $trustedProxies !== '') {
            $middleware->trustProxies(
                at: $trustedProxies === '*' ? '*' : array_map('trim', explode(',', $trustedProxies)),
            );
        }

        $middleware->appendToGroup('web', [
            AddHttp3EarlyHints::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
