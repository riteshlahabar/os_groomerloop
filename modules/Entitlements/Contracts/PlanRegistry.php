<?php

namespace Modules\Entitlements\Contracts;

use Modules\Entitlements\Domain\PlanSummary;
use Modules\Tenancy\Models\Tenant;

/**
 * Read the plan catalog, and move a business onto a plan.
 *
 * The boundary Billing talks to (D-007). Billing decides *when* a plan changes — a
 * subscription starts, an upgrade is paid for, a dunning cycle gives up — and calls this to
 * make it true. Entitlements decides what being on that plan means.
 *
 * Keeping the write here rather than letting Billing set tenants.plan_id itself is what makes
 * spec §35's "billing and entitlement state remain synchronized" enforceable: there is one
 * function that changes entitlement, and it audits every call.
 */
interface PlanRegistry
{
    /**
     * Every plan on sale, cheapest first.
     *
     * @return list<PlanSummary>
     */
    public function active(): array;

    public function findByKey(string $key): ?PlanSummary;

    public function findById(int $id): ?PlanSummary;

    /**
     * The plan a business is on before it subscribes, and the floor it returns to afterwards.
     */
    public function default(): ?PlanSummary;

    /**
     * Move a business onto a plan, or back to the default when given null.
     *
     * Audited, and deliberately the only write path: tenants.plan_id is not fillable, so no
     * request payload can put a business on a plan it has not paid for.
     *
     * Never destroys anything — invariant #4. A downgrade changes which features answer yes,
     * and nothing else.
     */
    public function assignToTenant(Tenant $tenant, ?int $planId): PlanSummary;
}
