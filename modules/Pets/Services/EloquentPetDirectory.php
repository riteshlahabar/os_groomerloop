<?php

namespace Modules\Pets\Services;

use Modules\Pets\Actions\CreatePet;
use Modules\Pets\Actions\EnsureDefaultSpecies;
use Modules\Pets\Actions\UpdatePet;
use Modules\Pets\Contracts\PetDirectory;
use Modules\Pets\Domain\PetSummary;
use Modules\Pets\Models\Pet;
use Modules\Pets\Models\Species;

final class EloquentPetDirectory implements PetDirectory
{
    public function __construct(
        private readonly CreatePet $create,
        private readonly UpdatePet $update,
        private readonly EnsureDefaultSpecies $ensureDefaultSpecies,
    ) {}

    /**
     * The §9 fields a customer may write about their own pet, in one place because both
     * `createForCustomer()` and `updateForCustomer()` must agree exactly.
     *
     * `internal_notes` and `status` are absent deliberately — see the contract's docblocks. Keeping
     * the list here rather than trusting each caller's request class means a future endpoint
     * cannot widen it by forgetting to exclude something.
     *
     * @var list<string>
     */
    private const CUSTOMER_WRITABLE = [
        'name',
        'species_id',
        'breed',
        'sex',
        'date_of_birth',
        'approximate_age_years',
        'weight_lb',
        'coat_type',
        'coat_notes',
        'customer_notes',
        'temperament_notes',
        'special_instructions',
        'medical_notes',
    ];

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

    /**
     * @param  list<int>  $petIds
     * @return array<int, string|null>
     */
    public function namesOf(array $petIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $petIds)));
        $unresolved = array_values(array_diff($ids, array_keys($this->resolved)));

        if ($unresolved !== []) {
            foreach (Pet::query()->whereKey($unresolved)->get() as $pet) {
                $this->resolved[(int) $pet->getKey()] = $pet;
            }

            foreach ($unresolved as $id) {
                $this->resolved[$id] ??= null;
            }
        }

        $names = [];

        foreach ($ids as $id) {
            $names[$id] = $this->resolved[$id]?->name;
        }

        return $names;
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
     * @param  array<string, mixed>  $attributes
     */
    public function createForPublicBooking(int $customerId, array $attributes): int
    {
        return (int) $this->create->execute($customerId, $attributes)->getKey();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function listSpecies(): array
    {
        $this->ensureDefaultSpecies->execute();

        return Species::query()
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Species $s): array => ['id' => (int) $s->getKey(), 'name' => $s->name])
            ->all();
    }

    public function speciesExists(int $speciesId): bool
    {
        return Species::query()->whereKey($speciesId)->exists();
    }

    /**
     * @return list<PetSummary>
     */
    public function summariesForCustomer(int $customerId): array
    {
        return Pet::query()
            ->forCustomer($customerId)
            ->current()
            ->with('species')
            ->orderBy('name')
            ->get()
            ->map(static fn (Pet $pet): PetSummary => new PetSummary(
                id: (int) $pet->getKey(),
                name: $pet->name,
                speciesId: $pet->species_id === null ? null : (int) $pet->species_id,
                speciesName: $pet->species?->name,
                breed: $pet->breed,
                sex: $pet->sex->value,
                dateOfBirth: $pet->date_of_birth?->toDateString(),
                approximateAgeYears: $pet->approximate_age_years === null ? null : (int) $pet->approximate_age_years,
                ageYears: $pet->ageYears(),
                ageIsApproximate: $pet->isAgeApproximate(),
                ageBreakdown: $pet->ageBreakdown(),
                weightLb: $pet->weight_lb === null ? null : (string) $pet->weight_lb,
                coatType: $pet->coat_type?->value,
                coatTypeLabel: $pet->coat_type?->label(),
                coatNotes: $pet->coat_notes,
                customerNotes: $pet->customer_notes,
                temperamentNotes: $pet->temperament_notes,
                specialInstructions: $pet->special_instructions,
                medicalNotes: $pet->medical_notes,
                status: $pet->status->value,
                statusLabel: $pet->status->label(),
            ))
            ->all();
    }

    public function createForCustomer(int $customerId, array $attributes): int
    {
        return (int) $this->create->execute($customerId, $this->customerWritable($attributes))->getKey();
    }

    public function updateForCustomer(int $petId, int $customerId, array $attributes): bool
    {
        $pet = $this->find($petId);

        // Both halves of the question, and in this order. Tenant isolation is already handled by
        // the global scope on `find()`, so this is the within-tenant half the scope cannot answer:
        // one family editing another family's dog in the same business.
        if ($pet === null || (int) $pet->customer_id !== $customerId) {
            return false;
        }

        $this->update->execute($pet, $this->customerWritable($attributes));

        unset($this->resolved[$petId]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function customerWritable(array $attributes): array
    {
        return array_intersect_key($attributes, array_flip(self::CUSTOMER_WRITABLE));
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
