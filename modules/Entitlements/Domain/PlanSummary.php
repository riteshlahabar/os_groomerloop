<?php

namespace Modules\Entitlements\Domain;

/**
 * A plan as other modules are allowed to see it.
 *
 * Billing needs a plan's id, key and price to open a subscription and raise an invoice, but
 * D-007 forbids it from loading the Plan model — so the plan crosses the module boundary as
 * this readonly value instead. Nothing here can be saved, lazily loaded or mutated, which is
 * the point: the catalog stays owned by Entitlements.
 */
final readonly class PlanSummary
{
    public function __construct(
        public int $id,
        public string $key,
        public string $name,
        public int $priceCents,
        public string $currency,
        public string $billingInterval,
        public bool $isDefault,
        public bool $isActive,
    ) {}

    public function isFree(): bool
    {
        return $this->priceCents === 0;
    }
}
