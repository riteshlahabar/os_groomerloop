<?php

namespace Modules\Tenancy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Tenancy\Domain\TenantStatus;
use Modules\Tenancy\Models\Tenant;

/**
 * @extends Factory<Tenant>
 */
final class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company().' Grooming';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->numerify('###-###-####'),
            'timezone' => fake()->randomElement([
                'America/New_York',
                'America/Chicago',
                'America/Denver',
                'America/Los_Angeles',
            ]),
            'status' => TenantStatus::Active,
        ];
    }

    public function suspended(): self
    {
        return $this->state(fn () => ['status' => TenantStatus::Suspended]);
    }
}
