<?php

use Illuminate\Support\Facades\Route;
use Modules\Booking\Http\Controllers\Api\V1\BookingSettingsController;
use Modules\Booking\Http\Controllers\Api\V1\PublicAvailabilityController;
use Modules\Booking\Http\Controllers\Api\V1\PublicBookingController;
use Modules\Booking\Http\Controllers\Api\V1\PublicOpenSlotsController;
use Modules\Booking\Http\Controllers\Api\V1\PublicServiceController;
use Modules\Booking\Http\Controllers\Api\V1\PublicStaffController;
use Modules\Tenancy\Http\Middleware\ResolvePublicTenant;

/*
|--------------------------------------------------------------------------
| Booking API routes
|--------------------------------------------------------------------------
|
| Registered under /api/v1 with the "api" middleware group.
|
| Two shapes in one file, because spec §12 is both halves: an unauthenticated public surface a
| stranger reaches by a tenant slug in the URL (no login, no permission system — ResolvePublicTenant
| establishes the tenant instead of ResolveTenant, see D-024), and an ordinary authenticated
| settings screen the owner configures it from.
|
| The public group is the only one throttled by "public" rather than the default "api" limiter —
| it is the one surface in the product a stranger can reach at all (RateLimitServiceProvider's own
| docblock already names this exact use case).
*/

Route::prefix('public/{tenant}')
    ->middleware(['throttle:public', ResolvePublicTenant::class])
    ->group(function (): void {
        Route::get('services', [PublicServiceController::class, 'index'])->name('public.services.index');
        Route::get('staff', [PublicStaffController::class, 'index'])->name('public.staff.index');
        Route::get('availability', PublicAvailabilityController::class)->name('public.availability.show');
        Route::post('appointments', [PublicBookingController::class, 'store'])->name('public.appointments.store');
    });

// Carved out of the group above on 2026-10-03 (`RateLimitServiceProvider::registerPublicAvailabilityLimiter()`):
// the booking page's "find the next open day" search can call this one endpoint far more than
// once per customer action, and the shared 30/minute public budget made that search itself the
// most likely way to exhaust it — a customer's own date search could lock them out of their own
// next request, misreported as "no availability" rather than "please wait a moment".
Route::prefix('public/{tenant}')
    ->middleware(['throttle:public-availability', ResolvePublicTenant::class])
    ->group(function (): void {
        Route::get('availability/open-slots', PublicOpenSlotsController::class)->name('public.availability.open-slots');
    });

Route::middleware(['auth:sanctum', 'tenant', 'permission:settings.manage'])->group(function (): void {
    Route::get('booking-settings', [BookingSettingsController::class, 'show'])->name('booking-settings.show');
    Route::put('booking-settings', [BookingSettingsController::class, 'update'])->name('booking-settings.update');
});
