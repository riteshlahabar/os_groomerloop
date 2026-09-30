<?php

namespace Modules\Entitlements\Contracts;

use Modules\Entitlements\Domain\Feature;
use Modules\Entitlements\Domain\FeatureGrade;

/**
 * The one place the product decides what a business is allowed to use (invariant #3, spec §2).
 *
 * Every other module depends on this interface and never on the Plan model, the plan_features
 * table or a plan name. That is what makes spec §2's promise real: packaging can be re-cut in
 * the seeder without a controller, policy or view changing.
 *
 * Answers are about the *current tenant*, taken from TenantContext, for the same reason
 * tenant scoping is not passed around by hand: a caller that has to remember to supply the
 * business is a caller that can forget.
 */
interface Entitlements
{
    /**
     * Is this feature included in the current business's plan at all?
     */
    public function allows(Feature $feature): bool;

    /**
     * How much of it is included, or null when the plan does not include it.
     */
    public function gradeOf(Feature $feature): ?FeatureGrade;

    /**
     * Is the feature included at or above a given grade?
     *
     * Lets a route demand "automation, at least advanced" without knowing which plans qualify.
     */
    public function atLeast(Feature $feature, FeatureGrade $minimum): bool;

    /**
     * Every feature the current business has, keyed by feature value.
     *
     * Used to hand the SPA one payload it can gate its navigation on, rather than making it
     * ask per feature.
     *
     * @return array<string, FeatureGrade>
     */
    public function all(): array;

    /**
     * Drop any memoised answer, so the next question re-reads the plan.
     *
     * Needed when a plan changes inside a single request or job — an upgrade, a downgrade, or
     * a test that moves a tenant between plans.
     */
    public function flush(): void;
}
