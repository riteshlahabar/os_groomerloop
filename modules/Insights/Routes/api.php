<?php

use Illuminate\Support\Facades\Route;
use Modules\Insights\Http\Controllers\Api\V1\ReportsController;

/*
|--------------------------------------------------------------------------
| Insights API routes
|--------------------------------------------------------------------------
|
| Registered under /api/v1 with the "api" middleware group.
|
| reports.view matches the §5 matrix (Modules\Identity\Domain\Role) and the admin.reports sidebar
| item. entitlement:business_insights is a presence check only — every plan has at least the
| Basic grade, so this never refuses a request on its own; DashboardReportBuilder is what grades
| individual metrics against the tenant's actual grade, because "may use the page at all" and
| "may see this particular row" are different questions.
*/

Route::middleware(['auth:sanctum', 'tenant', 'permission:reports.view', 'entitlement:business_insights'])
    ->group(function (): void {
        Route::get('reports/dashboard', ReportsController::class)->name('reports.dashboard');
    });
