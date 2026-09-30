<?php

namespace Modules\Onboarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Onboarding\Models\BusinessProfile;

/**
 * @extends Factory<BusinessProfile>
 */
final class BusinessProfileFactory extends Factory
{
    protected $model = BusinessProfile::class;

    /**
     * Produces a profile that satisfies the §7 business-details step.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legal_name' => fake()->company().' Grooming',
            'contact_name' => fake()->name(),
            'contact_email' => fake()->unique()->safeEmail(),
            'contact_phone' => fake()->numerify('###-###-####'),
            'address_line_1' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'postal_code' => fake()->postcode(),
            'country' => 'US',
            'service_area' => null,
            'description' => fake()->sentence(),
        ];
    }

    /**
     * A mobile groomer: a service area and no salon address. Spec §3 lists them as a target
     * business, and BusinessProfile::isSufficient() has to accept them.
     */
    public function mobile(): self
    {
        return $this->state(fn () => [
            'address_line_1' => null,
            'city' => null,
            'postal_code' => null,
            'service_area' => 'Within 20 miles of downtown Austin',
        ]);
    }

    /**
     * Too little to satisfy the step: no way to contact them, no idea where they work.
     */
    public function incomplete(): self
    {
        return $this->state(fn () => [
            'contact_email' => null,
            'contact_phone' => null,
            'address_line_1' => null,
            'city' => null,
            'postal_code' => null,
            'service_area' => null,
        ]);
    }
}
