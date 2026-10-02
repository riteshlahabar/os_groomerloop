<?php

namespace Modules\Billing\Contracts;

use Modules\Billing\Domain\SubscriptionSummary;

/**
 * How another module reads a tenant's subscription state (D-007) — answers about the *current*
 * tenant, taken from `TenantContext`, the same ambient-tenant shape `Entitlements` uses and for
 * the same reason: a caller that has to remember to supply the business is a caller that can
 * forget. A cross-tenant caller (SuperAdmin's platform-wide tenant roster, spec §31) drives this
 * once per tenant inside `TenantContext::runFor()`, the pattern already established by
 * `ExpireLapsedSubscriptions` and `AppointmentScheduler::startingBetween()`.
 */
interface SubscriptionDirectory
{
    /**
     * The current tenant's subscription, or null when it has never subscribed.
     */
    public function currentFor(): ?SubscriptionSummary;
}
