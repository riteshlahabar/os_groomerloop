<?php

namespace Modules\SuperAdmin\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Notifications\Domain\TenantMailSettingsSnapshot;

/**
 * One business's own SMTP account as the §31 console shows it (`D-032`). Mirrors
 * `PlatformMailSettingsResource`, including its one firm rule: never the password.
 *
 * @property-read TenantMailSettingsSnapshot $resource
 */
final class TenantMailSettingsResource extends JsonResource
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

            // Never the password. Just whether one is stored, so the screen can show "a
            // password is set" without ever being able to display or re-download it.
            'has_password' => $this->resource->hasPassword,

            'from_address' => $this->resource->fromAddress,
            'from_name' => $this->resource->fromName,
            'reply_to' => $this->resource->replyTo,
            'is_enabled' => $this->resource->isEnabled,

            // Enabled but incomplete: stored, switched on, and still not what this business
            // sends through. The screen says so rather than leaving the admin to infer it.
            'is_usable' => $this->resource->isUsable,

            'updated_at' => $this->resource->updatedAt,
        ];
    }
}
