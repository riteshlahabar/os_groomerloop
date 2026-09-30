<?php

namespace Modules\Entitlements\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Entitlements\Domain\Feature;
use Modules\Entitlements\Models\Plan;

/**
 * @property-read Plan $resource
 */
final class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $grants = $this->resource->grants();

        return [
            'id' => $this->resource->getKey(),
            'key' => $this->resource->key,
            'name' => $this->resource->name,
            'tagline' => $this->resource->tagline,

            // Both forms: cents for arithmetic, dollars for display, so the SPA never has to
            // divide money itself and no two screens round it differently.
            'price_cents' => $this->resource->price_cents,
            'price' => number_format($this->resource->priceInDollars(), 2, '.', ''),
            'currency' => $this->resource->currency,
            'billing_interval' => $this->resource->billing_interval,
            'is_default' => $this->resource->is_default,

            // Every §25 row, present or not, so the pricing page can render the full comparison
            // grid from one response instead of inferring the "--" cells.
            'features' => array_map(
                static fn (Feature $feature): array => [
                    'key' => $feature->value,
                    'label' => $feature->label(),
                    'included' => isset($grants[$feature->value]),
                    'grade' => ($grants[$feature->value] ?? null)?->value,
                ],
                Feature::all(),
            ),
        ];
    }
}
