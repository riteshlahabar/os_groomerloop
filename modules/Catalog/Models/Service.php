<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Database\Factories\ServiceFactory;
use Modules\Catalog\Domain\ServiceStatus;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * Something the business sells (spec §10, §26).
 *
 * @property string $name
 * @property int $price_cents
 * @property ServiceStatus $status
 */
final class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use BelongsToTenant, HasFactory;

    protected static string $factory = ServiceFactory::class;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'service_category_id',
        'name',
        'description',
        'price_cents',
        'duration_minutes',
        'buffer_minutes',
        'is_add_on',
        'is_bookable_online',
        'status',
        'position',
    ];

    /**
     * Mirrors the migration's defaults so a service answers correctly before it is re-read.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'buffer_minutes' => 0,
        'is_add_on' => false,
        'is_bookable_online' => true,
        'position' => 0,
    ];

    /**
     * How long this service occupies the calendar, buffer included.
     *
     * The number §11 must reserve, as opposed to the duration §12 shows a customer. Keeping them
     * separate is what stops a salon booking back-to-back grooms with no time to clean down.
     */
    public function occupiesMinutes(): int
    {
        return (int) $this->duration_minutes + (int) $this->buffer_minutes;
    }

    /**
     * May this service be put on a new appointment taken over the counter?
     */
    public function isSellable(): bool
    {
        return $this->status->isSellable();
    }

    /**
     * May a member of the public book it themselves (spec §12)?
     *
     * Both conditions, and an add-on is never independently bookable online — it is chosen
     * alongside a service, not instead of one.
     */
    public function isPubliclyBookable(): bool
    {
        return $this->isSellable()
            && (bool) $this->is_bookable_online
            && ! (bool) $this->is_add_on;
    }

    // --- Relationships --------------------------------------------------------------------

    /**
     * @return BelongsTo<ServiceCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    /**
     * The add-ons that may be attached to this service.
     *
     * @return BelongsToMany<Service, $this>
     */
    public function addOns(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'service_add_on',
            'service_id',
            'add_on_service_id',
        )->withTimestamps();
    }

    /**
     * @return HasMany<ServiceAvailabilityWindow, $this>
     */
    public function availabilityWindows(): HasMany
    {
        return $this->hasMany(ServiceAvailabilityWindow::class);
    }

    // --- Query scopes ---------------------------------------------------------------------

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ServiceStatus::Active->value);
    }

    /**
     * What the §12 public booking page may show. Never trust a client to filter this.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeBookableOnline(Builder $query): Builder
    {
        return $query->active()
            ->where('is_bookable_online', true)
            ->where('is_add_on', false);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeAddOns(Builder $query): Builder
    {
        return $query->where('is_add_on', true);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $q) use ($like): void {
            $q->where('name', 'like', $like)
                ->orWhere('description', 'like', $like);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ServiceStatus::class,
            'price_cents' => 'integer',
            'duration_minutes' => 'integer',
            'buffer_minutes' => 'integer',
            'position' => 'integer',
            'is_add_on' => 'boolean',
            'is_bookable_online' => 'boolean',
        ];
    }
}
