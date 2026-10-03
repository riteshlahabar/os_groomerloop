<?php

use Illuminate\Support\Facades\Route;
use Modules\SuperAdmin\Http\Controllers\Api\V1\PlatformAdminController;
use Modules\SuperAdmin\Http\Controllers\Api\V1\PlatformAuditLogController;
use Modules\SuperAdmin\Http\Controllers\Api\V1\PlatformMailSettingsController;
use Modules\SuperAdmin\Http\Controllers\Api\V1\PlatformOverviewController;
use Modules\SuperAdmin\Http\Controllers\Api\V1\PlatformTenantController;
use Modules\SuperAdmin\Http\Controllers\Api\V1\TenantReactivationController;
use Modules\SuperAdmin\Http\Controllers\Api\V1\TenantSuspensionController;

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

        Route::get('overview', PlatformOverviewController::class)->name('admin.overview.show');

        Route::get('tenants', [PlatformTenantController::class, 'index'])->name('admin.tenants.index');
        Route::get('tenants/{tenant}', [PlatformTenantController::class, 'show'])->name('admin.tenants.show');
        Route::post('tenants/{tenant}/suspend', [TenantSuspensionController::class, 'store'])
            ->name('admin.tenants.suspend');
        Route::post('tenants/{tenant}/reactivate', [TenantReactivationController::class, 'store'])
            ->name('admin.tenants.reactivate');

        Route::get('audit-log', [PlatformAuditLogController::class, 'index'])->name('admin.audit-log.index');

        /*
         | GroomerLoop's own staff accounts (`D-034`, overriding `D-029`'s console-only stance).
         | The gate is the same `permission:platform.administer` as everything else here, which
         | only an existing platform admin holds — so this shares the privilege, never grants it
         | from outside. `platform-admin:create` stays as the bootstrap for a fresh host.
         */
        // `platform.manage_admins` on top of the group's `platform.administer` (`D-035`): a plain
        // Admin runs the console but cannot grant console access, which is the single capability
        // that separates the two tiers.
        Route::middleware('permission:platform.manage_admins')->group(function (): void {
            Route::get('platform-admins', [PlatformAdminController::class, 'index'])
                ->name('admin.platform-admins.index');
            Route::post('platform-admins', [PlatformAdminController::class, 'store'])
                ->name('admin.platform-admins.store');
            Route::put('platform-admins/{admin}', [PlatformAdminController::class, 'update'])
                ->name('admin.platform-admins.update');
            Route::delete('platform-admins/{admin}', [PlatformAdminController::class, 'destroy'])
                ->name('admin.platform-admins.destroy');
        });
    });
