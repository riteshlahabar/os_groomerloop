<?php

use Illuminate\Support\Facades\Route;
use Modules\Booking\Http\Controllers\PublicCancellationController;
use Modules\Tenancy\Http\Middleware\ResolvePublicTenant;

/*
|--------------------------------------------------------------------------
| Booking web routes (spec §12 self-service cancellation)
|--------------------------------------------------------------------------
|
| `ModuleServiceProvider::registerModuleRoutes()` loads this under the "web" middleware group
| with no prefix and no name grouping (unlike Routes/api.php, which gets `api/v1` + `api.v1.`
| automatically) — so the full path and the route names below are exactly what is registered.
|
| GET shows the page; POST performs the cancellation. Both sit at the identical path so one
| signed URL (`CancellationLinks::urlFor()`) works for either verb — `signed` validates the
| request's URL and query string, never the HTTP method, so the same `?signature=...` a GET
| displays with is still valid when the page's own form POSTs back to it.
|
| `/book/{tenant}` itself (the booking wizard) is a plain closure in the root `routes/web.php`,
| not this module — see that file's own comment for why it is a deliberate top-level path. This
| nests under it without touching that route at all: a 4-segment `book/{tenant}/cancel/{id}`
| cannot match the wizard's fixed 2-segment pattern.
*/
Route::prefix('book/{tenant}/cancel/{appointment}')
    ->where(['appointment' => '[0-9]+'])
    ->middleware(['signed', 'throttle:public', ResolvePublicTenant::class])
    ->group(function (): void {
        Route::get('/', [PublicCancellationController::class, 'show'])->name('public-booking.cancel.show');
        Route::post('/', [PublicCancellationController::class, 'store'])->name('public-booking.cancel.store');
    });
