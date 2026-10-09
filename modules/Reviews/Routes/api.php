<?php

use Illuminate\Support\Facades\Route;
use Modules\Reviews\Http\Controllers\Api\V1\ReviewController;
use Modules\Reviews\Http\Controllers\Api\V1\ReviewDestinationController;

/*
|--------------------------------------------------------------------------
| Reviews API routes
|--------------------------------------------------------------------------
|
| Registered under /api/v1 with the "api" middleware group.
|
| Gated by `entitlement:review_support` — Starter does not include it; Business holds it at
| `standard`, Growth at `advanced`, Growth Partner at `enterprise` (D-051: a grade is the granting
| tier's own name). No minimum grade on the middleware itself: this module's own code is the same
| whatever the grade, because what the higher tiers really add is Managed Growth's human service,
| not a software feature these routes could gate.
*/
Route::middleware(['auth:sanctum', 'tenant', 'entitlement:review_support'])->group(function (): void {

    Route::middleware('permission:reviews.view')->group(function (): void {
        Route::get('review-destinations', [ReviewDestinationController::class, 'index'])
            ->name('review-destinations.index');
        Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
    });

    Route::middleware('permission:reviews.manage')->group(function (): void {
        Route::post('review-destinations', [ReviewDestinationController::class, 'store'])
            ->name('review-destinations.store');
        Route::delete('review-destinations/{reviewDestination}', [ReviewDestinationController::class, 'destroy'])
            ->name('review-destinations.destroy');

        Route::post('reviews', [ReviewController::class, 'store'])->name('reviews.store');
        Route::put('reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
        Route::delete('reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
    });
});
