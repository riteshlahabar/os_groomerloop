<?php

namespace Modules\Scheduling\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Scheduling\Database\Factories\AppointmentFactory;
use Modules\Scheduling\Domain\AppointmentStatus;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * One slot on the calendar (spec §11).
 *
 * `customer_id`, `pet_id`, `service_id` and `staff_member_id` are plain foreign-key columns,
 * never Eloquent relations to Crm's/Pets'/Catalog's/Team's own models (`D-007`'s module
 * boundary rule) — each is validated at write time through that module's own contract
 * (`CustomerDirectory`, `PetDirectory`, `ServiceCatalog`, `StaffDirectory`) and read back the
 * same way when a response needs a name rather than just an id.
 *
 * @property AppointmentStatus $status
 */
final class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use BelongsToTenant, HasFactory;

    protected static string $factory = AppointmentFactory::class;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'pet_id',
        'service_id',
        'staff_member_id',
        'starts_at',
        'ends_at',
        'status',
        'customer_notes',
        'internal_notes',
        'recurrence_group_id',
    ];

    protected $attributes = [
        'status' => 'requested',
    ];

    /**
     * @return HasMany<AppointmentStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(AppointmentStatusHistory::class)->orderBy('created_at');
    }

    /**
     * @return HasMany<AppointmentAddOn, $this>
     */
    public function addOns(): HasMany
    {
        return $this->hasMany(AppointmentAddOn::class);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForStaff(Builder $query, int $staffMemberId): Builder
    {
        return $query->where('staff_member_id', $staffMemberId);
    }

    /**
     * Appointments that still occupy a slot — i.e. not cancelled or no-show — overlapping the
     * given window. The exact predicate `StaffTimeOff::overlaps()` already established.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOverlapping(Builder $query, \DateTimeInterface $start, \DateTimeInterface $end): Builder
    {
        return $query
            ->whereIn('status', array_map(
                static fn (AppointmentStatus $s): string => $s->value,
                array_values(array_filter(AppointmentStatus::cases(), fn (AppointmentStatus $s): bool => $s->occupiesSlot()))
            ))
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AppointmentStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
