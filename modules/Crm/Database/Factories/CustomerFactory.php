<?php

namespace Modules\Crm\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Crm\Domain\CustomerSource;
use Modules\Crm\Domain\CustomerStatus;
use Modules\Crm\Models\Customer;

/**
 * @extends Factory<Customer>
 */
final class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('512-555-####'),
            'address_line_1' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'postal_code' => fake()->postcode(),
            'country' => 'US',
            'status' => CustomerStatus::Active,
            'source' => CustomerSource::WalkIn,
            'accepts_email' => true,
            'accepts_sms' => false,
            'accepts_push' => false,
            'accepts_marketing' => false,
        ];
    }

    public function lead(): self
    {
        return $this->state(fn () => ['status' => CustomerStatus::Lead]);
    }

    public function archived(): self
    {
        return $this->state(fn () => ['status' => CustomerStatus::Archived]);
    }

    public function optedOut(): self
    {
        return $this->state(fn () => ['opted_out_at' => now()]);
    }

    /**
     * Agrees to everything, including marketing. Useful for testing that the restrictive
     * paths are the ones actually doing the restricting.
     */
    public function fullyConsented(): self
    {
        return $this->state(fn () => [
            'accepts_email' => true,
            'accepts_sms' => true,
            'accepts_push' => true,
            'accepts_marketing' => true,
            'consent_recorded_at' => now(),
            'consent_source' => 'test',
        ]);
    }

    public function withContact(?string $email, ?string $phone = null): self
    {
        return $this->state(fn () => ['email' => $email, 'phone' => $phone]);
    }

    public function named(string $first, ?string $last = null): self
    {
        return $this->state(fn () => ['first_name' => $first, 'last_name' => $last]);
    }
}
