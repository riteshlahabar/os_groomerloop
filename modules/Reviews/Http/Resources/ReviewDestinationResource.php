<?php

namespace Modules\Reviews\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reviews\Models\ReviewDestination;

/**
 * @property-read ReviewDestination $resource
 */
final class ReviewDestinationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'label' => $this->resource->label,
            'url' => $this->resource->url,
            'position' => $this->resource->position,
        ];
    }
}
