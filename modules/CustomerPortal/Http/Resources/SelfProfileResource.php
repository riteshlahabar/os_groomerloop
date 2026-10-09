<?php

namespace Modules\CustomerPortal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Crm\Domain\CustomerSelfProfile;

/**
 * A customer's own profile, built from `CustomerSelfProfile` — a DTO that carries only the
 * editable half of their record, so there is no staff classification, note or consent flag here to
 * remember to omit (see that class's docblock).
 *
 * @property-read CustomerSelfProfile $resource
 */
final class SelfProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'first_name' => $this->resource->firstName,
            'last_name' => $this->resource->lastName,
            'full_name' => $this->resource->fullName(),

            // Sent so the form can show it, flagged so the form knows not to let it be typed in.
            // The field is not accepted on write at all (`UpdateSelfProfileRequest`), so this is
            // the page being told the truth rather than being trusted to enforce it.
            'email' => $this->resource->email,
            'email_is_editable' => false,

            'phone' => $this->resource->phone,
            'address_line_1' => $this->resource->addressLine1,
            'address_line_2' => $this->resource->addressLine2,
            'city' => $this->resource->city,
            'state' => $this->resource->state,
            'postal_code' => $this->resource->postalCode,
            'country' => $this->resource->country,
            'customer_since' => $this->resource->customerSince,
        ];
    }
}
