<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Domain\InvoiceStatus;
use Modules\Billing\Models\Invoice;

/**
 * @extends Factory<Invoice>
 */
final class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->numberBetween(1_000, 50_000);

        return [
            'number' => 'GL-'.date('Y').'-'.fake()->unique()->numerify('######'),
            'status' => InvoiceStatus::Open,
            'description' => 'Monthly subscription',
            'subtotal_cents' => $amount,
            'tax_cents' => 0,
            'total_cents' => $amount,
            'currency' => 'USD',
            'gateway' => 'fake',
            'issued_at' => now(),
            'due_at' => now()->addDays(7),
        ];
    }

    public function paid(): self
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
            'gateway_charge_id' => 'fake_ch_'.fake()->lexify('????????'),
        ]);
    }

    public function declined(): self
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::Open,
            'failure_code' => 'card_declined',
            'failure_message' => 'The card was declined.',
        ]);
    }
}
