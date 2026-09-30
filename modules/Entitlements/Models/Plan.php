<?php

namespace Modules\Entitlements\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Entitlements\Database\Factories\PlanFactory;
use Modules\Entitlements\Domain\Feature;
use Modules\Entitlements\Domain\FeatureGrade;

/**
 * One row of the spec §2 price list, with its slice of the §25 matrix hanging off it.
 *
 * Not tenant-owned — see the migration. This model is internal to Entitlements; other modules
 * ask the Entitlements contract instead of loading a plan (D-007).
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property int $price_cents
 */
final class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected static string $factory = PlanFactory::class;

    protected $fillable = [
        'key',
        'name',
        'tagline',
        'price_cents',
        'currency',
        'billing_interval',
        'is_default',
        'is_active',
        'sort_order',
    ];

    /**
     * @return HasMany<PlanFeature, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    /**
     * The grades this plan grants, keyed by feature value.
     *
     * Unknown feature strings are skipped rather than throwing: a plan_features row left
     * behind by a removed capability should not take the application down, and the guard test
     * catches the stale row separately.
     *
     * @return array<string, FeatureGrade>
     */
    public function grants(): array
    {
        $grants = [];

        foreach ($this->features as $row) {
            $feature = Feature::tryFrom($row->feature);
            $grade = FeatureGrade::tryFrom($row->grade);

            if ($feature !== null && $grade !== null) {
                $grants[$feature->value] = $grade;
            }
        }

        return $grants;
    }

    public function priceInDollars(): float
    {
        return $this->price_cents / 100;
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('price_cents');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
