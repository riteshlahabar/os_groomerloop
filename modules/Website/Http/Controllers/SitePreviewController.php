<?php

namespace Modules\Website\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Tenancy\Support\TenantContext;
use Modules\Website\Domain\PageKey;
use Modules\Website\Services\SiteComposer;
use Modules\Website\Services\WebsiteProvisioner;
use Symfony\Component\HttpFoundation\Response;

/**
 * The owner's view of their unpublished draft (spec §14).
 *
 * Authenticated, tenant-resolved and behind `website.manage` — the same gate as the editor — rather
 * than a signed public link: a preview is the business's own unreleased marketing copy, and a signed
 * URL would be forwardable. There is no token to leak this way.
 *
 * Renders the identical templates the public route renders, from the draft columns, so what the owner
 * approves is what publishing puts live.
 */
final class SitePreviewController
{
    public function __invoke(
        Request $request,
        TenantContext $context,
        WebsiteProvisioner $provisioner,
        SiteComposer $composer,
    ): View {
        $tenant = $context->tenant();

        abort_if($tenant === null, Response::HTTP_NOT_FOUND);

        // By name, for the reason PublicSiteController's own comment records.
        $page = $request->route('page');

        $key = PageKey::tryFrom(is_string($page) ? $page : PageKey::Home->value);

        abort_if($key === null, Response::HTTP_NOT_FOUND);

        $view = $composer->previewPage($tenant, $provisioner->current(), $key);

        abort_if($view === null, Response::HTTP_NOT_FOUND);

        return view($view->template->viewPrefix().'.page', ['site' => $view]);
    }
}
