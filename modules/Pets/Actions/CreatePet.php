<?php

namespace Modules\Pets\Actions;

use Modules\Audit\Contracts\AuditRecorder;
use Modules\Pets\Models\Pet;

/**
 * Add a pet to a customer (spec §9).
 */
final class CreatePet
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(int $customerId, array $attributes): Pet
    {
        $pet = new Pet;
        $pet->fill($attributes);

        // Set outside fill() because customer_id is deliberately not fillable: re-homing a pet
        // moves its whole grooming history to another family, and that must never happen as a
        // side effect of an ordinary edit. That the customer exists, and belongs to this
        // business, is settled by the form request through CustomerDirectory.
        $pet->customer_id = $customerId;

        $pet->save();

        $this->audit->record('pet.created', $pet, [
            'name' => $pet->name,
            'species' => $pet->species->value,
            'customer_id' => $customerId,
        ]);

        return $pet;
    }
}
