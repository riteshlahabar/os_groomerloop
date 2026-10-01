<?php

use Illuminate\Support\Facades\Route;
use Modules\Scheduling\Http\Controllers\Api\V1\AppointmentController;
use Modules\Scheduling\Http\Controllers\Api\V1\AppointmentRescheduleController;
use Modules\Scheduling\Http\Controllers\Api\V1\AppointmentStatusController;
use Modules\Scheduling\Http\Controllers\Api\V1\AvailabilityController;
use Modules\Scheduling\Http\Controllers\Api\V1\BusinessHoursController;

/*
|--------------------------------------------------------------------------
| Scheduling API routes
|--------------------------------------------------------------------------
|
| Registered under /api/v1 with the "api" middleware group.
|
| calendar.view gates the calendar-browsing surface (the appointment list, a slot-availability
| check, business hours); appointments.view gates one appointment's own full detail. Every
| calendar-touching role (Owner, Manager, Groomer, Front Desk) holds both together today, but the
| split keeps them independently expressible if that ever changes.
|
| appointments.update_status is deliberately narrower than appointments.manage — a Groomer holds
| the first but not the second (spec §5, §11), so AppointmentPolicy::updateStatus() refuses a
| Groomer-held request to cancel or no-show through the shared status endpoint; the route
| middleware alone cannot express "this status, but not that one".
|
| No `entitlement:` here, the same reasoning as Catalog and Team: §25's online_booking and
| appointments_calendar features are included on every plan, Starter included, so gating the
| calendar behind an entitlement check would refuse nobody and only look load-bearing.
*/

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {

    // --- Reading ------------------------------------------------------------------------
    Route::middleware('permission:calendar.view')->group(function (): void {
        Route::get('appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::get('availability', AvailabilityController::class)->name('availability.show');
        Route::get('business-hours', [BusinessHoursController::class, 'index'])->name('business-hours.index');
    });

    Route::middleware('permission:appointments.view')->group(function (): void {
        Route::get('appointments/{appointment}', [AppointmentController::class, 'show'])->name('appointments.show');
    });

    // --- Writing ------------------------------------------------------------------------
    Route::middleware('permission:appointments.manage')->group(function (): void {
        Route::post('appointments', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::put('appointments/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');
        Route::delete('appointments/{appointment}', [AppointmentController::class, 'destroy'])->name('appointments.destroy');

        Route::put('appointments/{appointment}/reschedule', AppointmentRescheduleController::class)
            ->name('appointments.reschedule');
    });

    Route::middleware('permission:appointments.update_status')->group(function (): void {
        Route::put('appointments/{appointment}/status', AppointmentStatusController::class)
            ->name('appointments.status');
    });

    // Business hours are a business-wide configuration decision, the same bar as BusinessProfile
    // (§5 gives it to Owner alone) — distinct from calendar.view's read access above.
    Route::middleware('permission:settings.manage')->group(function (): void {
        Route::put('business-hours', [BusinessHoursController::class, 'update'])->name('business-hours.update');
    });
});
