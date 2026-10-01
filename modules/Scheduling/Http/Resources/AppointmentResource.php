<?php

namespace Modules\Scheduling\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Contracts\ServiceCatalog;
use Modules\Crm\Contracts\CustomerDirectory;
use Modules\Pets\Contracts\PetDirectory;
use Modules\Scheduling\Models\Appointment;
use Modules\Team\Contracts\StaffDirectory;

/**
 * @property-read Appointment $resource
 */
final class AppointmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $service = app(ServiceCatalog::class)->find($this->resource->service_id);

        return [
            'id' => $this->resource->getKey(),

            'customer_id' => $this->resource->customer_id,
            // Resolved through each owning module's contract, never a relationship — Scheduling
            // may not load the Customer/Pet/Service/StaffMember models (D-007). A calendar page
            // renders many of these; each contract memoises its own lookups per request, so this
            // costs one query per distinct id rather than one per row.
            'customer_name' => app(CustomerDirectory::class)->nameOf($this->resource->customer_id),

            'pet_id' => $this->resource->pet_id,
            'pet_name' => app(PetDirectory::class)->nameOf($this->resource->pet_id),

            'service_id' => $this->resource->service_id,
            'service_name' => $service?->name,
            'service_price' => $service?->price(),

            'staff_member_id' => $this->resource->staff_member_id,
            'staff_member_name' => $this->resource->staff_member_id === null
                ? null
                : app(StaffDirectory::class)->namesOf([$this->resource->staff_member_id])[$this->resource->staff_member_id] ?? null,

            'starts_at' => $this->resource->starts_at->toIso8601String(),
            'ends_at' => $this->resource->ends_at->toIso8601String(),

            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),

            'customer_notes' => $this->resource->customer_notes,

            // Staff commentary on the booking itself. Unlike Pets' internal_notes (§9 gives that
            // its own permission because a pet's handling history outlives any one appointment),
            // §11 draws no separate visibility tier for an appointment's own notes — anyone who
            // can see the appointment at all already holds appointments.view/manage.
            'internal_notes' => $this->resource->internal_notes,

            'recurrence_group_id' => $this->resource->recurrence_group_id,

            'add_ons' => AppointmentAddOnResource::collection($this->whenLoaded('addOns')),
            'status_history' => AppointmentStatusHistoryResource::collection($this->whenLoaded('statusHistory')),

            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
