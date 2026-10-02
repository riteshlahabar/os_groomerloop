<?php

namespace Modules\Website\Http\Controllers\Api\V1;

use Modules\Website\Actions\PublishWebsite;
use Modules\Website\Actions\UnpublishWebsite;
use Modules\Website\Http\Resources\WebsiteResource;
use Modules\Website\Services\WebsiteProvisioner;

/**
 * Going live, and coming back off (spec §14).
 *
 * Its own controller rather than two more methods on WebsiteController: publishing is a different use
 * case from editing — it is the audited moment the business's public face changes, and it is the one
 * action on this screen with a consequence outside the tenant.
 */
final class WebsitePublicationController
{
    public function store(WebsiteProvisioner $provisioner, PublishWebsite $publish): WebsiteResource
    {
        return WebsiteResource::make($publish->execute($provisioner->current()));
    }

    public function destroy(WebsiteProvisioner $provisioner, UnpublishWebsite $unpublish): WebsiteResource
    {
        $site = $unpublish->execute($provisioner->current());

        return WebsiteResource::make($site->load('pages'));
    }
}
