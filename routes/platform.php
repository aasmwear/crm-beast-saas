<?php

use App\Http\Controllers\Platform\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Platform\DashboardController;
use App\Http\Controllers\Platform\Organizations\OrgHealthController;
use App\Http\Controllers\Platform\Organizations\SubscriptionsController;
use App\Http\Controllers\Platform\RevenueDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Platform Admin Routes
|--------------------------------------------------------------------------
|
| These routes are for Super Admins and Support staff who manage the
| platform and all organizations. They use the 'platform' guard.
|
| Prefix: /admin
| Name: platform.*
| Guard: platform
|
*/

// Guest routes (login, password reset, etc.)
Route::middleware('guest:platform')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->name('login.store');

    Route::get('/forgot-password', function () {
        return inertia('Platform/Auth/ForgotPassword');
    })->name('password.request');

    Route::post('/forgot-password', function () {
        // TODO: Implement password reset request logic
    })->name('password.email');

    Route::get('/reset-password/{token}', function (string $token) {
        return inertia('Platform/Auth/ResetPassword', ['token' => $token]);
    })->name('password.reset');

    Route::post('/reset-password', function () {
        // TODO: Implement password reset logic
    })->name('password.store');
});

// Authenticated platform admin routes
Route::middleware('auth:platform')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // Revenue / MRR Dashboard
    Route::get('/revenue', [RevenueDashboardController::class, 'index'])
        ->name('revenue');

    // Organizations Management
    Route::prefix('/organizations')->name('organizations.')->group(function () {
        Route::get('/health', [OrgHealthController::class, 'index'])
            ->name('health');

        Route::get('/subscriptions', [SubscriptionsController::class, 'index'])
            ->name('subscriptions');

        Route::get('/', function () {
            return inertia('Platform/Organizations/Index');
        })->name('index');

        Route::get('/{organization}', function () {
            return inertia('Platform/Organizations/Show');
        })->name('show');

        Route::patch('/{organization}/subscription', [SubscriptionsController::class, 'updateSubscription'])
            ->name('subscription.update')
            ->scopeBindings();

        Route::post('/{organization}/addons', [SubscriptionsController::class, 'storeAddon'])
            ->name('addons.store')
            ->scopeBindings();

        Route::patch('/{organization}/addons/{addon}', [SubscriptionsController::class, 'updateAddon'])
            ->name('addons.update')
            ->scopeBindings();

        Route::delete('/{organization}/addons/{addon}', [SubscriptionsController::class, 'destroyAddon'])
            ->name('addons.destroy')
            ->scopeBindings();

        Route::patch('/{organization}/features', function () {
            // TODO: Implement feature toggle logic
        })->name('features.update');
    });

    // System Settings
    Route::prefix('/settings')->name('settings.')->group(function () {
        Route::get('/', function () {
            return inertia('Platform/Settings/Index');
        })->name('index');
    });

    // Platform Admin Management
    Route::prefix('/admins')->name('admins.')->group(function () {
        Route::get('/', function () {
            return inertia('Platform/Admins/Index');
        })->name('index');

        Route::post('/', function () {
            // TODO: Implement admin creation logic
        })->name('store');

        Route::patch('/{admin}', function () {
            // TODO: Implement admin update logic
        })->name('update');

        Route::delete('/{admin}', function () {
            // TODO: Implement admin deletion logic
        })->name('destroy');
    });

    // Logout
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
