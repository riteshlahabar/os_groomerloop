<?php

namespace Modules\Website\Http\Controllers\Api\V1;

use Modules\Website\Actions\UpdateWebsitePage;
use Modules\Website\Http\Requests\UpdateWebsitePageRequest;
use Modules\Website\Http\Resources\WebsitePageResource;
use Modules\Website\Models\WebsitePage;
use Modules\Website\Services\WebsiteProvisioner;
use Symfony\Component\HttpFoundation\Response;

/**
 * One page of the tenant's site (spec §14).
 *
 * Addressed by its fixed page key — `home`, `services`, … — not by a database id. The page set is
 * fixed by the product (§37: this is not a website builder), so the key is the stable, guessable
 * identifier and an id would only invite a client to cache one that belongs to another tenant.
 */
final class WebsitePageController
{
    public function update(
        UpdateWebsitePageRequest $request,
        WebsiteProvisioner $provisioner,
        UpdateWebsitePage $update,
    ): WebsitePageResource {
        $site = $provisioner->current();
        $key = $request->pageKey();

        // Found through the tenant's own site rather than by route model binding: a page is only ever
        // reachable via the site that owns it, so there is no bindable id to get wrong (D-014).
        $page = $site->pages->first(fn (WebsitePage $candidate): bool => $candidate->key === $key);

        abort_if($page === null, Response::HTTP_NOT_FOUND);

        return WebsitePageResource::make($update->execute($page, $request->pageAttributes()));
    }
}
