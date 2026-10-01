<?php

namespace Modules\Scheduling\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Contracts\ServiceCatalog;
use Modules\Crm\Contracts\CustomerDirectory;
use Modules\Pets\Contracts\PetDirectory;
use Modules\Scheduling\Models\WaitlistEntry;
use Modules\Team\Contracts\StaffDirectory;

/**
 * @property-read WaitlistEntry $resource
 */
final class WaitlistEntryResource extends JsonResource
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
            'customer_name' => app(CustomerDirectory::class)->nameOf($this->resource->customer_id),

            'pet_id' => $this->resource->pet_id,
            'pet_name' => app(PetDirectory::class)->nameOf($this->resource->pet_id),

            'service_id' => $this->resource->service_id,
            'service_name' => $service?->name,

            'staff_member_id' => $this->resource->staff_member_id,
            'staff_member_name' => $this->resource->staff_member_id === null
                ? null
                : app(StaffDirectory::class)->namesOf([$this->resource->staff_member_id])[$this->resource->staff_member_id] ?? null,

            'requested_date' => $this->resource->requested_date->toDateString(),
            'notes' => $this->resource->notes,

            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'appointment_id' => $this->resource->appointment_id,

            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
