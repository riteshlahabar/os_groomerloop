<?php

use Illuminate\Support\Facades\Route;
use Modules\Team\Http\Controllers\Api\V1\StaffMemberController;
use Modules\Team\Http\Controllers\Api\V1\StaffReactivationController;
use Modules\Team\Http\Controllers\Api\V1\StaffTimeOffController;
use Modules\Team\Http\Controllers\Api\V1\StaffWorkingHoursController;

/*
|--------------------------------------------------------------------------
| Team API routes
|--------------------------------------------------------------------------
|
| Registered under /api/v1 with the "api" middleware group.
|
| Gated by staff.view/staff.manage — deliberately separate from Identity's team.view/team.manage,
| which gate user invitations and role changes. Owner and Manager hold staff.manage; everyone
| working in the salon except Marketing holds at least staff.view.
|
| No `entitlement:` here, the same as Catalog: a business that cannot say who grooms cannot use
| the product at all.
*/

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {

    // --- Reading ------------------------------------------------------------------------
    Route::middleware('permission:staff.view')->group(function (): void {
        Route::get('staff', [StaffMemberController::class, 'index'])->name('staff.index');
        Route::get('staff/{staffMember}', [StaffMemberController::class, 'show'])->name('staff.show');
    });

    // --- Writing ------------------------------------------------------------------------
    Route::middleware('permission:staff.manage')->group(function (): void {
        Route::post('staff', [StaffMemberController::class, 'store'])->name('staff.store');
        Route::put('staff/{staffMember}', [StaffMemberController::class, 'update'])->name('staff.update');
        Route::delete('staff/{staffMember}', [StaffMemberController::class, 'destroy'])->name('staff.destroy');

        Route::post('staff/{staffMember}/reactivate', StaffReactivationController::class)
            ->name('staff.reactivate');

        Route::put('staff/{staffMember}/working-hours', StaffWorkingHoursController::class)
            ->name('staff.working-hours');

        Route::post('staff/{staffMember}/time-off', [StaffTimeOffController::class, 'store'])
            ->name('staff.time-off.store');
        Route::delete('staff/{staffMember}/time-off/{timeOff}', [StaffTimeOffController::class, 'destroy'])
            ->name('staff.time-off.destroy');
    });
});
