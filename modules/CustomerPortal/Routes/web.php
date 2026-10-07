<?php

use Illuminate\Support\Facades\Route;
use Modules\CustomerPortal\Http\Controllers\AccountClaimController;
use Modules\Tenancy\Http\Middleware\ResolveCustomerTenant;
use Modules\Tenancy\Support\TenantContext;

/*
|--------------------------------------------------------------------------
| Customer Portal web routes (D-043)
|--------------------------------------------------------------------------
|
| ModuleServiceProvider::registerModuleRoutes() loads this under the "web" middleware group with
| no prefix and no name grouping (unlike Routes/api.php, which gets `api/v1` + `api.v1.`
| automatically) — so the full path and route names below are exactly what is registered.
|
| GET shows the "set your password" page; POST submits it. Both sit at the identical path so one
| signed URL (AccountClaimLinks::urlFor()) works for either verb — the same shape
| Booking\Routes\web.php's self-service cancellation link already established, and for the
| identical reason: `signed` validates the URL and query string, never the HTTP method.
*/
Route::prefix('portal/{tenant}/claim/{customer}')
    ->where(['customer' => '[0-9]+'])
    ->middleware(['signed', 'throttle:public', ResolveCustomerTenant::class])
    ->group(function (): void {
        Route::get('/', [AccountClaimController::class, 'show'])->name('customer-portal.claim.show');
        Route::post('/', [AccountClaimController::class, 'store'])->name('customer-portal.claim.store');
    });

/*
|--------------------------------------------------------------------------
| Customer Portal pages (D-043 Phase 4)
|--------------------------------------------------------------------------
|
| Blade shells only, same rule as `/admin` (`D-007`): every page's real data comes from a
| client-side `fetch()` against the Routes/api.php endpoints above. Each closure's only job is
| handing the already-resolved `Tenant` to the view for building those API/page URLs and showing
| the business name — the same thing `AccountClaimController::show()` already does.
|
| No `guest:customer` guard on the login page: `routes/web.php`'s own staff `/login` route
| carries none either, and an already-authenticated visitor landing there is harmless.
*/
Route::prefix('portal/{tenant}')
    ->middleware(ResolveCustomerTenant::class)
    ->group(function (): void {
        Route::get('login', fn (TenantContext $context) => view('customer-portal.login', [
            'tenant' => $context->tenant(),
        ]))->name('customer-portal.login');

        Route::middleware('auth:customer')->group(function (): void {
            Route::get('/', fn (TenantContext $context) => view('customer-portal.dashboard', [
                'tenant' => $context->tenant(),
            ]))->name('customer-portal.dashboard');

            Route::get('appointments', fn (TenantContext $context) => view('customer-portal.appointments', [
                'tenant' => $context->tenant(),
            ]))->name('customer-portal.appointments-page');

            Route::get('pets', fn (TenantContext $context) => view('customer-portal.pets', [
                'tenant' => $context->tenant(),
            ]))->name('customer-portal.pets-page');
        });
    });
