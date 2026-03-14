<?php

use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web:      __DIR__ . '/../routes/web.php',
        api:      __DIR__ . '/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__ . '/../routes/console.php',
        health:   '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(SecurityHeadersMiddleware::class);

        $middleware->api(prepend: [
            HandleCors::class,
        ]);

        $middleware->alias([
            'role'     => RoleMiddleware::class,
            'throttle' => ThrottleRequests::class,
        ]);

        // ✅ No throttleApi() call here — rate limiters are defined
        //    in AppServiceProvider::boot() instead
    })
    ->withExceptions(function (Exceptions $exceptions) {})
    ->create();