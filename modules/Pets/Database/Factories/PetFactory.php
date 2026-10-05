<?php

namespace Modules\Pets\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Pets\Domain\CoatType;
use Modules\Pets\Domain\PetSex;
use Modules\Pets\Domain\PetStatus;
use Modules\Pets\Models\Pet;
use Modules\Pets\Models\Species;

/**
 * @extends Factory<Pet>
 */
final class PetFactory extends Factory
{
    protected $model = Pet::class;

    /**
     * No customer_id default.
     *
     * A pet without an owner is not a state the application should be able to reach, and the
     * column is not nullable — so every test has to say whose pet it is, which is also the thing
     * most worth being explicit about in a test.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Bella', 'Luna', 'Max', 'Charlie', 'Daisy', 'Cooper']),
            // firstOrCreate rather than Species::factory(): a fresh factory call per pet would
            // collide with the unique (tenant_id, slug) index the second time any test makes a
            // second "Dog" within the same tenant.
            'species_id' => fn (): int => self::speciesNamed('Dog'),
            'breed' => fake()->randomElement(['Labrador', 'Poodle', 'Cockapoo', 'Shih Tzu', 'Collie']),
            'sex' => PetSex::Unknown,
            'coat_type' => CoatType::Medium,
            'weight_lb' => fake()->randomFloat(1, 5, 90),
            'status' => PetStatus::Active,
        ];
    }

    public function of(int $customerId): self
    {
        // forceFill through the state, because customer_id is deliberately not fillable — moving
        // a pet between families is not something an ordinary write may do.
        return $this->state(fn (): array => ['customer_id' => $customerId]);
    }

    public function named(string $name): self
    {
        return $this->state(fn (): array => ['name' => $name]);
    }

    public function cat(): self
    {
        return $this->state(fn (): array => [
            'species_id' => self::speciesNamed('Cat'),
            'breed' => fake()->randomElement(['Persian', 'Maine Coon', 'Domestic Shorthair']),
        ]);
    }

    /**
     * Shared across every pet a test creates in the same tenant, rather than one new row per
     * pet — `Species::factory()` alone would collide with the unique (tenant_id, slug) index the
     * second time any test made a second "Dog".
     */
    private static function speciesNamed(string $name): int
    {
        return Species::query()->firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name],
        )->getKey();
    }

    public function archived(): self
    {
        return $this->state(fn (): array => ['status' => PetStatus::Archived]);
    }

    public function deceased(): self
    {
        return $this->state(fn (): array => ['status' => PetStatus::Deceased]);
    }

    public function bornOn(string $date): self
    {
        return $this->state(fn (): array => ['date_of_birth' => $date, 'approximate_age_years' => null]);
    }

    public function aboutYearsOld(int $years): self
    {
        return $this->state(fn (): array => ['date_of_birth' => null, 'approximate_age_years' => $years]);
    }

    /**
     * A pet the groomer needs warning about before they start.
     */
    public function needsHandlingCare(): self
    {
        return $this->state(fn (): array => [
            'temperament_notes' => 'Nervous of clippers around the face.',
            'special_instructions' => 'Muzzle for nail trims.',
        ]);
    }

    public function withInternalNote(string $note): self
    {
        return $this->state(fn (): array => ['internal_notes' => $note]);
    }
}
