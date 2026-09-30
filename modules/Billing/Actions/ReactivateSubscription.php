<?php

namespace Modules\Billing\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Billing\Domain\SubscriptionStatus;
use Modules\Billing\Models\Subscription;
use Modules\Entitlements\Contracts\PlanRegistry;
use Modules\Tenancy\Models\Tenant;

/**
 * Reactivation (spec §24).
 *
 * Covers both cases the customer experiences as "I want to come back":
 *
 *   - Undoing a cancellation before the period ends. Nothing was lost; clear the dates.
 *   - Restarting after it ended. The plan is granted again, and the business finds its
 *     customers, pets and appointments exactly as they were — which is the whole point of
 *     invariant #4 and the reason cancellation never deletes.
 *
 * The same subscription row is resumed rather than a new one opened, so billing history
 * stays continuous and §36 churn reporting can tell a reactivation from a new signup.
 */
final class ReactivateSubscription
{
    public function __construct(
        private readonly PlanRegistry $plans,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(Tenant $tenant, Subscription $subscription): Subscription
    {
        $plan = $this->plans->findById($subscription->plan_id);

        if ($plan === null || ! $plan->isActive) {
            throw ValidationException::withMessages([
                'plan' => 'The plan this subscription was on is no longer available. Choose a new plan.',
            ]);
        }

        $wasCancelled = $subscription->status->isCancelled();

        if (! $wasCancelled && $subscription->cancelled_at === null) {
            throw ValidationException::withMessages([
                'subscription' => 'This subscription is already active.',
            ]);
        }

        return DB::transaction(function () use ($tenant, $subscription, $plan, $wasCancelled): Subscription {
            $subscription->transitionTo(SubscriptionStatus::Active);

            $subscription->cancelled_at = null;
            $subscription->ends_at = null;
            $subscription->grace_ends_at = null;
            $subscription->failed_payment_count = 0;

            // A subscription resumed after its period lapsed starts a fresh one; one resumed
            // before its period ended keeps the period it already paid for.
            if ($subscription->current_period_end === null || $subscription->current_period_end->isPast()) {
                $subscription->current_period_start = now();
                $subscription->current_period_end = now()->addMonth();
            }

            $subscription->save();

            $this->plans->assignToTenant($tenant, $plan->id);

            $this->audit->record('subscription.reactivated', $subscription, [
                'plan' => $plan->key,
                'after_full_cancellation' => $wasCancelled,
            ]);

            return $subscription;
        });
    }
}
