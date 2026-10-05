<?php

namespace Modules\Pets\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Pets\Models\Species;

/**
 * @extends Factory<Species>
 */
final class SpeciesFactory extends Factory
{
    protected $model = Species::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Dog', 'Cat', 'Rabbit', 'Bird']),
            'position' => 0,
        ];
    }

    public function named(string $name): self
    {
        return $this->state(fn (): array => ['name' => $name]);
    }
}
