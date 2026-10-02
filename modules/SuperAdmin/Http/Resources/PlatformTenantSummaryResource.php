<?php

namespace Modules\SuperAdmin\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Billing\Contracts\SubscriptionDirectory;
use Modules\Entitlements\Contracts\PlanRegistry;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;

/**
 * One row of spec §31's "Tenant search" roster — enough to triage a business without opening
 * it: plan, subscription health, headcount.
 *
 * Resolves plan and subscription through their owning modules' own contracts, run inside
 * `TenantContext::runFor()` since both answer about "the current tenant" (D-007; the same
 * pattern `AppointmentResource` already uses to resolve `CustomerDirectory`/`PetDirectory` per
 * row). Uncached per row, unlike those contracts' own memoisation — acceptable at a page size
 * capped at 100 and the tenant volumes this product has today; worth a bulk-lookup method on
 * both contracts if the roster ever grows large enough for it to matter.
 *
 * @property-read Tenant $resource
 */
final class PlatformTenantSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $plan = $this->resource->plan_id === null
            ? app(PlanRegistry::class)->default()
            : app(PlanRegistry::class)->findById($this->resource->plan_id);

        $subscription = app(TenantContext::class)->runFor(
            $this->resource,
            fn () => app(SubscriptionDirectory::class)->currentFor(),
        );

        return [
            'id' => $this->resource->getKey(),
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'email' => $this->resource->email,
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),

            'plan_key' => $plan?->key,
            'plan_name' => $plan?->name,

            'subscription_status' => $subscription?->status->value,
            'subscription_status_label' => $subscription?->status->label(),
            'subscription_is_delinquent' => $subscription?->isDelinquent ?? false,

            'user_count' => $this->resource->users()->count(),

            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
