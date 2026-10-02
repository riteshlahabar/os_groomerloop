<?php

namespace Modules\Website\Http\Controllers\Api\V1;

use Modules\Website\Actions\UpdateWebsite;
use Modules\Website\Http\Requests\UpdateWebsiteRequest;
use Modules\Website\Http\Resources\WebsiteResource;
use Modules\Website\Services\WebsiteProvisioner;

/**
 * The tenant's site and its settings (spec §14).
 *
 * There is no index and no store: one tenant has exactly one site, provisioned on first read, so the
 * only two verbs that make sense are "show me mine" and "change mine".
 */
final class WebsiteController
{
    public function show(WebsiteProvisioner $provisioner): WebsiteResource
    {
        return WebsiteResource::make($provisioner->current());
    }

    public function update(
        UpdateWebsiteRequest $request,
        WebsiteProvisioner $provisioner,
        UpdateWebsite $update,
    ): WebsiteResource {
        $site = $update->execute($provisioner->current(), $request->settings());

        return WebsiteResource::make($site->load('pages'));
    }
}
