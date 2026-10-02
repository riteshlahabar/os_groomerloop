<?php

namespace Modules\Onboarding\Services;

use Modules\Onboarding\Contracts\BusinessProfileDirectory;
use Modules\Onboarding\Domain\BusinessProfileSummary;
use Modules\Onboarding\Models\BusinessProfile;

/**
 * The Eloquent half of {@see BusinessProfileDirectory}.
 *
 * Memoised per request: a single website page renders the business name in the header, the
 * address in the footer and the phone number in a call-to-action, and that is one query, not
 * three. Registered as a singleton by OnboardingServiceProvider, so the memo is shared.
 */
final class EloquentBusinessProfileDirectory implements BusinessProfileDirectory
{
    private bool $loaded = false;

    private ?BusinessProfileSummary $summary = null;

    public function summary(): ?BusinessProfileSummary
    {
        if ($this->loaded) {
            return $this->summary;
        }

        $this->loaded = true;

        // Tenant-scoped by BelongsToTenant's global scope, and fails closed with no tenant in
        // context (D-012) — a public site request has established its tenant through
        // ResolvePublicTenant before this runs.
        $profile = BusinessProfile::query()->first();

        if ($profile === null) {
            return null;
        }

        return $this->summary = new BusinessProfileSummary(
            legalName: $profile->legal_name,
            contactName: $profile->contact_name,
            contactEmail: $profile->contact_email,
            contactPhone: $profile->contact_phone,
            addressLine1: $profile->address_line_1,
            addressLine2: $profile->address_line_2,
            city: $profile->city,
            state: $profile->state,
            postalCode: $profile->postal_code,
            country: $profile->country,
            serviceArea: $profile->service_area,
            description: $profile->description,
        );
    }
}
