<?php

namespace Modules\Booking\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Contracts\ServiceCatalog;
use Modules\Scheduling\Domain\AppointmentSummary;
use Modules\Team\Contracts\StaffDirectory;

/**
 * Spec §12 step 7, "receives confirmation" — just enough to show a booking succeeded, never the
 * authenticated `AppointmentResource`'s full shape (no internal notes, no raw customer/pet ids).
 *
 * @property-read AppointmentSummary $resource
 */
final class PublicAppointmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $service = app(ServiceCatalog::class)->find($this->resource->serviceId);

        return [
            'id' => $this->resource->id,
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'starts_at' => $this->resource->startsAt->format(DATE_ATOM),
            'ends_at' => $this->resource->endsAt->format(DATE_ATOM),
            'service_name' => $service?->name,
            'staff_member_name' => $this->resource->staffMemberId === null
                ? null
                : app(StaffDirectory::class)->find($this->resource->staffMemberId)?->displayName,
        ];
    }
}
