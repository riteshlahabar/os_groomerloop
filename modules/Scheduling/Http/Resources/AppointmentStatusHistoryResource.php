<?php

namespace Modules\Scheduling\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Scheduling\Models\AppointmentStatusHistory;

/**
 * @property-read AppointmentStatusHistory $resource
 */
final class AppointmentStatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'from_status' => $this->resource->from_status?->value,
            'to_status' => $this->resource->to_status->value,
            'changed_by' => $this->resource->changed_by,
            'note' => $this->resource->note,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
