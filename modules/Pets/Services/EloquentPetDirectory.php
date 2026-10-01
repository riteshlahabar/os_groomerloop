<?php

namespace Modules\Pets\Services;

use Modules\Pets\Contracts\PetDirectory;
use Modules\Pets\Models\Pet;

final class EloquentPetDirectory implements PetDirectory
{
    /**
     * Memoised per request. Scheduling asks about the same pet more than once while validating a
     * booking, and the answer cannot change mid-request.
     *
     * @var array<int, Pet|null>
     */
    private array $resolved = [];

    public function exists(int $petId): bool
    {
        return $this->find($petId) !== null;
    }

    public function belongsTo(int $petId, int $customerId): bool
    {
        // Fails closed on an unknown pet. A booking that names a pet the business does not have
        // must be refused, not allowed through for something downstream to notice.
        return $this->find($petId)?->customer_id === $customerId;
    }

    public function nameOf(int $petId): ?string
    {
        return $this->find($petId)?->name;
    }

    public function allowsOutreach(int $petId): bool
    {
        return $this->find($petId)?->allowsOutreach() ?? false;
    }

    /**
     * @return list<int>
     */
    public function idsForCustomer(int $customerId): array
    {
        return Pet::query()
            ->forCustomer($customerId)
            ->current()
            ->orderBy('name')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    public function hasAny(): bool
    {
        // current(), not every row: a business whose only pet is archived has not populated its
        // pet records, and the onboarding checklist should say so.
        return Pet::query()->current()->exists();
    }

    /**
     * Tenant-scoped by the global scope, so a pet id from another business resolves to null here
     * exactly as it 404s over HTTP.
     */
    private function find(int $petId): ?Pet
    {
        return $this->resolved[$petId] ??= Pet::query()->find($petId);
    }
}
