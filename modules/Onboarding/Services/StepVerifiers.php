<?php

namespace Modules\Onboarding\Services;

use Modules\Onboarding\Contracts\OnboardingStepVerifier;
use Modules\Onboarding\Domain\OnboardingStep;

/**
 * Where modules hang their onboarding step verifiers.
 *
 * A registry rather than a hard-coded switch, so Catalog can answer for services and Team
 * for staff without Onboarding importing either (D-007). Phases 5–8 each add one line to
 * their own provider; nothing in this module changes.
 */
final class StepVerifiers
{
    /** @var array<string, OnboardingStepVerifier> */
    private array $verifiers = [];

    public function register(OnboardingStepVerifier $verifier): void
    {
        $this->verifiers[$verifier->step()->value] = $verifier;
    }

    public function has(OnboardingStep $step): bool
    {
        return isset($this->verifiers[$step->value]);
    }

    /**
     * Whether the step's underlying work is done, or null when nothing can answer yet.
     *
     * Null rather than false is the useful distinction: the checklist can say "not done"
     * either way, but "no verifier registered" means a module is missing, not that the owner
     * has work to do — and only one of those is a bug.
     */
    public function satisfied(OnboardingStep $step): ?bool
    {
        return ($this->verifiers[$step->value] ?? null)?->isSatisfied();
    }

    /**
     * @return list<OnboardingStep>
     */
    public function registeredSteps(): array
    {
        return array_values(array_map(
            static fn (OnboardingStepVerifier $v): OnboardingStep => $v->step(),
            $this->verifiers
        ));
    }
}
