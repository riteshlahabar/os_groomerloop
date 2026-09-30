<?php

namespace Modules\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Identity\Models\Invitation;

/**
 * @property-read Invitation $resource
 */
final class InvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'email' => $this->resource->email,
            'role' => $this->resource->role->value,
            'role_label' => $this->resource->role->label(),
            'pending' => $this->resource->isPending(),
            'expired' => $this->resource->isExpired(),
            'expires_at' => $this->resource->expires_at->toIso8601String(),
            'accepted_at' => $this->resource->accepted_at?->toIso8601String(),

            // token_hash is never exposed. There is nothing useful in it, and publishing it would
            // hand out the only thing the acceptance lookup checks.
        ];
    }
}
