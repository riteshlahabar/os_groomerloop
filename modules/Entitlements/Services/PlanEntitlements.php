<?php

namespace Modules\Entitlements\Services;

use Modules\Entitlements\Contracts\Entitlements;
use Modules\Entitlements\Domain\Feature;
use Modules\Entitlements\Domain\FeatureGrade;
use Modules\Entitlements\Models\Plan;
use Modules\Tenancy\Support\TenantContext;

/**
 * The single entitlement service required by invariant #3.
 *
 * Resolution order for "which plan is this business on":
 *
 *   1. tenants.plan_id, when set — written by Billing when a subscription starts or changes.
 *   2. the plan flagged is_default, otherwise — a business that has registered but not yet
 *      subscribed, or whose subscription has ended. Spec §2's Starter tier is that floor.
 *   3. no plan at all — nothing is entitled. Fails closed, matching the D-012 reasoning for
 *      the tenant scope: an unknown answer must deny, not permit.
 *
 * Answers are memoised for the life of the instance (one request, one job) and re-resolved
 * whenever the tenant's plan pointer changes, so a page that gates a dozen things costs one
 * query while a tenant switch inside a worker cannot be answered from the previous plan.
 *
 * They are deliberately NOT written to the shared cache. A stale entitlement means a business
 * being billed for something it cannot use, or using something it has stopped paying for, and
 * neither is worth one saved query.
 */
final class PlanEntitlements implements Entitlements
{
    /**
     * The tenant plan pointer the memo below was built for. Null is a meaningful value here
     * ("no plan_id set"), so a separate flag records whether anything is memoised at all.
     */
    private ?int $memoisedFor = null;

    private bool $memoised = false;

    private ?Plan $memoisedPlan = null;

    /** @var array<string, FeatureGrade> */
    private array $memoisedGrants = [];

    public function __construct(private readonly TenantContext $tenants) {}

    public function allows(Feature $feature): bool
    {
        return isset($this->grants()[$feature->value]);
    }

    public function gradeOf(Feature $feature): ?FeatureGrade
    {
        return $this->grants()[$feature->value] ?? null;
    }

    public function atLeast(Feature $feature, FeatureGrade $minimum): bool
    {
        return $this->gradeOf($feature)?->atLeast($minimum) ?? false;
    }

    /**
     * @return array<string, FeatureGrade>
     */
    public function all(): array
    {
        return $this->grants();
    }

    public function flush(): void
    {
        $this->memoised = false;
        $this->memoisedFor = null;
        $this->memoisedPlan = null;
        $this->memoisedGrants = [];
    }

    /**
     * The plan the current business is on, or null when there is none to fall back to.
     */
    public function plan(): ?Plan
    {
        $this->resolve();

        return $this->memoisedPlan;
    }

    public function hasPlan(): bool
    {
        return $this->plan() !== null;
    }

    public function defaultPlan(): ?Plan
    {
        return Plan::query()->with('features')->where('is_default', true)->first();
    }

    /**
     * @return array<string, FeatureGrade>
     */
    private function grants(): array
    {
        $this->resolve();

        return $this->memoisedGrants;
    }

    private function resolve(): void
    {
        $pointer = $this->currentPlanPointer();

        if ($this->memoised && $this->memoisedFor === $pointer) {
            return;
        }

        $plan = $this->loadPlan($pointer);

        $this->memoisedFor = $pointer;
        $this->memoisedPlan = $plan;
        $this->memoisedGrants = $plan?->grants() ?? [];
        $this->memoised = true;
    }

    private function currentPlanPointer(): ?int
    {
        $planId = $this->tenants->tenant()?->getAttribute('plan_id');

        return $planId === null ? null : (int) $planId;
    }

    private function loadPlan(?int $planId): ?Plan
    {
        if ($planId !== null) {
            $plan = Plan::query()->with('features')->find($planId);

            if ($plan !== null) {
                return $plan;
            }

            // The tenant points at a plan row that no longer exists. Falling through to the
            // default is deliberate: a business should keep working on the floor tier rather
            // than lose access to its own data because a price list row was deleted.
        }

        return $this->defaultPlan();
    }
}
