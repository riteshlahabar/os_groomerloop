<?php

namespace Modules\Scheduling\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Scheduling\Models\BusinessHour;

/**
 * @property-read BusinessHour $resource
 */
final class BusinessHourResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'day_of_week' => $this->resource->day_of_week->value,
            'day_of_week_label' => $this->resource->day_of_week->label(),
            'starts_at' => $this->resource->startsAtString(),
            'ends_at' => $this->resource->endsAtString(),
        ];
    }
}
