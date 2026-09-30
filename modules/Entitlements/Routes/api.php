<?php

use Illuminate\Support\Facades\Route;
use Modules\Entitlements\Http\Controllers\Api\V1\EntitlementController;
use Modules\Entitlements\Http\Controllers\Api\V1\PlanController;

/*
|--------------------------------------------------------------------------
| Entitlements API routes
|--------------------------------------------------------------------------
|
| Registered under /api/v1 with the "api" middleware group.
|
*/

// The price list is public: the marketing site's pricing page and the pre-signup plan chooser
// both read it, and it contains nothing tenant-specific.
Route::get('plans', PlanController::class)
    ->middleware('throttle:public')
    ->name('plans.index');

// What this business may use. Needs the tenant, so it needs authentication.
Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::get('entitlements', EntitlementController::class)->name('entitlements.index');
});
