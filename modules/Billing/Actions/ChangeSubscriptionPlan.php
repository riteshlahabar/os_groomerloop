<?php

namespace Modules\Billing\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Billing\Models\Subscription;
use Modules\Entitlements\Contracts\PlanRegistry;
use Modules\Tenancy\Models\Tenant;

/**
 * Upgrade or downgrade (spec §24).
 *
 * The two are one action because they are one operation with one invariant: the subscription
 * row and tenants.plan_id must agree afterwards, whichever direction the money went (spec
 * §35, "billing and entitlement state remain synchronized"). Splitting them into an
 * UpgradePlan and a DowngradePlan would give that invariant two places to be broken.
 *
 * A downgrade takes effect immediately and destroys nothing: features lock, records stay
 * (invariant #4). The entitlement change is made by PlanRegistry, the only writer of
 * tenants.plan_id, which is what makes that guarantee structural rather than a promise.
 */
final class ChangeSubscriptionPlan
{
    public function __construct(
        private readonly PlanRegistry $plans,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(Tenant $tenant, Subscription $subscription, string $planKey): Subscription
    {
        $plan = $this->plans->findByKey($planKey);

        if ($plan === null || ! $plan->isActive) {
            throw ValidationException::withMessages([
                'plan' => 'That plan is not available.',
            ]);
        }

        if ($subscription->status->isCancelled()) {
            throw ValidationException::withMessages([
                'plan' => 'This subscription has been cancelled. Reactivate it before changing plan.',
            ]);
        }

        $previous = $this->plans->findById($subscription->plan_id);

        if ($previous !== null && $previous->id === $plan->id) {
            return $subscription;
        }

        return DB::transaction(function () use ($tenant, $subscription, $plan, $previous): Subscription {
            $subscription->plan_id = $plan->id;
            $subscription->save();

            // Entitlements follow the subscription in the same transaction. If the plan
            // change commits and the entitlement change does not, the business is billed for
            // one tier and can use another — which is the precise failure §35 names.
            $this->plans->assignToTenant($tenant, $plan->id);

            $this->audit->record('subscription.plan_changed', $subscription, [
                'from' => $previous?->key,
                'to' => $plan->key,
                'from_price_cents' => $previous?->priceCents,
                'to_price_cents' => $plan->priceCents,
                'direction' => $this->direction($previous?->priceCents, $plan->priceCents),
            ]);

            return $subscription;
        });
    }

    private function direction(?int $from, int $to): string
    {
        return match (true) {
            $from === null => 'assigned',
            $to > $from => 'upgrade',
            $to < $from => 'downgrade',
            default => 'lateral',
        };
    }
}
