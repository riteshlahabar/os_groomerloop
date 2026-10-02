<?php

namespace Modules\Billing\Domain;

/**
 * A subscription as another module is allowed to see it (D-007) — never the Eloquent model, the
 * same boundary `PlanSummary` draws for Entitlements. Built for SuperAdmin's platform-wide
 * tenant roster (spec §31 "Subscription/plan status"), which has to read every tenant's billing
 * state without loading `Modules\Billing\Models\Subscription` from outside this module.
 */
final readonly class SubscriptionSummary
{
    public function __construct(
        public SubscriptionStatus $status,
        public bool $isDelinquent,
        public ?string $trialEndsAt,
        public ?string $currentPeriodEnd,
    ) {}
}
