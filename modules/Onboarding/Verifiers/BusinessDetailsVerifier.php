<?php

namespace Modules\Onboarding\Verifiers;

use Modules\Onboarding\Contracts\OnboardingStepVerifier;
use Modules\Onboarding\Domain\OnboardingStep;
use Modules\Onboarding\Models\BusinessProfile;

/**
 * Spec §7 step 2. Satisfied once the profile holds enough to contact and locate the business
 * — see BusinessProfile::isSufficient() for why that bar is deliberately low.
 */
final class BusinessDetailsVerifier implements OnboardingStepVerifier
{
    public function step(): OnboardingStep
    {
        return OnboardingStep::BusinessDetails;
    }

    public function isSatisfied(): bool
    {
        return BusinessProfile::query()->first()?->isSufficient() ?? false;
    }
}
