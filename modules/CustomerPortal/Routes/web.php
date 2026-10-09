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
                return redirect()->route('customer-portal.profile-page', ['tenant' => $context->tenant()->getKey()]);
            }

            return view('customer-portal.login', ['tenant' => $context->tenant()]);
        })->name('customer-portal.login');

        /*
         * The portal's index is **Profile**, not a Dashboard (owner, 2026-10-09). The old
         * dashboard was a landing page with nothing on it the two other pages did not already
         * show, so it became the one screen a customer actually needs that did not exist: their
         * own editable record. The URL is unchanged — `/portal/{tenant}` is still the index — but
         * the route *name* moved from `customer-portal.dashboard` to `customer-portal.profile-page`
         * (`-page` matching its two siblings, so it cannot be confused with the API's
         * `customer-portal.profile`).
         *
         * Each closure reads the signed-in customer through `CustomerDirectory` (`D-007`), never a
         * method on the `Authenticatable` model — now `selfProfileOf()` rather than
         * `contactDetailsOf()`, because the shell's profile box also wants "customer since".
         */
        Route::middleware('auth:customer')->group(function (): void {
            // Initials for the profile box, because §28 has no customer photo upload — see the
            // layout's note on why the bundle's avatar-and-upload control is not reproduced.
            $initials = static function (string $name): string {
                $parts = preg_split('/\s+/', trim($name)) ?: [];

                $letters = implode('', array_map(
                    static fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)),
                    array_slice(array_filter($parts), 0, 2),
                ));

                return $letters === '' ? '?' : $letters;
            };

            $shell = static function (TenantContext $context, Request $request, CustomerDirectory $customers, string $view) use ($initials) {
                $profile = $customers->selfProfileOf((int) $request->user('customer')->getAuthIdentifier());

                return view($view, [
                    'tenant' => $context->tenant(),
                    'customerName' => $profile?->fullName(),
                    'customerInitials' => $profile === null ? '?' : $initials($profile->fullName()),
                    'customerSince' => $profile?->customerSince,
                ]);
            };

            Route::get('/', fn (TenantContext $context, Request $request, CustomerDirectory $customers) => $shell($context, $request, $customers, 'customer-portal.profile'))
                ->name('customer-portal.profile-page');

            Route::get('appointments', fn (TenantContext $context, Request $request, CustomerDirectory $customers) => $shell($context, $request, $customers, 'customer-portal.appointments'))
                ->name('customer-portal.appointments-page');

            Route::get('pets', fn (TenantContext $context, Request $request, CustomerDirectory $customers) => $shell($context, $request, $customers, 'customer-portal.pets'))
                ->name('customer-portal.pets-page');
        });
    });
