<?php

namespace Modules\Team\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Team\Models\StaffMember;

/**
 * @property-read StaffMember $resource
 */
final class StaffMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'display_name' => $this->resource->display_name,
            'job_title' => $this->resource->job_title,
            'bio' => $this->resource->bio,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'is_bookable_online' => $this->resource->is_bookable_online,
            'is_publicly_bookable' => $this->resource->isPubliclyBookable(),
            'position' => $this->resource->position,
            'user_id' => $this->resource->user_id,
            'has_working_hours' => $this->resource->relationLoaded('workingHours')
                ? $this->resource->workingHours->isNotEmpty()
                : $this->resource->hasWorkingHours(),

            // Attached by the controller — not every endpoint resolves the eligibility pivot, the
            // same way a plain Eloquent relation would only appear `whenLoaded`.
            'service_ids' => $this->when(
                isset($this->resource->service_ids),
                fn (): array => $this->resource->service_ids
            ),

            'working_hours' => StaffWorkingHourResource::collection($this->whenLoaded('workingHours')),
            'time_off' => StaffTimeOffResource::collection($this->whenLoaded('timeOff')),

            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
