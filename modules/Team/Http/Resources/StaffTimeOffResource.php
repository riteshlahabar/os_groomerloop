<?php

namespace Modules\Team\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Team\Models\StaffTimeOff;

/**
 * @property-read StaffTimeOff $resource
 */
final class StaffTimeOffResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'starts_at' => $this->resource->starts_at->toIso8601String(),
            'ends_at' => $this->resource->ends_at->toIso8601String(),
            'is_all_day' => $this->resource->is_all_day,
            'reason' => $this->resource->reason,
        ];
    }
}
