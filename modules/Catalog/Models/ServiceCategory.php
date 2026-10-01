<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Catalog\Database\Factories\ServiceCategoryFactory;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * How a business groups its menu (spec §10).
 *
 * @property string $name
 * @property string $slug
 */
final class ServiceCategory extends Model
{
    /** @use HasFactory<ServiceCategoryFactory> */
    use BelongsToTenant, HasFactory;

    protected static string $factory = ServiceCategoryFactory::class;

    protected $fillable = [
        'name',
        'position',
    ];

    protected $attributes = [
        'position' => 0,
    ];

    public static function booted(): void
    {
        // Derived from the name, so "Full Groom", "full groom" and "  Full  Groom " are one
        // category rather than three. The unique index on (tenant_id, slug) then does the work.
        self::saving(static function (ServiceCategory $category): void {
            $category->slug = Str::slug($category->name);
        });
    }

    /**
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'service_category_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['position' => 'integer'];
    }
}
