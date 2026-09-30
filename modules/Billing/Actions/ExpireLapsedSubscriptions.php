<?php

namespace Modules\Billing\Actions;

use Illuminate\Support\Collection;
use Modules\Billing\Domain\SubscriptionStatus;
use Modules\Billing\Models\Subscription;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;

/**
 * The scheduled half of the billing lifecycle: end what the clock has ended (spec §24).
 *
 * Two cases, both time-driven and neither triggered by a request:
 *
 *   - A grace period ran out. The dunning cycle is over; cancel and drop to the default tier.
 *   - A cancellation scheduled for period end reached that date. Drop to the default tier.
 *
 * Runs across every tenant, which is a deliberate and audited exception to invariant #1 —
 * this is platform housekeeping, not a tenant reading data. Each business is processed
 * *inside its own tenant context* so every write, entitlement change and audit event is
 * attributed correctly; doing it in one cross-tenant sweep would write every audit event
 * with a null tenant and leave each business with no record of its own cancellation.
 */
final class ExpireLapsedSubscriptions
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly CancelSubscription $cancel,
    ) {}

    /**
     * @return int how many subscriptions were ended
     */
    public function execute(): int
    {
        $ended = 0;

        foreach ($this->lapsed() as $subscription) {
            $tenant = Tenant::query()->find($subscription->tenant_id);

            if ($tenant === null) {
                continue;
            }

            $this->tenants->runFor($tenant, function () use ($tenant, $subscription, &$ended): void {
                $reason = $subscription->status === SubscriptionStatus::Grace
                    ? 'grace_period_expired'
                    : 'cancellation_period_ended';

                $this->cancel->execute(
                    tenant: $tenant,
                    subscription: $subscription,
                    immediately: true,
                    reason: $reason,
                );

                $ended++;
            });
        }

        return $ended;
    }

    /**
     * @return Collection<int, Subscription>
     */
    private function lapsed()
    {
        return $this->tenants->withoutTenancy(
            static fn () => Subscription::query()
                ->withoutGlobalScopes()
                ->current()
                ->where(function ($query): void {
                    $query
                        ->where(function ($q): void {
                            $q->where('status', SubscriptionStatus::Grace->value)
                                ->whereNotNull('grace_ends_at')
                                ->where('grace_ends_at', '<=', now());
                        })
                        ->orWhere(function ($q): void {
                            $q->whereNotNull('ends_at')->where('ends_at', '<=', now());
                        });
                })
                ->get()
        );
    }
}
