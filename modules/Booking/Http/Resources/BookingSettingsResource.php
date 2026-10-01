<?php

namespace Modules\Booking\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Booking\Models\BookingSettings;

/**
 * @property-read BookingSettings $resource
 */
final class BookingSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'lead_time_minutes' => $this->resource->lead_time_minutes,
            'cancellation_window_hours' => $this->resource->cancellation_window_hours,
            'confirmation_mode' => $this->resource->confirmation_mode->value,
            'confirmation_mode_label' => $this->resource->confirmation_mode->label(),
        ];
    }
}
