<?php

namespace Modules\Booking\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Team\Domain\StaffSummary;

/**
 * What a stranger may see about a groomer — a name, a title, a bio. Never contact details,
 * working hours or time off; every staff member reaching this resource has already passed
 * `StaffDirectory::bookableOnline()`.
 *
 * @property-read StaffSummary $resource
 */
final class PublicStaffResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'display_name' => $this->resource->displayName,
            'job_title' => $this->resource->jobTitle,
            'bio' => $this->resource->bio,
        ];
    }
}
