<?php

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\ServiceCategory;

/**
 * @property-read ServiceCategory $resource
 */
final class ServiceCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'position' => $this->resource->position,

            // Counted rather than listed: a settings screen wants to warn before retiring a
            // category that still has a menu behind it, and does not need the menu itself.
            'services_count' => $this->whenCounted('services'),
        ];
    }
}
