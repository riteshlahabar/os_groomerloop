<?php

use Illuminate\Support\Facades\Route;
use Modules\Platform\Http\Controllers\Api\V1\HealthController;

/*
|--------------------------------------------------------------------------
| Platform API routes
|--------------------------------------------------------------------------
|
| Registered by ModuleServiceProvider under the "api" middleware group with the
| /api/v1 prefix and the api.v1. route-name prefix, so paths and names here are
| relative. Nothing in this file is tenant-scoped.
|
*/

// Unauthenticated on purpose: uptime monitors and load balancers must reach it. Throttled
// with the public limiter so it cannot be used to probe the database in a loop.
Route::get('health', HealthController::class)
    ->middleware('throttle:public')
    ->name('health');
