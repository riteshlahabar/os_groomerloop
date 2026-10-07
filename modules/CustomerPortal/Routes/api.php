<?php

use Illuminate\Support\Facades\Route;
use Modules\CustomerPortal\Http\Controllers\Api\V1\AppointmentController;
use Modules\CustomerPortal\Http\Controllers\Api\V1\ClaimLinkRequestController;
use Modules\CustomerPortal\Http\Controllers\Api\V1\LoginController;
use Modules\CustomerPortal\Http\Controllers\Api\V1\LogoutController;
use Modules\CustomerPortal\Http\Controllers\Api\V1\MeController;
use Modules\CustomerPortal\Http\Controllers\Api\V1\PetController;
use Modules\Tenancy\Http\Middleware\ResolveCustomerTenant;

/*
|--------------------------------------------------------------------------
| Customer Portal API routes (D-043)
|--------------------------------------------------------------------------
|
| Registered under /api/v1 with the "api" middleware group.
|
| Every route here — guest and authenticated alike — carries ResolveCustomerTenant explicitly,
| unlike Identity's own login route, which deliberately omits the `tenant` middleware because
| that one reads the tenant off the already-authenticated user (there is none at login).
| ResolveCustomerTenant instead reads the tenant from the URL, because this guard's own login
| endpoint has no actor yet and still needs to know which tenant's Customer row to check
| credentials against — see that middleware's own docblock.
*/
Route::prefix('customer/{tenant}')
    ->middleware(ResolveCustomerTenant::class)
    ->group(function (): void {

        // --- Guest routes: no customer session yet ------------------------------------------
        // throttle:auth is keyed on both IP and submitted email, the same limiter Identity's own
        // login uses, for the same reason: spraying one password across many accounts is
        // throttled just as hard as guessing many passwords for one.
        Route::middleware('throttle:auth')->group(function (): void {
            Route::post('login', LoginController::class)->name('customer-portal.login');
            Route::post('claim-link', ClaimLinkRequestController::class)->name('customer-portal.claim-link');
        });

        // --- Authenticated routes ------------------------------------------------------------
        Route::middleware('auth:customer')->group(function (): void {
            Route::post('logout', LogoutController::class)->name('customer-portal.logout');
            Route::get('me', MeController::class)->name('customer-portal.me');
            Route::get('appointments', AppointmentController::class)->name('customer-portal.appointments');
            Route::get('pets', PetController::class)->name('customer-portal.pets');
        });
    });
