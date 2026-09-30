<?php

namespace Modules\Onboarding\Contracts;

use Modules\Onboarding\Domain\OnboardingStep;

/**
 * Lets a module answer "has this setup step actually been done?" for itself.
 *
 * Spec §7 has onboarding covering staff, services, hours and imports — all of which are
 * owned by other modules. Onboarding must not reach into Catalog's services table or Team's
 * staff records to find out (D-007), and it must not trust the client to tick the box: a
 * checklist that can be marked done without doing the work would show a business as ready to
 * take bookings when it has no services.
 *
 * So each module registers a verifier for the step it owns, and Onboarding asks. A step with
 * no verifier registered yet reads as not satisfied, which is honest — the module that would
 * answer does not exist.
 */
interface OnboardingStepVerifier
{
    public function step(): OnboardingStep;

    /**
     * Is the underlying work done for the current tenant?
     *
     * Called inside the tenant's context, so an ordinary scoped query is correct here.
     */
    public function isSatisfied(): bool;
}
