<?php

namespace Modules\Pets\Actions;

use Modules\Audit\Contracts\AuditRecorder;
use Modules\Pets\Domain\PetStatus;
use Modules\Pets\Models\Pet;

/**
 * Edit a pet (spec §9).
 */
final class UpdatePet
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Pet $pet, array $attributes): Pet
    {
        $wasDeceased = $pet->status === PetStatus::Deceased;

        $pet->fill($attributes);

        // Only the keys that actually moved. An audit trail that records every field on every
        // edit is one nobody reads.
        $changed = array_keys($pet->getDirty());

        $pet->save();

        if ($changed !== []) {
            $this->audit->record('pet.updated', $pet, ['changed' => $changed]);
        }

        // Its own event, because it is not an ordinary field change: from here on, §22 retention
        // must never generate a rebooking prompt about this animal, and the business may need to
        // show when and by whom that was recorded if a message ever does go out.
        if (! $wasDeceased && $pet->status === PetStatus::Deceased) {
            $this->audit->record('pet.marked_deceased', $pet, ['name' => $pet->name]);
        }

        return $pet;
    }
}
