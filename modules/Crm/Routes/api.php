<?php

use Illuminate\Support\Facades\Route;
use Modules\Crm\Http\Controllers\Api\V1\CustomerConsentController;
use Modules\Crm\Http\Controllers\Api\V1\CustomerController;
use Modules\Crm\Http\Controllers\Api\V1\CustomerDuplicateController;
use Modules\Crm\Http\Controllers\Api\V1\CustomerExportController;
use Modules\Crm\Http\Controllers\Api\V1\CustomerImportController;
use Modules\Crm\Http\Controllers\Api\V1\CustomerMergeController;
use Modules\Crm\Http\Controllers\Api\V1\CustomerTagController;

/*
|--------------------------------------------------------------------------
| CRM API routes
|--------------------------------------------------------------------------
|
| Registered under /api/v1 with the "api" middleware group.
|
| Gated by `entitlement:crm_pets` as well as by permission. Spec §25 gives CRM + pets to
| every plan, so this never actually refuses anyone today — it is declared because the
| §25 matrix is data and packaging can change without a deploy (invariant #3). A route
| that assumed "every plan has this" would be the thing that had to be found and edited
| the day it stopped being true.
|
*/

Route::middleware(['auth:sanctum', 'tenant', 'entitlement:crm_pets'])->group(function (): void {

    // --- Reading ------------------------------------------------------------------------
    Route::middleware('permission:customers.view')->group(function (): void {
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');

        Route::get('customers/{customer}/duplicates', CustomerDuplicateController::class)
            ->name('customers.duplicates');

        Route::get('customer-tags', [CustomerTagController::class, 'index'])->name('customer-tags.index');
    });

    // --- Writing ------------------------------------------------------------------------
    Route::middleware('permission:customers.manage')->group(function (): void {
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');

        Route::put('customers/{customer}/consent', CustomerConsentController::class)
            ->name('customers.consent');

        // Merging is destructive in effect even though nothing is hard-deleted, so it sits
        // with the write permission rather than with the read-only duplicate listing.
        Route::post('customers/{customer}/merge', CustomerMergeController::class)
            ->name('customers.merge');

        Route::post('customer-tags', [CustomerTagController::class, 'store'])->name('customer-tags.store');
        Route::delete('customer-tags/{customerTag}', [CustomerTagController::class, 'destroy'])
            ->name('customer-tags.destroy');
    });

    // --- Bulk ---------------------------------------------------------------------------
    //
    // Their own permissions (spec §8, "import/export with permissions"), held by Owner and
    // Manager only. Moving the whole customer book in or out is a different risk from
    // editing one record, and the front desk does the second all day without needing the
    // first.
    Route::post('customers/import', CustomerImportController::class)
        ->middleware('permission:customers.import')
        ->name('customers.import');

    Route::get('customers-export', CustomerExportController::class)
        ->middleware('permission:customers.export')
        ->name('customers.export');
});
