<?php

namespace Modules\Pets\Actions;

use Modules\Audit\Contracts\AuditRecorder;
use Modules\Pets\Domain\PetStatus;
use Modules\Pets\Models\Pet;

/**
 * Take a pet out of the working lists without destroying it (spec §9, invariant #4).
 *
 * A pet carries grooming history, appointment history and the notes that make the next groom go
 * well. A business tidying its list must not be able to lose that, so nothing here deletes.
 */
final class ArchivePet
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(Pet $pet): Pet
    {
        // A deceased pet is already out of the working lists, and archiving it would overwrite
        // the more specific fact with a vaguer one — losing the reason §22 must never contact
        // anyone about it.
        if ($pet->status === PetStatus::Deceased) {
            return $pet;
        }

        $pet->status = PetStatus::Archived;
        $pet->save();

        $this->audit->record('pet.archived', $pet, ['name' => $pet->name]);

        return $pet;
    }
}
