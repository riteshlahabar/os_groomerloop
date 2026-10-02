<?php

namespace Modules\Website\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Tenancy\Support\TenantContext;
use Modules\Website\Domain\PageKey;
use Modules\Website\Models\Website;
use Modules\Website\Services\SiteComposer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders a tenant's published site (spec §14).
 *
 * **This is the one surface in the product that renders server-side** (`D-030`). Every other page is
 * a Blade shell that fetches `/api/v1` client-side (`D-007`); a tenant's marketing site has to be
 * readable by a crawler that runs no JavaScript, so the HTML arrives complete. The exception is
 * deliberately narrow: only these public pages, never the authenticated editor.
 *
 * The tenant is established by ResolvePublicTenant from the `{tenant}` slug — the same middleware the
 * §12 booking page's API uses (`D-024`) — which also 404s an unknown, suspended or cancelled
 * business before this controller runs.
 */
final class PublicSiteController
{
    public function __invoke(
        Request $request,
        TenantContext $context,
        SiteComposer $composer,
    ): View {
        $tenant = $context->tenant();

        abort_if($tenant === null, Response::HTTP_NOT_FOUND);

        // Read by name, never as a method argument. Laravel fills a non-class argument from the
        // leftover route parameters *positionally* when no parameter shares its name, so a
        // `?string $page` argument on this route silently receives the `{tenant}` slug — which then
        // fails PageKey::tryFrom() and 404s every published page.
        $page = $request->route('page');

        $key = PageKey::tryFrom(is_string($page) ? $page : PageKey::Home->value);

        abort_if($key === null, Response::HTTP_NOT_FOUND);

        // Tenant-scoped by the global scope, so this can only ever be the resolved tenant's site.
        $site = Website::query()->with('pages')->first();

        abort_if($site === null, Response::HTTP_NOT_FOUND);

        $view = $composer->publicPage($tenant, $site, $key);

        // Null means "not published" or "page switched off". Both are a 404 rather than a 403: a
        // stranger learns nothing about whether a draft exists, which is the same reasoning
        // ResolvePublicTenant applies to a suspended business.
        abort_if($view === null, Response::HTTP_NOT_FOUND);

        return view($view->template->viewPrefix().'.page', ['site' => $view]);
    }
}
