<?php

namespace Modules\Website\Http\Controllers\Api\V1;

use Modules\Website\Actions\UploadWebsiteImage;
use Modules\Website\Http\Requests\UploadWebsiteImageRequest;
use Modules\Website\Http\Resources\WebsiteResource;
use Modules\Website\Services\WebsiteProvisioner;

/**
 * Upload/remove the two §14 branding images (spec §28, `D-016`) — `{field}` is `logo` or `hero`,
 * constrained by the route (`Routes/api.php`), never an arbitrary column name.
 */
final class WebsiteImageController
{
    public function store(
        string $field,
        UploadWebsiteImageRequest $request,
        WebsiteProvisioner $provisioner,
        UploadWebsiteImage $uploader,
    ): WebsiteResource {
        $site = $uploader->execute($provisioner->current(), $field, $request->file('image'));

        return WebsiteResource::make($site->load('pages'));
    }

    public function destroy(
        string $field,
        WebsiteProvisioner $provisioner,
        UploadWebsiteImage $uploader,
    ): WebsiteResource {
        $site = $uploader->remove($provisioner->current(), $field);

        return WebsiteResource::make($site->load('pages'));
    }
}
