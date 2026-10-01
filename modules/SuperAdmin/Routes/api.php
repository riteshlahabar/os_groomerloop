<?php

use Illuminate\Support\Facades\Route;
use Modules\SuperAdmin\Http\Controllers\Api\V1\PlatformMailSettingsController;

/*
|--------------------------------------------------------------------------
| SuperAdmin API routes
|--------------------------------------------------------------------------
|
| Registered under /api/v1 with the "api" middleware group — deliberately WITHOUT the "tenant"
| middleware every other protected route group carries. A GroomerLoop Admin (spec §5) belongs to
| no tenant (ResolveTenant::class already 403s one that reaches a tenant-scoped route), so
| forcing tenant resolution here would refuse the only role that can ever call this endpoint.
| "auth:sanctum" + "permission:platform.administer" is the complete gate.
*/

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'permission:platform.administer'])
    ->group(function (): void {
        Route::get('mail-settings', [PlatformMailSettingsController::class, 'show'])
            ->name('admin.mail-settings.show');
        Route::put('mail-settings', [PlatformMailSettingsController::class, 'update'])
            ->name('admin.mail-settings.update');
    });
