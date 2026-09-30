<?php

namespace Modules\Onboarding\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Onboarding\Models\BusinessProfile;

/**
 * @property-read BusinessProfile $resource
 */
final class BusinessProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'legal_name' => $this->resource->legal_name,
            'contact_name' => $this->resource->contact_name,
            'contact_email' => $this->resource->contact_email,
            'contact_phone' => $this->resource->contact_phone,

            'address_line_1' => $this->resource->address_line_1,
            'address_line_2' => $this->resource->address_line_2,
            'city' => $this->resource->city,
            'state' => $this->resource->state,
            'postal_code' => $this->resource->postal_code,
            'country' => $this->resource->country,

            'service_area' => $this->resource->service_area,
            'description' => $this->resource->description,

            // Drives the §7 checklist row, so the client can explain what is still missing
            // without duplicating the sufficiency rule.
            'is_sufficient' => $this->resource->isSufficient(),
        ];
    }
}
