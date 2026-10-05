<?php

namespace Modules\Pets\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Pets\Database\Factories\SpeciesFactory;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * What kind of animal this is, as the business defines it (spec §9).
 *
 * Replaced the fixed `PetSpecies` enum 2026-10-05, at the owner's explicit request — a
 * tenant-owned table rather than a shared code-level list, the same shape and the same
 * reasoning `Catalog\Models\ServiceCategory` already uses: free text gives one species four
 * spellings, and §16 reporting on the mix needs one spelling to count.
 *
 * @property string $name
 * @property string $slug
 */
final class Species extends Model
{
    /** @use HasFactory<SpeciesFactory> */
    use BelongsToTenant, HasFactory;

    protected static string $factory = SpeciesFactory::class;

    protected $table = 'pet_species';

    protected $fillable = [
        'name',
        'position',
    ];

    protected $attributes = [
        'position' => 0,
    ];

    public static function booted(): void
    {
        self::saving(static function (Species $species): void {
            $species->slug = Str::slug($species->name);
        });
    }

    /**
     * @return HasMany<Pet, $this>
     */
    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class, 'species_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['position' => 'integer'];
    }
}
