<?php

use Illuminate\Support\Facades\Route;
use Modules\SuperAdmin\Http\Controllers\Api\V1\PlatformAuditLogController;
use Modules\SuperAdmin\Http\Controllers\Api\V1\PlatformMailSettingsController;
use Modules\SuperAdmin\Http\Controllers\Api\V1\PlatformOverviewController;
use Modules\SuperAdmin\Http\Controllers\Api\V1\PlatformTenantController;
use Modules\SuperAdmin\Http\Controllers\Api\V1\PlatformTenantMailSettingsController;
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

        // Which businesses have their own SMTP account — the Mail Settings page's picker marks
        // its rows with this. Sits under `mail-settings` rather than `tenants` because it is a
        // fact about mail configuration, not another tenant roster.
        Route::get('mail-settings/tenants', [PlatformTenantMailSettingsController::class, 'configured'])
            ->name('admin.mail-settings.tenants');

        Route::get('overview', PlatformOverviewController::class)->name('admin.overview.show');

        Route::get('tenants', [PlatformTenantController::class, 'index'])->name('admin.tenants.index');
        Route::get('tenants/{tenant}', [PlatformTenantController::class, 'show'])->name('admin.tenants.show');
        Route::post('tenants/{tenant}/suspend', [TenantSuspensionController::class, 'store'])
            ->name('admin.tenants.suspend');
        Route::post('tenants/{tenant}/reactivate', [TenantReactivationController::class, 'store'])
            ->name('admin.tenants.reactivate');

        // One business's own SMTP account (`D-032`). Separate from the platform-wide
        // `mail-settings` above: that one is GroomerLoop's, this one is the tenant's, and a
        // tenant without one falls back to it.
        Route::get('tenants/{tenant}/mail-settings', [PlatformTenantMailSettingsController::class, 'show'])
            ->name('admin.tenants.mail-settings.show');
        Route::put('tenants/{tenant}/mail-settings', [PlatformTenantMailSettingsController::class, 'update'])
            ->name('admin.tenants.mail-settings.update');
        Route::post('tenants/{tenant}/mail-settings/test', [PlatformTenantMailSettingsController::class, 'sendTest'])
            ->name('admin.tenants.mail-settings.test');

        Route::get('audit-log', [PlatformAuditLogController::class, 'index'])->name('admin.audit-log.index');
    });
