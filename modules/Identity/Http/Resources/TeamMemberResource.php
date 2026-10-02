<?php

namespace Modules\Identity\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the "who can sign in to this business" list (spec §23).
 *
 * Deliberately slimmer than `UserResource`, which is the signed-in user's own bootstrap payload
 * and carries `permissions` — the full resolved permission list. That is right for "what may I
 * do", and wrong repeated across every row of a list: it is a page of data nothing renders, and
 * it hands every team-manager a complete map of everyone else's capabilities when the screen
 * only needs their role name.
 *
 * @property-read User $resource
 */
final class TeamMemberResource extends JsonResource
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

            // Lets the client mark "this is you" and suppress controls that would only be
            // refused — a user may not change their own role. Still never the enforcement
            // point: UserPolicy re-decides on the write.
            'is_self' => $this->resource->getKey() === $request->user()?->getKey(),

            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
