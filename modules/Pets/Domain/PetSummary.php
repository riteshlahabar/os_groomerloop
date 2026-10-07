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
    public function __construct(
        public int $id,
        public string $name,
        public ?string $speciesName,
        public ?string $breed,
        public string $sex,
        public ?string $dateOfBirth,
        public ?int $ageYears,
        public bool $ageIsApproximate,
        public ?array $ageBreakdown,
        public ?string $weightLb,
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
