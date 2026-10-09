<?php

namespace Modules\Pets\Domain;

/**
 * What `PetDirectory::summariesForCustomer()` hands another module (D-007) — built for the
 * Customer Portal (`D-043`), so it deliberately excludes `internal_notes` as a field rather than
 * merely as a presentation choice. §9 gives staff-only notes their own permission; a DTO that
 * never carries the value at all cannot be leaked by a caller that forgets to check one.
 */
final readonly class PetSummary
{
    /*
     * `speciesId`, `coatType` and `approximateAgeYears` are the raw stored values beside the
     * display ones, added 2026-10-09 when the portal's Pets page gained an edit form. A form has
     * to pre-select what is already stored, and a label cannot do that: "Dog" does not identify a
     * row in this tenant's `pet_species` table, and "Short" is not the `CoatType` case. Both
     * display and raw are carried rather than one derived from the other, so a caller that only
     * renders text still gets text.
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?int $speciesId,
        public ?string $speciesName,
        public ?string $breed,
        public string $sex,
        public ?string $dateOfBirth,
        public ?int $approximateAgeYears,
        public ?int $ageYears,
        public bool $ageIsApproximate,
        public ?array $ageBreakdown,
        public ?string $weightLb,
        public ?string $coatType,
        public ?string $coatTypeLabel,
        public ?string $coatNotes,
        public ?string $customerNotes,
        public ?string $temperamentNotes,
        public ?string $specialInstructions,
        public ?string $medicalNotes,
        public string $status,
        public string $statusLabel,
    ) {}
}
