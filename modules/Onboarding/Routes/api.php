<?php

use Illuminate\Support\Facades\Route;
use Modules\Onboarding\Http\Controllers\Api\V1\BusinessProfileController;
use Modules\Onboarding\Http\Controllers\Api\V1\OnboardingChecklistController;
use Modules\Onboarding\Http\Controllers\Api\V1\OnboardingCompletionController;
use Modules\Onboarding\Http\Controllers\Api\V1\OnboardingStepController;

/*
|--------------------------------------------------------------------------
| Onboarding API routes
|--------------------------------------------------------------------------
|
| Registered under /api/v1 with the "api" middleware group.
|
| Gated on settings.manage, which spec §5 gives to the Owner alone. Setting up the business
| is the owner's job, and the §16 dashboard reaches the same information through the
| OnboardingStatus contract rather than through these endpoints, so other roles lose nothing
| by not having them.
|
| No `entitlement:` middleware anywhere here, deliberately — for the same reason Billing has
| none. Every plan includes Core OS (§25), and a business that could not finish setting up
| because of its plan would be a business that can never use the product it paid for.
|
*/

Route::middleware(['auth:sanctum', 'tenant', 'permission:settings.manage'])->group(function (): void {

    Route::prefix('onboarding')->name('onboarding.')->group(function (): void {
        Route::get('/', OnboardingChecklistController::class)->name('checklist');
        Route::post('finish', OnboardingCompletionController::class)->name('finish');

        // {step} is typed as the OnboardingStep enum in the controller, so Laravel resolves
        // it and 404s anything that is not a real step — no route constraint needed.
        Route::post('steps/{step}/complete', [OnboardingStepController::class, 'complete'])
            ->name('steps.complete');
        Route::post('steps/{step}/skip', [OnboardingStepController::class, 'skip'])
            ->name('steps.skip');
    });

    Route::get('business-profile', [BusinessProfileController::class, 'show'])
        ->name('business-profile.show');
    Route::put('business-profile', [BusinessProfileController::class, 'update'])
        ->name('business-profile.update');
});
