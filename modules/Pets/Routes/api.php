<?php

use Illuminate\Support\Facades\Route;
use Modules\Pets\Http\Controllers\Api\V1\CustomerPetController;
use Modules\Pets\Http\Controllers\Api\V1\PetController;
use Modules\Pets\Http\Controllers\Api\V1\PetInternalNoteController;

/*
|--------------------------------------------------------------------------
| Pets API routes
|--------------------------------------------------------------------------
|
| Registered under /api/v1 with the "api" middleware group.
|
| Gated by `entitlement:crm_pets` as well as by permission. Spec §25 gives CRM + pets to every
| plan, so this refuses nobody today — it is declared because the §25 matrix is data and
| packaging can change without a deploy (invariant #3).
|
*/

Route::middleware(['auth:sanctum', 'tenant', 'entitlement:crm_pets'])->group(function (): void {

    // --- Reading ------------------------------------------------------------------------
    Route::middleware('permission:pets.view')->group(function (): void {
        Route::get('pets', [PetController::class, 'index'])->name('pets.index');
        Route::get('pets/{pet}', [PetController::class, 'show'])->name('pets.show');

        // Spec §9: multiple pets linked to one customer. `{customer}` is constrained to digits so
        // it cannot collide with a future literal segment under /customers.
        Route::get('customers/{customer}/pets', CustomerPetController::class)
            ->whereNumber('customer')
            ->name('customers.pets.index');
    });

    // --- Writing ------------------------------------------------------------------------
    Route::middleware('permission:pets.manage')->group(function (): void {
        Route::post('pets', [PetController::class, 'store'])->name('pets.store');
        Route::put('pets/{pet}', [PetController::class, 'update'])->name('pets.update');
        Route::delete('pets/{pet}', [PetController::class, 'destroy'])->name('pets.destroy');
    });

    // --- Internal staff notes -----------------------------------------------------------
    //
    // Its own permission (spec §9), and note which way it cuts: a Groomer reaches this while
    // holding neither pets.manage nor the ability to edit anything else on the record. The
    // handling history is written by whoever handles the animal.
    Route::put('pets/{pet}/internal-notes', PetInternalNoteController::class)
        ->middleware('permission:pets.internal_notes')
        ->name('pets.internal-notes');
});
