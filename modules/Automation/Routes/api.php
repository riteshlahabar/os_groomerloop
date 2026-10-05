<?php

use Illuminate\Support\Facades\Route;
use Modules\Automation\Http\Controllers\Api\V1\AutomationRunController;
use Modules\Automation\Http\Controllers\Api\V1\AutomationSettingsController;

/*
|--------------------------------------------------------------------------
| Automation API routes
|--------------------------------------------------------------------------
|
| Registered under /api/v1 with the "api" middleware group.
|
| entitlement:automation is a presence check only — every plan has at least the Basic grade
| (PlanSeeder), so this never refuses a request on its own; AutomationSettingsManager::
| maxEnabled() is what actually grades how many of the five keys a tenant's plan allows enabled
| at once, enforced inside the write endpoint rather than the route.
*/

Route::middleware(['auth:sanctum', 'tenant', 'entitlement:automation'])->group(function (): void {
    Route::middleware('permission:automation.view')->group(function (): void {
        Route::get('automation/settings', [AutomationSettingsController::class, 'index'])->name('automation.settings.index');
        Route::get('automation/runs', [AutomationRunController::class, 'index'])->name('automation.runs.index');
    });

    Route::middleware('permission:automation.manage')->group(function (): void {
        Route::put('automation/settings/{key}', [AutomationSettingsController::class, 'update'])->name('automation.settings.update');
    });
});
