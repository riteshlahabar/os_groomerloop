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
 * Open a business's first paid subscription (spec §24, §32.1 step 4).
 *
 * Starts on trial when one is configured, so a groomer can finish onboarding before being
 * charged; charges immediately when it is not. Either way the plan takes effect at once —
 * spec §32.1 has them configuring services and staff straight after paying, and a trial that
 * withheld entitlements would block the journey it exists to smooth.
 */
final class StartSubscription
{
    public function __construct(
        private readonly PlanRegistry $plans,
        private readonly AuditRecorder $audit,
        private readonly ChargeSubscription $charge,
    ) {}

    public function execute(Tenant $tenant, string $planKey, ?string $gatewayCustomerId = null): Subscription
    {
        $plan = $this->plans->findByKey($planKey);

        if ($plan === null || ! $plan->isActive) {
            throw ValidationException::withMessages([
                'plan' => 'That plan is not available.',
            ]);
        }

        $this->refuseIfAlreadySubscribed($tenant);

        $trialDays = (int) config('billing.trial_days', 0);

        $subscription = DB::transaction(function () use ($tenant, $plan, $trialDays, $gatewayCustomerId): Subscription {
            $subscription = Subscription::create([
                'plan_id' => $plan->id,
                'status' => $trialDays > 0 ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
                'gateway' => (string) config('billing.gateway', 'fake'),
                'gateway_customer_id' => $gatewayCustomerId,
                'trial_ends_at' => $trialDays > 0 ? now()->addDays($trialDays) : null,
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
            ]);

            // Entitlements are granted through the registry, never by writing tenants.plan_id
            // here. One writer keeps billing and entitlement state synchronized (spec §35).
            $this->plans->assignToTenant($tenant, $plan->id);

            $this->audit->record('subscription.started', $subscription, [
                'plan' => $plan->key,
                'price_cents' => $plan->priceCents,
                'trial_days' => $trialDays,
            ]);

            return $subscription;
        });

        // Charged after the transaction commits: a gateway call inside a transaction holds
        // row locks open for the length of a network round trip, and a rollback afterwards
        // cannot un-charge a card.
        if ($trialDays === 0) {
            $this->charge->execute($subscription, $plan);
        }

        return $subscription->refresh();
    }

    private function refuseIfAlreadySubscribed(Tenant $tenant): void
    {
        $existing = Subscription::query()->current()->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'plan' => 'This business already has an active subscription. Change the plan instead.',
            ]);
        }
    }
}
