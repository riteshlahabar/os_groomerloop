<?php

namespace Modules\Pets\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Pets\Models\Species;

/**
 * @property-read Species $resource
 */
final class SpeciesResource extends JsonResource
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

            // Counted rather than listed, the same reason `ServiceCategoryResource` counts
            // services: a settings screen wants to warn (and refuse) before retiring a species
            // still in use, without loading every pet that has it.
            'pets_count' => $this->whenCounted('pets'),
        ];
    }
}
