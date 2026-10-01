<?php

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\ServiceAvailabilityWindow;

/**
 * @property-read ServiceAvailabilityWindow $resource
 */
final class ServiceAvailabilityWindowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'day_of_week' => $this->resource->day_of_week->value,
            'day_label' => $this->resource->day_of_week->label(),

            // HH:MM, the same shape the client sent. MySQL hands a TIME column back as "09:00:00",
            // and a form that posted "09:00" and read back "09:00:00" would mark itself dirty on
            // every load.
            'starts_at' => $this->resource->startsAtString(),
            'ends_at' => $this->resource->endsAtString(),
        ];
    }
}
