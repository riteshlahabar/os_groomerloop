<?php

namespace Modules\CustomerPortal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Contracts\ServiceCatalog;
use Modules\Scheduling\Domain\AppointmentSummary;
use Modules\Team\Contracts\StaffDirectory;

/**
 * A customer's own view of one of their appointments — deliberately not Scheduling's own
 * `AppointmentResource` (which carries `internal_notes`, staff-only), the same split
 * `Booking\Http\Resources\PublicAppointmentResource` already draws for the public booking flow.
 * No `customer_id`/`pet_id` either: this is always "my" appointment and "my" pet, never a record
 * naming a family's internal ids back to them.
 *
 * @property-read AppointmentSummary $resource
 */
final class AppointmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $service = app(ServiceCatalog::class)->find($this->resource->serviceId);

        return [
            'id' => $this->resource->id,
            'service_name' => $service?->name,
            'staff_member_name' => $this->resource->staffMemberId === null
                ? null
                : app(StaffDirectory::class)->find($this->resource->staffMemberId)?->displayName,
            'starts_at' => $this->resource->startsAt->format(DATE_ATOM),
            'ends_at' => $this->resource->endsAt->format(DATE_ATOM),
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'customer_notes' => $this->resource->customerNotes,
        ];
    }
}
