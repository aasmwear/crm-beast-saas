<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware(['web'])
                ->prefix('admin')
                ->name('platform.')
                ->group(base_path('routes/platform.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            'webhooks/stripe',
        ]);

        // 1. Register Aliases
        $middleware->alias([
            'resolveTenant' => \App\Http\Middleware\ResolveTenant::class,
            'superAdmin' => \App\Http\Middleware\SuperAdmin::class,
            'feature' => \App\Http\Middleware\EnsureFeatureEnabled::class,
            'org_api_key' => \App\Http\Middleware\AuthenticateOrganizationApiKey::class,
            'throttle_org_api' => \App\Http\Middleware\ThrottleOrgApi::class,
            'portal' => \App\Http\Middleware\EnsurePortalUser::class,
        ]);

        // 2. ACTIVATE THE WEB MIDDLEWARE (The Missing Piece)
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // 3. API Middleware
        $middleware->api(append: [
            \App\Http\Middleware\FilterResponseFields::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
