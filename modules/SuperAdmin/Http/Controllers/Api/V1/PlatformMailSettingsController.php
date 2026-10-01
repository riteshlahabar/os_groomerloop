<?php

namespace Modules\SuperAdmin\Http\Controllers\Api\V1;

use Modules\SuperAdmin\Actions\UpdatePlatformMailSettings;
use Modules\SuperAdmin\Http\Requests\UpdatePlatformMailSettingsRequest;
use Modules\SuperAdmin\Http\Resources\PlatformMailSettingsResource;
use Modules\SuperAdmin\Models\PlatformMailSettings;

/**
 * The platform's own outbound-email configuration (spec §13, §31) — GroomerLoop Admin only,
 * reached outside tenant scope entirely (see Routes/api.php).
 */
final class PlatformMailSettingsController
{
    public function show(): PlatformMailSettingsResource
    {
        return PlatformMailSettingsResource::make(PlatformMailSettings::current());
    }

    public function update(
        UpdatePlatformMailSettingsRequest $request,
        UpdatePlatformMailSettings $update,
    ): PlatformMailSettingsResource {
        return PlatformMailSettingsResource::make(
            $update->execute($request->settingsAttributes())
        );
    }
}
