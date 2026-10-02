<?php

namespace Modules\Onboarding\Contracts;

use Modules\Onboarding\Domain\BusinessProfileSummary;

/**
 * How other modules read the business's own details (D-007).
 *
 * Onboarding owns `business_profiles` (spec §7 step 2). The §14 Website module needs the salon's
 * name, address and contact details to render a public site, and §16 Insights will want the same
 * for report headers — neither may load the BusinessProfile model.
 *
 * Read-only on purpose: the profile is edited through Onboarding's own §7 endpoints, which audit
 * the change. There is no write half of this contract and should not be one.
 */
interface BusinessProfileDirectory
{
    /**
     * The current tenant's profile, or null when §7 step 2 was never completed.
     *
     * Null is an ordinary answer, not an error: the step is skippable, so a consumer has to be
     * able to render without it.
     */
    public function summary(): ?BusinessProfileSummary;
}
