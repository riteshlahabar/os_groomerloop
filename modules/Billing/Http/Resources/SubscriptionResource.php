<?php

namespace Modules\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Billing\Models\Subscription;

/**
 * @property-read Subscription $resource
 */
final class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),

            // Derived states the SPA would otherwise recompute, each in its own way.
            'is_active' => $this->resource->status->isActive(),
            'is_delinquent' => $this->resource->status->isDelinquent(),
            'on_trial' => $this->resource->onTrial(),
            'entitles_to_plan' => $this->resource->status->entitlesToPlan(),

            'trial_ends_at' => $this->resource->trial_ends_at?->toIso8601String(),
            'current_period_start' => $this->resource->current_period_start?->toIso8601String(),
            'current_period_end' => $this->resource->current_period_end?->toIso8601String(),
            'grace_ends_at' => $this->resource->grace_ends_at?->toIso8601String(),
            'cancelled_at' => $this->resource->cancelled_at?->toIso8601String(),
            'ends_at' => $this->resource->ends_at?->toIso8601String(),

            'failed_payment_count' => $this->resource->failed_payment_count,

            // The gateway's own identifiers are deliberately absent. They are useless to the
            // client and they name our provider to anyone reading the response.
        ];
    }
}
