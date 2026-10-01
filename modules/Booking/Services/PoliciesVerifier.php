<?php

namespace Modules\Booking\Services;

use Modules\Booking\Models\BookingSettings;
use Modules\Onboarding\Contracts\OnboardingStepVerifier;
use Modules\Onboarding\Domain\OnboardingStep;

/**
 * Closes the gap `OnboardingChecklistTest`'s own history names: `OnboardingStep::Policies` has
 * been declared `isVerified() => true` since Phase 4 with nothing registered to answer it. Booking
 * (§12) owns cancellation/no-show policy — `BookingSettings.cancellation_window_hours` — so this
 * is the step's real home.
 */
final class PoliciesVerifier implements OnboardingStepVerifier
{
    public function step(): OnboardingStep
    {
        return OnboardingStep::Policies;
    }

    public function isSatisfied(): bool
    {
        return BookingSettings::query()->exists();
    }
}
