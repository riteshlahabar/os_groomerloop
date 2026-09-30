<?php

namespace Modules\Crm\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Crm\Models\CustomerTag;

/**
 * @extends Factory<CustomerTag>
 */
final class CustomerTagFactory extends Factory
{
    protected $model = CustomerTag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'colour' => '#3366cc',
        ];
    }

    public function named(string $name): self
    {
        return $this->state(fn () => ['name' => $name]);
    }
}
