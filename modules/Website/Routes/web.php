<?php

use Illuminate\Support\Facades\Route;
use Modules\Tenancy\Http\Middleware\ResolvePublicTenantById;
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
|   /{tenant}/{slug}[/{page}]   the published site, public, no login
|   /admin/website/preview      the owner's draft, authenticated
|
| The public path is keyed by the tenant's numeric id, not its slug (owner's request, 2026-10-03)
| — the `{slug}` segment after it is decorative, read by no code, kept only so the URL stays
| readable and a slug rename never breaks a link already handed to a customer. Resolved by
| ResolvePublicTenantById rather than the slug-keyed ResolvePublicTenant the §12 booking widget
| uses — see that class's docblock for why this is two middlewares, not one with a mode flag.
|
| This no longer collides with /book/{tenant} or public/frontview-assets the way a literal `site`
| prefix once needed to avoid (D-019) — a leading numeric segment cannot match either path.
|
| The subdomain shape the product eventually wants — <tenant>.groomerloop.com — is additive: a
| domain-scoped route group resolving the same controller. It is not built here because wildcard DNS
| and a wildcard certificate cannot be verified from this environment.
*/

Route::prefix('{tenant}/{slug}')
    ->where(['tenant' => '[0-9]+'])
    ->middleware(['throttle:public', ResolvePublicTenantById::class])
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
