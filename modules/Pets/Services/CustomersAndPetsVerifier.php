<?php

namespace Modules\Pets\Services;

use Modules\Crm\Contracts\CustomerDirectory;
use Modules\Onboarding\Contracts\OnboardingStepVerifier;
use Modules\Onboarding\Domain\OnboardingStep;
use Modules\Pets\Contracts\PetDirectory;

/**
 * Spec §7 step 8: "Add or import customers and pets" (`D-015`).
 *
 * Owned by Pets rather than Crm because it takes both halves, and Crm shipped first. A
 * customers-only verifier would have reported the step done for a business holding four hundred
 * customers and no pets — which cannot be booked at all, since a §11 appointment is for a pet.
 *
 * Neither half is counted directly: customers are asked of Crm through `CustomerDirectory`, pets
 * of this module's own `PetDirectory`, so nothing here reads another module's tables (D-007).
 */
final class CustomersAndPetsVerifier implements OnboardingStepVerifier
{
    public function __construct(
        private readonly CustomerDirectory $customers,
        private readonly PetDirectory $pets,
    ) {}

    public function step(): OnboardingStep
    {
        return OnboardingStep::CustomersAndPets;
    }

    public function isSatisfied(): bool
    {
        return $this->customers->hasAny() && $this->pets->hasAny();
    }
}
