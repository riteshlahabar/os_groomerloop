<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Models\PaymentMethod;

/**
 * @extends Factory<PaymentMethod>
 */
final class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gateway' => 'fake',
            'token' => 'fake_pm_'.fake()->unique()->lexify('????????????'),
            'brand' => 'Visa',
            'last_four' => (string) fake()->numberBetween(1000, 9999),
            'expiry_month' => 12,
            'expiry_year' => (int) date('Y') + 3,
            'is_default' => true,
        ];
    }

    public function expired(): self
    {
        return $this->state(fn () => [
            'expiry_month' => 1,
            'expiry_year' => (int) date('Y') - 1,
        ]);
    }
}
