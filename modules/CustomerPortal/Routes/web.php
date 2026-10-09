<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Crm\Contracts\CustomerDirectory;
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
| The login page sends an already-signed-in customer to their dashboard instead of rendering the
| form. An earlier version of this comment called that case "harmless"; it is not. The owner hit
| exactly it (2026-10-09): the tenant's own website showed a "Login" button to a customer who was
| already signed in, they clicked it, and were asked for credentials they had just given. The
| button itself is fixed in Website's three headers, which now read `$site->customerIsSignedIn`
| and offer "My Account" — this redirect closes the same hole for a bookmark, a Back button or any
| other way of arriving here with a live session.
*/
Route::prefix('portal/{tenant}')
    ->middleware(ResolveCustomerTenant::class)
    ->group(function (): void {
        Route::get('login', function (TenantContext $context) {
            if (auth()->guard('customer')->check()) {
                return redirect()->route('customer-portal.dashboard', ['tenant' => $context->tenant()->getKey()]);
            }

            return view('customer-portal.login', ['tenant' => $context->tenant()]);
        })->name('customer-portal.login');

        // Each closure resolves the signed-in customer's display name through CustomerDirectory
        // (D-007) rather than calling a method on the Authenticatable model directly — the same
        // boundary MeController already respects via `contactDetailsOf()`.
        Route::middleware('auth:customer')->group(function (): void {
            Route::get('/', fn (TenantContext $context, Request $request, CustomerDirectory $customers) => view('customer-portal.dashboard', [
                'tenant' => $context->tenant(),
                'customerName' => $customers->contactDetailsOf((int) $request->user('customer')->getAuthIdentifier())?->fullName,
            ]))->name('customer-portal.dashboard');

            Route::get('appointments', fn (TenantContext $context, Request $request, CustomerDirectory $customers) => view('customer-portal.appointments', [
                'tenant' => $context->tenant(),
                'customerName' => $customers->contactDetailsOf((int) $request->user('customer')->getAuthIdentifier())?->fullName,
            ]))->name('customer-portal.appointments-page');

            Route::get('pets', fn (TenantContext $context, Request $request, CustomerDirectory $customers) => view('customer-portal.pets', [
                'tenant' => $context->tenant(),
                'customerName' => $customers->contactDetailsOf((int) $request->user('customer')->getAuthIdentifier())?->fullName,
            ]))->name('customer-portal.pets-page');
        });
    });
