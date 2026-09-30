<?php

namespace Modules\Billing\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Billing\Domain\SubscriptionStatus;
use Modules\Billing\Models\Subscription;
use Modules\Entitlements\Contracts\PlanRegistry;
use Modules\Tenancy\Models\Tenant;

/**
 * Cancellation (spec §24).
 *
 * Two shapes, and the difference matters to the customer:
 *
 *   - At period end (the default). They have paid for this month, so they keep the plan
 *     until it runs out. The subscription stays entitling until then.
 *   - Immediately. Used when a grace period expires, and available on request. Entitlements
 *     drop to the default tier at once.
 *
 * Neither deletes anything. Every customer, pet, appointment and invoice survives — invariant
 * #4, and the reason a returning customer can reactivate and find their business as they left
 * it.
 */
final class CancelSubscription
{
    public function __construct(
        private readonly PlanRegistry $plans,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(
        Tenant $tenant,
        Subscription $subscription,
        bool $immediately = false,
        string $reason = 'requested',
    ): Subscription {
        if ($subscription->status->isCancelled()) {
            return $subscription;
        }

        return DB::transaction(function () use ($tenant, $subscription, $immediately, $reason): Subscription {
            $endsAt = $immediately
                ? now()
                : ($subscription->current_period_end ?? now());

            $subscription->cancelled_at = now();
            $subscription->ends_at = $endsAt;

            if ($immediately) {
                $subscription->transitionTo(SubscriptionStatus::Cancelled);
                $subscription->grace_ends_at = null;
            }

            $subscription->save();

            // Only an immediate cancellation moves entitlements. A cancellation at period end
            // leaves the business on its plan until the period actually ends — they paid for
            // it. ExpireEndedSubscriptions does the drop when the date arrives.
            if ($immediately) {
                $this->plans->assignToTenant($tenant, null);
            }

            $this->audit->record('subscription.cancelled', $subscription, [
                'reason' => $reason,
                'immediately' => $immediately,
                'ends_at' => $endsAt->toIso8601String(),
            ]);

            return $subscription;
        });
    }
}
