<?php

namespace Modules\Pets\Http\Controllers\Api\V1;

use Modules\Pets\Actions\RecordInternalPetNote;
use Modules\Pets\Http\Requests\RecordInternalNoteRequest;
use Modules\Pets\Http\Resources\PetResource;
use Modules\Pets\Models\Pet;

/**
 * The staff-only notes on a pet (spec §9, "internal staff notes with permissions").
 *
 * Its own controller, its own endpoint and its own permission, for the same reason customer
 * consent has its own: keeping it off the general update route means one code path can write it,
 * the audit trail for "who wrote this about a customer's dog" is a single event type, and a role
 * that may not read the notes cannot reach them through an ordinary edit.
 *
 * Gated on `pets.internal_notes`, which a Groomer holds while ManagePets does not — the person
 * handling the animal is both the one who needs the handling history and the one who learns it.
 */
final class PetInternalNoteController
{
    public function __invoke(
        RecordInternalNoteRequest $request,
        Pet $pet,
        RecordInternalPetNote $record,
    ): PetResource {
        return PetResource::make($record->execute($pet, $request->notes()));
    }
}
