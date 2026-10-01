<?php

namespace Modules\Catalog\Services;

use Modules\Catalog\Contracts\ServiceCatalog;
use Modules\Onboarding\Contracts\OnboardingStepVerifier;
use Modules\Onboarding\Domain\OnboardingStep;

/**
 * Spec §7 step 5: "Services, prices and durations".
 *
 * This step has read `unavailable` since Phase 4 — Onboarding declared it verified but no module
 * could answer it, which the checklist reported as "coming soon" rather than nagging the owner
 * about something the product could not yet accept. Catalog now answers it.
 *
 * It is a **required** step, so this is also what stands between a business and `is_ready`: nobody
 * can book a salon whose services are unknown. Asked through the module's own contract rather than
 * by counting rows here, so the definition of "has a sellable service" lives in one place.
 */
final class ServicesVerifier implements OnboardingStepVerifier
{
    public function __construct(private readonly ServiceCatalog $catalog) {}

    public function step(): OnboardingStep
    {
        return OnboardingStep::Services;
    }

    public function isSatisfied(): bool
    {
        return $this->catalog->hasAny();
    }
}
