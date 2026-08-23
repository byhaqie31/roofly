<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->alias([
            'role'          => \App\Http\Middleware\EnsureRole::class,
            'touch-active'  => \App\Http\Middleware\TouchLastActive::class,
            'not-suspended' => \App\Http\Middleware\EnsureNotSuspended::class,
        ]);
        // /api/track is a public beacon (sendBeacon/fetch from marketing pages, no session
        // cookie round-trip guaranteed) — exempt it from CSRF so cross-origin beacons aren't
        // rejected with 419.
        $middleware->validateCsrfTokens(except: ['api/track']);
        // nginx/Cloudflare sit in front of every environment; trust all proxies so the
        // client IP (used for per-IP throttling and analytics ip_hash) is read from
        // X-Forwarded-For rather than the proxy's own address.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
