<?php

namespace Modules\CustomerPortal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Pets\Domain\PetSummary;

/**
 * A customer's own view of one of their pets, built from `PetSummary` — a DTO that never carries
 * `internal_notes` as a field at all, rather than a resource trusted to omit it (see
 * `Pets\Domain\PetSummary`'s own docblock).
 *
 * @property-read PetSummary $resource
 */
final class PetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            // Raw stored values beside the display ones: the portal's edit form has to pre-select
            // what is already there, and a label cannot do that (see `PetSummary`'s own note).
            'species_id' => $this->resource->speciesId,
            'species_name' => $this->resource->speciesName,
            'breed' => $this->resource->breed,
            'sex' => $this->resource->sex,
            'date_of_birth' => $this->resource->dateOfBirth,
            'approximate_age_years' => $this->resource->approximateAgeYears,
            'age_years' => $this->resource->ageYears,
            'age_is_approximate' => $this->resource->ageIsApproximate,
            'age_breakdown' => $this->resource->ageBreakdown,
            'weight_lb' => $this->resource->weightLb,
            'coat_type' => $this->resource->coatType,
            'coat_type_label' => $this->resource->coatTypeLabel,
            'coat_notes' => $this->resource->coatNotes,
            'customer_notes' => $this->resource->customerNotes,
            'temperament_notes' => $this->resource->temperamentNotes,
            'special_instructions' => $this->resource->specialInstructions,

            // Informational only, same caveat `Pets\Http\Resources\PetResource` carries — §9 and
            // §29 are explicit that this must never read as veterinary advice.
            'medical_notes' => $this->resource->medicalNotes,
            'medical_notes_are_not_veterinary_advice' => true,

            'status' => $this->resource->status,
            'status_label' => $this->resource->statusLabel,
        ];
    }
}
