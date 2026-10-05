<?php

namespace Modules\Reviews\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Crm\Contracts\CustomerDirectory;
use Modules\Reviews\Models\Review;

/**
 * @property-read Review $resource
 */
final class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'platform' => $this->resource->platform,

            'customer_id' => $this->resource->customer_id,
            'customer_name' => $this->resource->customer_id === null
                ? null
                : app(CustomerDirectory::class)->nameOf($this->resource->customer_id),

            'rating' => $this->resource->rating,
            'comment' => $this->resource->comment,
            'reviewed_at' => $this->resource->reviewed_at->toDateString(),

            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
