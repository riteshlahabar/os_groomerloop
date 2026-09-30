<?php

namespace Modules\Onboarding\Verifiers;

use App\Models\User;
use Modules\Onboarding\Contracts\OnboardingStepVerifier;
use Modules\Onboarding\Domain\OnboardingStep;

/**
 * Spec §7 step 1. True as soon as the business has a user, which it does by definition —
 * registration creates the tenant and its owner together.
 *
 * Kept as a real verifier rather than hard-coded true so the checklist has no special cases,
 * and so it starts telling the truth the day support tooling can create an empty tenant.
 */
final class AccountVerifier implements OnboardingStepVerifier
{
    public function step(): OnboardingStep
    {
        return OnboardingStep::Account;
    }

    public function isSatisfied(): bool
    {
        return User::query()->exists();
    }
}
