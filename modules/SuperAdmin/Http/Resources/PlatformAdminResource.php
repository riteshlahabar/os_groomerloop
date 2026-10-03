<?php

namespace Modules\SuperAdmin\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One GroomerLoop staff account as the §31 console lists it (`D-034`).
 *
 * `is_you` is here so the screen can grey out its own Remove button rather than offering an
 * action that `DeletePlatformAdmin` will refuse — the refusal still stands server-side, this just
 * stops the UI promising something it cannot do.
 *
 * @property-read User $resource
 */
final class PlatformAdminResource extends JsonResource
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
            'is_you' => $request->user()?->getKey() === $this->resource->getKey(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
