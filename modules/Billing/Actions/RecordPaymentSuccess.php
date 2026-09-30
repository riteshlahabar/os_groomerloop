<?php

namespace Modules\Billing\Actions;

use Modules\Audit\Contracts\AuditRecorder;
use Modules\Billing\Domain\SubscriptionStatus;
use Modules\Billing\Models\Subscription;

/**
 * A payment came in. Clear the dunning state (spec §24).
 *
 * The recovery half of RecordPaymentFailure, and deliberately its own action rather than an
 * `else` branch inside the charge: recovery also happens when a customer updates their card
 * and we retry, or when a gateway webhook reports a late success, and none of those go
 * through the same path.
 */
final class RecordPaymentSuccess
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(Subscription $subscription): Subscription
    {
        $previous = $subscription->status;

        // Cancelled subscriptions are not revived by a stray payment. Coming back is
        // reactivation, which is an explicit, audited act — see ReactivateSubscription.
        if ($previous->isCancelled()) {
            return $subscription;
        }

        $subscription->transitionTo(SubscriptionStatus::Active);

        $subscription->failed_payment_count = 0;
        $subscription->grace_ends_at = null;
        $subscription->current_period_start = now();
        $subscription->current_period_end = now()->addMonth();

        $subscription->save();

        if ($previous !== SubscriptionStatus::Active) {
            $this->audit->record('subscription.payment_recovered', $subscription, [
                'from' => $previous->value,
            ]);
        }

        return $subscription;
    }
}
