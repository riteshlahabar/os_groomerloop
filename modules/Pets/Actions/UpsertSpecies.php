<?php

namespace Modules\Pets\Actions;

use Illuminate\Support\Str;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Pets\Models\Species;

/**
 * Add a species to the business's list, or hand back the one that already covers it (spec §9).
 * Mirrors `Catalog\Actions\UpsertServiceCategory` exactly, for the same reason: looked up by
 * slug and created by name, never `firstOrCreate`, which would mass-assign the derived lookup
 * key.
 */
final class UpsertSpecies
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * Null when the name carries nothing to slug — two such names would collide on the unique
     * index.
     */
    public function execute(string $name, int $position = 0): ?Species
    {
        $name = trim($name);
        $slug = Str::slug($name);

        if ($slug === '') {
            return null;
        }

        $species = Species::query()->where('slug', $slug)->first();

        if ($species !== null) {
            return $species;
        }

        $species = Species::query()->create([
            'name' => $name,
            'position' => $position,
        ]);

        $this->audit->record('pet_species.created', $species, ['name' => $species->name]);

        return $species;
    }
}
