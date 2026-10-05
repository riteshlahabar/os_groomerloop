<?php

namespace Modules\Pets\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Crm\Contracts\CustomerDirectory;
use Modules\Identity\Domain\Permission;
use Modules\Pets\Models\Pet;

/**
 * @property-read Pet $resource
 */
final class PetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),

            'customer_id' => $this->resource->customer_id,

            // Resolved through Crm's contract, not a relationship. The calendar and the pet list
            // both need to say whose dog this is, and neither should require the client to make a
            // second request — but Pets may not load the Customer model (D-007).
            'customer_name' => app(CustomerDirectory::class)->nameOf($this->resource->customer_id),

            'name' => $this->resource->name,
            'species_id' => $this->resource->species_id,
            'species_name' => $this->resource->species?->name,
            'breed' => $this->resource->breed,
            'sex' => $this->resource->sex->value,

            'date_of_birth' => $this->resource->date_of_birth?->toDateString(),

            // One computed answer rather than two columns for the client to choose between, with
            // a flag saying how much to trust it. "About 7" and "7, born 14 March" are different
            // facts and a UI should be able to show that.
            'age_years' => $this->resource->ageYears(),
            'age_is_approximate' => $this->resource->isAgeApproximate(),

            // Years/months/days, only when a real date of birth exists — lets the UI show
            // "4mo 20d" for a pet under a year old instead of a bare "0" that reads as a bug.
            'age_breakdown' => $this->resource->ageBreakdown(),

            'weight_lb' => $this->resource->weight_lb,

            'coat_type' => $this->resource->coat_type?->value,
            'coat_type_label' => $this->resource->coat_type?->label(),
            'coat_notes' => $this->resource->coat_notes,

            'customer_notes' => $this->resource->customer_notes,
            'temperament_notes' => $this->resource->temperament_notes,
            'special_instructions' => $this->resource->special_instructions,

            // Informational only. §9 and §29 are explicit that health information must never be
            // presented as veterinary diagnosis, so it travels with a flag the UI can render as a
            // caveat rather than being dressed up as a clinical record.
            'medical_notes' => $this->resource->medical_notes,
            'medical_notes_are_not_veterinary_advice' => true,

            'needs_handling_care' => $this->resource->hasHandlingNotes(),

            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'allows_outreach' => $this->resource->allowsOutreach(),

            // Spec §9 asks for a photo; there is no upload path in the product yet (`D-016`), so
            // this is always null today. Present in the contract so the SPA does not need
            // changing when uploads arrive.
            'photo_url' => null,

            // Spec §9's "internal staff notes with permissions", enforced here as well as on the
            // write endpoint. Omitted entirely rather than sent as null: a key that is sometimes
            // null and sometimes absent tells a reader nothing, while a key present with content
            // only for those allowed to see it cannot be leaked by a client that forgot to check.
            ...$this->internalNotes($request),

            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function internalNotes(Request $request): array
    {
        $user = $request->user();

        if ($user === null || ! $user->can(Permission::AccessInternalPetNotes->value)) {
            return [];
        }

        return ['internal_notes' => $this->resource->internal_notes];
    }
}
