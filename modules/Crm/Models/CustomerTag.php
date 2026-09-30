<?php

namespace Modules\Crm\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Modules\Crm\Database\Factories\CustomerTagFactory;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * A label a business puts on its customers (spec §8).
 *
 * @property string $name
 * @property string $slug
 */
final class CustomerTag extends Model
{
    /** @use HasFactory<CustomerTagFactory> */
    use BelongsToTenant, HasFactory;

    protected static string $factory = CustomerTagFactory::class;

    protected $fillable = [
        'name',
        'colour',
    ];

    public static function booted(): void
    {
        // Derived from the name, so "Nervous", "nervous" and "  Nervous  " are one tag
        // rather than three. The unique index on (tenant_id, slug) then does the work.
        static::saving(static function (CustomerTag $tag): void {
            $tag->slug = Str::slug($tag->name);
        });
    }

    /**
     * @return BelongsToMany<Customer, $this>
     */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_tag')
            ->withTimestamps();
    }
}
