<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Domain\SubscriptionStatus;
use Modules\Billing\Models\Subscription;
use Modules\Entitlements\Models\Plan;

/**
 * @extends Factory<Subscription>
 */
final class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'status' => SubscriptionStatus::Active,
            'gateway' => 'fake',
            'gateway_customer_id' => 'fake_cus_'.fake()->unique()->lexify('????????'),
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ];
    }

    public function onPlan(Plan $plan): self
    {
        return $this->state(fn () => ['plan_id' => $plan->getKey()]);
    }

    public function withStatus(SubscriptionStatus $status): self
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function trialing(int $days = 14): self
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDays($days),
        ]);
    }

    public function pastDue(int $attempts = 1): self
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::PastDue,
            'failed_payment_count' => $attempts,
            'last_payment_failed_at' => now(),
        ]);
    }

    public function inGrace(int $daysRemaining = 7): self
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Grace,
            'failed_payment_count' => 3,
            'grace_ends_at' => now()->addDays($daysRemaining),
        ]);
    }

    public function graceExpired(): self
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Grace,
            'failed_payment_count' => 3,
            'grace_ends_at' => now()->subDay(),
        ]);
    }

    public function cancelled(): self
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now()->subDay(),
            'ends_at' => now()->subDay(),
        ]);
    }
}
