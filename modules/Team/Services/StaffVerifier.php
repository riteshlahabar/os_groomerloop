<?php

namespace Modules\Team\Services;

use Modules\Onboarding\Contracts\OnboardingStepVerifier;
use Modules\Onboarding\Domain\OnboardingStep;
use Modules\Team\Contracts\StaffDirectory;

/**
 * Spec §7 step 4: "Add groomers and their availability".
 *
 * The last of the four verified steps that had no module to answer it. Business hours (§11) is now the
 * only one left reading `unavailable`.
 *
 * Satisfied by having anyone on the team, not by their having a rota. That is a deliberate bar: the step
 * is **skippable** because §3 lists solo and home-based groomers first, and a one-person business that
 * has added itself has done this step. Demanding a filled-in rota here would turn a skippable
 * introduction into a scheduling exercise — and "active staff with no working hours" is surfaced by the
 * team list filter instead, where it is actionable.
 */
final class StaffVerifier implements OnboardingStepVerifier
{
    public function __construct(private readonly StaffDirectory $staff) {}

    public function step(): OnboardingStep
    {
        return OnboardingStep::Staff;
    }

    public function isSatisfied(): bool
    {
        return $this->staff->hasAny();
    }
}
