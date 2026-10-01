<?php

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\Service;

/**
 * @property-read Service $resource
 */
final class ServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),

            'name' => $this->resource->name,
            'description' => $this->resource->description,

            // Both, deliberately. Cents are what any arithmetic must use; the formatted string is
            // what gets rendered, and formatting it once here stops one screen showing "49.9" and
            // another "49.95" because each divided by 100 its own way.
            'price_cents' => $this->resource->price_cents,
            'price' => number_format($this->resource->price_cents / 100, 2, '.', ''),

            'duration_minutes' => $this->resource->duration_minutes,
            'buffer_minutes' => $this->resource->buffer_minutes,

            // What §11 must actually reserve on the calendar, as opposed to what §12 shows the
            // customer. Sent so no client has to remember to add the buffer itself.
            'occupies_minutes' => $this->resource->occupiesMinutes(),

            'is_add_on' => $this->resource->is_add_on,
            'is_bookable_online' => $this->resource->is_bookable_online,

            // The resolved answer, not the raw flags: "may a member of the public book this" is
            // three conditions, and a client recomputing it is how a retired service reappears on
            // a booking page.
            'is_publicly_bookable' => $this->resource->isPubliclyBookable(),

            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'position' => $this->resource->position,

            'category' => $this->whenLoaded(
                'category',
                fn (): ?array => $this->resource->category === null ? null : [
                    'id' => $this->resource->category->getKey(),
                    'name' => $this->resource->category->name,
                    'slug' => $this->resource->category->slug,
                ]
            ),

            'add_ons' => ServiceAddOnResource::collection($this->whenLoaded('addOns')),

            'availability_windows' => ServiceAvailabilityWindowResource::collection(
                $this->whenLoaded('availabilityWindows')
            ),

            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
