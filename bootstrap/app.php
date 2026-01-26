<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register route middleware aliases here (Laravel 11 style)
        $middleware->alias([
            'resolveTenant' => \App\Http\Middleware\ResolveTenant::class,
            'superAdmin' => \App\Http\Middleware\SuperAdmin::class,
            'feature' => \App\Http\Middleware\EnsureFeatureEnabled::class,
        ]);

        // If you ever want to append to 'web' or 'api' stacks:
        // $middleware->web(append: [/* ... */]);
        // $middleware->api(append: [/* ... */]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
