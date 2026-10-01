<?php

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\Api\V1\ServiceAvailabilityController;
use Modules\Catalog\Http\Controllers\Api\V1\ServiceCategoryController;
use Modules\Catalog\Http\Controllers\Api\V1\ServiceController;

/*
|--------------------------------------------------------------------------
| Catalog API routes
|--------------------------------------------------------------------------
|
| Registered under /api/v1 with the "api" middleware group.
|
| No `entitlement:` here, unlike Crm and Pets. Spec §25 does not gate the service catalogue behind
| a plan, and it could not sensibly do so: a business that cannot define what it sells cannot use
| the product at all, whatever it pays. Declaring a gate that must never refuse anyone would be
| worse than declaring none — the first person to read it would assume it was load-bearing.
|
*/

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {

    // --- Reading ------------------------------------------------------------------------
    //
    // Every working role holds services.view (§5): a groomer needs to know what a "full groom"
    // includes and how long it is booked for.
    Route::middleware('permission:services.view')->group(function (): void {
        Route::get('services', [ServiceController::class, 'index'])->name('services.index');
        Route::get('services/{service}', [ServiceController::class, 'show'])->name('services.show');

        Route::get('service-categories', [ServiceCategoryController::class, 'index'])
            ->name('service-categories.index');
    });

    // --- Writing ------------------------------------------------------------------------
    //
    // The price list is Owner and Manager only (§5). A front desk that could reprice a groom is a
    // business that cannot reconcile its own takings.
    Route::middleware('permission:services.manage')->group(function (): void {
        Route::post('services', [ServiceController::class, 'store'])->name('services.store');
        Route::put('services/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::delete('services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');

        Route::put('services/{service}/availability', ServiceAvailabilityController::class)
            ->name('services.availability');

        Route::post('service-categories', [ServiceCategoryController::class, 'store'])
            ->name('service-categories.store');
        Route::delete('service-categories/{serviceCategory}', [ServiceCategoryController::class, 'destroy'])
            ->name('service-categories.destroy');
    });
});
