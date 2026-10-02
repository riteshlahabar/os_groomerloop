<?php

use Illuminate\Support\Facades\Route;
use Modules\Tenancy\Http\Middleware\ResolvePublicTenant;
use Modules\Tenancy\Http\Middleware\ResolveTenant;
use Modules\Website\Http\Controllers\PublicSiteController;
use Modules\Website\Http\Controllers\SitePreviewController;

/*
|--------------------------------------------------------------------------
| Website web routes (spec §14)
|--------------------------------------------------------------------------
|
| Two surfaces, both server-rendered (D-030 — the one deliberate exception to D-007's
| client-side-fetch rule, because a tenant's marketing site must be crawlable):
|
|   /site/{tenant}[/{page}]   the published site, public, no login
|   /admin/website/preview    the owner's draft, authenticated
|
| `/site/...` is its own top-level path rather than `/public/{tenant}/...`: colliding with the
| public/frontview-assets directory is the trap D-019 already documents, and `/book/{tenant}` was
| given its own path for the same reason.
|
| The subdomain shape the product eventually wants — <tenant>.groomerloop.com — is additive: a
| domain-scoped route group resolving the same controller. It is not built here because wildcard DNS
| and a wildcard certificate cannot be verified from this environment.
*/

Route::prefix('site/{tenant}')
    ->middleware(['throttle:public', ResolvePublicTenant::class])
    ->group(function (): void {
        Route::get('/', PublicSiteController::class)->name('website.public.home');
        Route::get('{page}', PublicSiteController::class)->name('website.public.page');
    });

/*
 * The preview sits under /admin on purpose: it is part of the authenticated app, shares its gate with
 * the editor, and must never be reachable without a login — a draft is unreleased marketing copy.
 *
 * ResolveTenant is named as a class rather than the `tenant` alias so this file does not depend on an
 * alias registered by another module's provider.
 */
Route::middleware(['auth', ResolveTenant::class, 'permission:website.manage'])
    ->prefix('admin/website')
    ->group(function (): void {
        Route::get('preview/{page?}', SitePreviewController::class)->name('admin.website.preview');
    });
