<?php

namespace Modules\Team\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Team\Models\StaffWorkingHour;

/**
 * @property-read StaffWorkingHour $resource
 */
final class StaffWorkingHourResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'day_of_week' => $this->resource->day_of_week->value,
            'day_of_week_label' => $this->resource->day_of_week->label(),

            // Not the raw casts — MySQL hands a TIME column back as "09:00:00", and a client that
            // sent "09:00" should read the same shape it wrote.
            'starts_at' => $this->resource->startsAtString(),
            'ends_at' => $this->resource->endsAtString(),
        ];
    }
}
