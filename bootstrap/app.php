<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \App\Http\Middleware\RequestMetricsMiddleware::class,
        ]);

        $middleware->alias([
            'admin.token' => \App\Http\Middleware\EnsureAdminToken::class,
        ]);

        // App + nginx draaien op dezelfde host (188.245.159.115) → vertrouw alleen
        // 127.0.0.1 als proxy. Zonder dit zou $request->ip() de loopback teruggeven
        // voor elke request en zou rate-limiting van auth-routes alle gebruikers in
        // één bucket stoppen (brute-force gap).
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->reportable(function (\Throwable $e) {
            if (config('observability.enabled', true)) {
                \App\Models\ErrorLog::capture($e, request());
            }
        });
    })->create();
