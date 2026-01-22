<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Load auth.php with web middleware
            Route::middleware('web')
                ->prefix('api')
                ->group(base_path('routes/auth.php'));
            
            // Load api.php with web middleware for session support
            Route::middleware('web')
                ->prefix('api')
                ->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            ]);
        $middleware->statefulApi();

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
