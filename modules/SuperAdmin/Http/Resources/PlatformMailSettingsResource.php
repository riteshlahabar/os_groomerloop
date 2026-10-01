<?php

namespace Modules\SuperAdmin\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\SuperAdmin\Models\PlatformMailSettings;

/**
 * @property-read PlatformMailSettings $resource
 */
final class PlatformMailSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'host' => $this->resource->host,
            'port' => $this->resource->port,
            'encryption' => $this->resource->encryption?->value,
            'username' => $this->resource->username,

            // Never the password. Just whether one is stored, so the admin screen can show
            // "a password is set" without ever being able to display or re-download it.
            'has_password' => $this->resource->hasPassword(),

            'from_address' => $this->resource->from_address,
            'from_name' => $this->resource->from_name,
            'is_enabled' => $this->resource->is_enabled,

            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
