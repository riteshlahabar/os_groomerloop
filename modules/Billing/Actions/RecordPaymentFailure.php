<?php

namespace Modules\Billing\Actions;

use Modules\Audit\Contracts\AuditRecorder;
use Modules\Billing\Domain\SubscriptionStatus;
use Modules\Billing\Models\Subscription;

/**
 * The dunning cycle of spec §24: "failed-payment handling" and "configurable grace period".
 *
 * One failure moves a subscription to past_due. After the configured number of attempts it
 * enters the grace period. Only when grace expires — handled by ExpireGracePeriods, not here
 * — does it cancel.
 *
 * Access is never withdrawn by this action. A business that is past due or in grace keeps
 * every feature of its plan, because the alternative is locking a groomer out of tomorrow's
 * appointments over an expired card. That is invariant #4 read the way it was meant: losing
 * the plan must not cost them the business.
 */
final class RecordPaymentFailure
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(Subscription $subscription, string $reason): Subscription
    {
        if ($subscription->status->isCancelled()) {
            return $subscription;
        }

        $attempts = $subscription->failed_payment_count + 1;
        $maxAttempts = max(1, (int) config('billing.dunning.max_attempts', 3));

        $subscription->forceFill([
            'failed_payment_count' => $attempts,
            'last_payment_failed_at' => now(),
        ]);

        if ($attempts >= $maxAttempts && $subscription->status !== SubscriptionStatus::Grace) {
            $graceDays = max(0, (int) config('billing.dunning.grace_days', 7));

            // Trialing -> Grace is not a legal transition, so a trial that ends without a
            // card passes through past_due first. Modelling it as two steps keeps the state
            // machine closed rather than special-casing the trial path.
            if ($subscription->status === SubscriptionStatus::Trialing) {
                $subscription->transitionTo(SubscriptionStatus::PastDue);
            }

            $subscription->transitionTo(SubscriptionStatus::Grace);
            $subscription->grace_ends_at = now()->addDays($graceDays);
        } elseif ($subscription->status !== SubscriptionStatus::Grace) {
            $subscription->transitionTo(SubscriptionStatus::PastDue);
        }

        $subscription->save();

        $this->audit->record('subscription.payment_failed', $subscription, [
            'reason' => $reason,
            'attempt' => $attempts,
            'max_attempts' => $maxAttempts,
            'status' => $subscription->status->value,
            'grace_ends_at' => $subscription->grace_ends_at?->toIso8601String(),
        ]);

        return $subscription;
    }
}
