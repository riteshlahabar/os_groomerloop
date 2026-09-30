<?php

namespace Modules\Entitlements\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Entitlements\Domain\Feature;
use Modules\Entitlements\Domain\FeatureGrade;
use Modules\Entitlements\Models\Plan;

/**
 * @extends Factory<Plan>
 */
final class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * Deliberately generates a nonsense plan name rather than one of the four real ones.
     *
     * A test that needs "the Growth plan" should seed the real catalog with PlanSeeder; a test
     * that needs "a plan granting exactly these features" should use this factory. Keeping the
     * two apart is what lets PlanLiteralGuardTest ban plan names everywhere but the seeder.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'key' => Str::slug($name, '_'),
            'name' => Str::title($name),
            'tagline' => fake()->sentence(4),
            'price_cents' => fake()->numberBetween(1_000, 50_000),
            'currency' => 'USD',
            'billing_interval' => 'month',
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function default(): self
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    public function inactive(): self
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * Grant a set of features, either as bare cases (Standard) or feature => grade pairs.
     *
     * @param  array<int|string, Feature|FeatureGrade>  $features
     */
    public function granting(array $features): self
    {
        return $this->afterCreating(function (Plan $plan) use ($features): void {
            foreach ($features as $key => $value) {
                [$feature, $grade] = $value instanceof Feature
                    ? [$value, FeatureGrade::Standard]
                    : [Feature::from((string) $key), $value];

                $plan->features()->create([
                    'feature' => $feature->value,
                    'grade' => $grade->value,
                ]);
            }

            $plan->load('features');
        });
    }
}
