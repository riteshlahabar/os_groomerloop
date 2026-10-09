<?php

namespace Modules\Crm\Domain;

/**
 * What a customer may see and edit about themselves in the Customer Portal (`D-043`).
 *
 * A second, wider DTO alongside `CustomerContactDetails` rather than more fields on that one,
 * for the same reason `Pets\Domain\PetSummary` exists beside the admin resource: the two have
 * different audiences. `CustomerContactDetails` answers "where do I send this" for Notifications
 * and is three fields on purpose; this answers "what does this person see on their own profile
 * page", which is the editable half of their record and nothing else.
 *
 * Deliberately absent, and each omission is the point: `status` and `source` (the business's
 * classification of them, not theirs), `notes` (staff's note *about* them — the §8/§9 split
 * between a customer-provided note and an internal one), every `accepts_*` consent column
 * (invariant #9 — consent is changed through a consent flow, never as a side effect of editing
 * an address), and `password`. `email` is carried but is read-only by the owner's decision
 * (2026-10-09): it is this guard's login identity, so changing it changes how they sign in, and a
 * collision with another customer sharing that address would lock someone out.
 */
final readonly class CustomerSelfProfile
{
    public function __construct(
        public int $id,
        public string $firstName,
        public ?string $lastName,
        public ?string $email,
        public ?string $phone,
        public ?string $addressLine1,
        public ?string $addressLine2,
        public ?string $city,
        public ?string $state,
        public ?string $postalCode,
        public ?string $country,
        /** When they first became a customer — the design's "Member Since" line. */
        public ?string $customerSince,
    ) {}

    public function fullName(): string
    {
        return trim($this->firstName.' '.(string) $this->lastName);
    }
}
