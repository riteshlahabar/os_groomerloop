<?php

namespace Modules\Identity\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The shape the React SPA receives for a user.
 *
 * @property-read User $resource
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'role' => $this->resource->role?->value,
            'role_label' => $this->resource->role?->label(),
            'email_verified' => $this->resource->email_verified_at !== null,

            // The SPA uses this to hide controls the server would refuse anyway. It is a UX
            // convenience and never the enforcement point — every endpoint re-checks.
            'permissions' => $this->resource->permissionNames(),

            'business' => $this->whenLoaded('tenant', fn () => [
                'id' => $this->resource->tenant->getKey(),
                'name' => $this->resource->tenant->name,
                'slug' => $this->resource->tenant->slug,
                'timezone' => $this->resource->tenant->timezone,
                'status' => $this->resource->tenant->status->value,
            ]),
        ];
    }
}
