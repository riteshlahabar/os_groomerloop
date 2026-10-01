<?php

namespace Modules\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Domain\ServiceStatus;
use Modules\Catalog\Models\Service;

/**
 * @extends Factory<Service>
 */
final class ServiceFactory extends Factory
{
    protected $model = Service::class;

    /**
     * A realistic default: a full groom, an hour, with fifteen minutes after it to clean down.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Full Groom', 'Bath & Brush', 'Puppy Introduction', 'De-shed Treatment']),
            'description' => 'Wash, dry, brush out, nails and ears.',
            'price_cents' => 6500,
            'duration_minutes' => 60,
            'buffer_minutes' => 15,
            'is_add_on' => false,
            'is_bookable_online' => true,
            'status' => ServiceStatus::Active,
            'position' => 0,
        ];
    }

    public function named(string $name): self
    {
        return $this->state(fn (): array => ['name' => $name]);
    }

    /**
     * Dollars in, cents stored — so a test reads like a price list.
     */
    public function priced(float $dollars): self
    {
        return $this->state(fn (): array => ['price_cents' => (int) round($dollars * 100)]);
    }

    public function lasting(int $minutes, int $buffer = 0): self
    {
        return $this->state(fn (): array => [
            'duration_minutes' => $minutes,
            'buffer_minutes' => $buffer,
        ]);
    }

    public function addOn(): self
    {
        return $this->state(fn (): array => [
            'name' => fake()->randomElement(['Nail Trim', 'Teeth Brushing', 'Ear Clean', 'Flea Treatment']),
            'is_add_on' => true,
            'price_cents' => 1200,
            'duration_minutes' => 10,
            'buffer_minutes' => 0,
        ]);
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['status' => ServiceStatus::Inactive]);
    }

    /**
     * Sold in the salon but not published online — the case that makes §10's visibility flag
     * separate from its status.
     */
    public function notBookableOnline(): self
    {
        return $this->state(fn (): array => ['is_bookable_online' => false]);
    }

    public function inCategory(int $categoryId): self
    {
        return $this->state(fn (): array => ['service_category_id' => $categoryId]);
    }
}
