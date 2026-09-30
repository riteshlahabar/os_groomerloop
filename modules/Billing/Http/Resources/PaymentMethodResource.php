<?php

namespace Modules\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Billing\Models\PaymentMethod;

/**
 * @property-read PaymentMethod $resource
 */
final class PaymentMethodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'brand' => $this->resource->brand,
            'last_four' => $this->resource->last_four,
            'expiry_month' => $this->resource->expiry_month,
            'expiry_year' => $this->resource->expiry_year,
            'is_default' => $this->resource->is_default,
            'is_expired' => $this->resource->isExpired(),
            'label' => $this->resource->describe(),

            // The gateway token is never serialised. It is the handle used to charge the
            // card, and no screen has a reason to display it.
        ];
    }
}
