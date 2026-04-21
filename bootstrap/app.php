<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Add root route without middleware
            Route::get('/', function () {
                return response()->json([
                    'service' => 'ZATCA Proxy Service',
                    'status' => 'online',
                    'version' => '1.0.0',
                    'message' => 'API service is running',
                    'api_endpoints' => '/api/',
                    'health_check' => '/api/health'
                ]);
            });
        }
    )
    ->withMiddleware(function (Middleware $middleware) {
        // $middleware->api(prepend: [
        //     \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        // ]);

        $middleware->alias([
            'api.key' => \App\Http\Middleware\ApiKeyMiddleware::class,
            'ip.whitelist' => \App\Http\Middleware\IpWhitelistMiddleware::class,
        ]);

        // $middleware->throttleApi('api', 1000, 1); // Disabled for local testing to avoid Redis errors
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();