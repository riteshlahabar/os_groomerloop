<?php

namespace Modules\Scheduling\Services;

use Modules\Onboarding\Contracts\OnboardingStepVerifier;
use Modules\Onboarding\Domain\OnboardingStep;
use Modules\Scheduling\Models\BusinessHour;

/**
 * Closes a gap open since Phase 4: `OnboardingStep::BusinessHours` has always been declared
 * `isVerified() => true`, but no module existed to answer it, so the checklist reported
 * `unavailable` rather than nagging the owner about something the product could not yet accept
 * (`OnboardingChecklistTest::test_a_step_whose_module_does_not_exist_reports_as_unavailable`,
 * written against exactly this step). `D-017` already named the intended owner: "Scheduling →
 * hours."
 */
final class BusinessHoursVerifier implements OnboardingStepVerifier
{
    public function step(): OnboardingStep
    {
        return OnboardingStep::BusinessHours;
    }

    public function isSatisfied(): bool
    {
        return BusinessHour::query()->exists();
    }
}
