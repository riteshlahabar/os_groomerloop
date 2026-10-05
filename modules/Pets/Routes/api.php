<?php

use Illuminate\Support\Facades\Route;
use Modules\Pets\Http\Controllers\Api\V1\CustomerPetController;
use Modules\Pets\Http\Controllers\Api\V1\PetController;
use Modules\Pets\Http\Controllers\Api\V1\PetInternalNoteController;
use Modules\Pets\Http\Controllers\Api\V1\SpeciesController;

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

        // Read access sits with pets.view, not settings.manage: the pet form's species picker
        // and the list's species filter both need this, for every role that can see pets at all.
        Route::get('pet-species', [SpeciesController::class, 'index'])->name('pet-species.index');
    });

    // --- Writing ------------------------------------------------------------------------
    Route::middleware('permission:pets.manage')->group(function (): void {
        Route::post('pets', [PetController::class, 'store'])->name('pets.store');
        Route::put('pets/{pet}', [PetController::class, 'update'])->name('pets.update');
        Route::delete('pets/{pet}', [PetController::class, 'destroy'])->name('pets.destroy');
    });

    // Adding or retiring a species is a Settings-tab action, the owner's call — the same bar
    // Catalog's service categories sit behind, narrower than this module's own pets.manage.
    Route::middleware('permission:settings.manage')->group(function (): void {
        Route::post('pet-species', [SpeciesController::class, 'store'])->name('pet-species.store');
        Route::delete('pet-species/{species}', [SpeciesController::class, 'destroy'])->name('pet-species.destroy');
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
