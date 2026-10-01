<?php

namespace Modules\Booking\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Domain\ServiceSummary;

/**
 * What a stranger may see about a service — price and duration, never the internal
 * active/online-visibility flags an authenticated `ServiceResource` would carry, since every
 * service reaching this resource has already passed `ServiceCatalog::bookableOnline()`.
 *
 * @property-read ServiceSummary $resource
 */
final class PublicServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'price' => $this->resource->price(),
            'duration_minutes' => $this->resource->durationMinutes,
            'category_name' => $this->resource->categoryName,
        ];
    }
}
