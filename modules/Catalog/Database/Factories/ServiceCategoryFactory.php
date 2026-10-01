<?php

namespace Modules\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Models\ServiceCategory;

/**
 * @extends Factory<ServiceCategory>
 */
final class ServiceCategoryFactory extends Factory
{
    protected $model = ServiceCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Grooming', 'Bathing', 'Extras', 'Cat Grooming']),
            'position' => 0,
        ];
    }

    public function named(string $name): self
    {
        return $this->state(fn (): array => ['name' => $name]);
    }

    public function atPosition(int $position): self
    {
        return $this->state(fn (): array => ['position' => $position]);
    }
}
