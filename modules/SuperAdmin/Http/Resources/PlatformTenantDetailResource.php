<?php

namespace Modules\SuperAdmin\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Billing\Contracts\SubscriptionDirectory;
use Modules\Entitlements\Contracts\Entitlements;
use Modules\Entitlements\Contracts\PlanRegistry;
use Modules\Entitlements\Domain\Feature;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;

/**
 * The full tenant detail spec §31 needs — plan, subscription, every §25 feature grade, and the
 * roster of logins. Deliberately stops at account-level metadata: no customer, pet or
 * appointment record is reachable from here. Role::PlatformAdmin's own docblock is explicit
 * that deeper "support tools" access into a tenant's own business data (spec §31) is out of
 * MVP scope and must arrive as an explicit, audited, time-bound grant — never a side effect of
 * this screen existing.
 *
 * @property-read Tenant $resource
 */
final class PlatformTenantDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tenants = app(TenantContext::class);

        $plan = $this->resource->plan_id === null
            ? app(PlanRegistry::class)->default()
            : app(PlanRegistry::class)->findById($this->resource->plan_id);

        [$subscription, $features] = $tenants->runFor($this->resource, fn (): array => [
            app(SubscriptionDirectory::class)->currentFor(),
            app(Entitlements::class)->all(),
        ]);

        return [
            'id' => $this->resource->getKey(),
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'timezone' => $this->resource->timezone,
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'created_at' => $this->resource->created_at?->toIso8601String(),

            'plan' => $plan === null ? null : [
                'key' => $plan->key,
                'name' => $plan->name,
                'price_cents' => $plan->priceCents,
                'billing_interval' => $plan->billingInterval,
            ],

            'subscription' => $subscription === null ? null : [
                'status' => $subscription->status->value,
                'status_label' => $subscription->status->label(),
                'is_delinquent' => $subscription->isDelinquent,
                'trial_ends_at' => $subscription->trialEndsAt,
                'current_period_end' => $subscription->currentPeriodEnd,
            ],

            'features' => array_map(
                static function ($feature) use ($features) {
                    $grade = $features[$feature->value] ?? null;

                    return [
                        'key' => $feature->value,
                        'grade' => $grade?->value,
                        'grade_label' => $grade?->label(),
                    ];
                },
                Feature::cases(),
            ),

            'users' => $this->resource->users()->get()->map(static fn ($user): array => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->value,
                'role_label' => $user->role?->label(),
                'created_at' => $user->created_at?->toIso8601String(),
            ])->all(),
        ];
    }
}
