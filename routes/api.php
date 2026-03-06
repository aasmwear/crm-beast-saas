<?php

use App\Http\Controllers\Api\PingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['org_api_key', 'throttle_org_api'])->group(function () {
    Route::get('/ping', PingController::class)->name('api.ping');
});
