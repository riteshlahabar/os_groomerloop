<?php

namespace Modules\Crm\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Crm\Models\CustomerTag;

/**
 * @property-read CustomerTag $resource
 */
final class CustomerTagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'name' => $this->resource->name,

            // The slug is what the index filter takes, so the client never has to derive it.
            'slug' => $this->resource->slug,
            'colour' => $this->resource->colour,
        ];
    }
}
