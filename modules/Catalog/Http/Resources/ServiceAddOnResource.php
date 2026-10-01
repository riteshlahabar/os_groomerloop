<?php

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\Service;

/**
 * An add-on as it appears nested inside its parent service.
 *
 * Its own resource rather than reusing ServiceResource, which would recurse: a service renders its
 * add-ons, and an add-on rendered as a full service would render its own (empty) add-on list and
 * availability windows on every row. This is the short form — what a booking page needs to offer
 * the extra and price it.
 *
 * @property-read Service $resource
 */
final class ServiceAddOnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'name' => $this->resource->name,
            'price_cents' => $this->resource->price_cents,
            'price' => number_format($this->resource->price_cents / 100, 2, '.', ''),
            'duration_minutes' => $this->resource->duration_minutes,
            'occupies_minutes' => $this->resource->occupiesMinutes(),
            'status' => $this->resource->status->value,
        ];
    }
}
