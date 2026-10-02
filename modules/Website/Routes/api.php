<?php

use Illuminate\Support\Facades\Route;
use Modules\Website\Http\Controllers\Api\V1\WebsiteController;
use Modules\Website\Http\Controllers\Api\V1\WebsitePageController;
use Modules\Website\Http\Controllers\Api\V1\WebsitePublicationController;

/*
|--------------------------------------------------------------------------
| Website API routes (spec §14)
|--------------------------------------------------------------------------
|
| Registered under /api/v1 with the "api" middleware group.
|
| Three gates, in this order, none of them repeated in a controller:
|
|   auth:sanctum              — the editor is for the business, never the public
|   tenant                    — ResolveTenant, so every query below is scoped (invariant #1)
|   entitlement:basic_website — plan gating through the one entitlement service, by feature key and
|                               never by plan name (invariant #3). Every plan grants this feature
|                               today; the gate exists so packaging can change in the seeder alone,
|                               and it answers 402, not 403.
|   permission:website.manage — the §5 role matrix. Reading the draft needs it too: an unpublished
|                               site is the business's own unreleased copy, so there is no
|                               view-only audience for it the way there is for a customer list.
|
| The published site itself is NOT here — it is a server-rendered web route (Routes/web.php, D-030).
*/

Route::middleware([
    'auth:sanctum',
    'tenant',
    'entitlement:basic_website',
    'permission:website.manage',
])->group(function (): void {
    Route::get('website', [WebsiteController::class, 'show'])->name('website.show');
    Route::put('website', [WebsiteController::class, 'update'])->name('website.update');

    Route::put('website/pages/{pageKey}', [WebsitePageController::class, 'update'])
        ->name('website.pages.update');

    // Publication is a POST/DELETE pair on one resource rather than two verbs named publish and
    // unpublish: going live creates the public site, taking it down removes it.
    Route::post('website/publication', [WebsitePublicationController::class, 'store'])
        ->name('website.publication.store');
    Route::delete('website/publication', [WebsitePublicationController::class, 'destroy'])
        ->name('website.publication.destroy');
});
