<?php

namespace Modules\Scheduling\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Scheduling\Domain\WaitlistStatus;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * One customer's standing request for a service that had no open slot (spec §11).
 *
 * `customer_id`, `pet_id`, `service_id` and `staff_member_id` are plain foreign-key columns, the
 * same `Appointment` convention — never Eloquent relations into Crm's/Pets'/Catalog's/Team's own
 * models (`D-007`). `appointment_id` is Scheduling's own column pointing at its own table, so it
 * is the one id here that may be a real relation.
 */
final class WaitlistEntry extends Model
{
    use BelongsToTenant;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'pet_id',
        'service_id',
        'staff_member_id',
        'requested_date',
        'notes',
    ];

    protected $attributes = [
        'status' => 'waiting',
    ];

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', WaitlistStatus::Waiting->value);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WaitlistStatus::class,
            'requested_date' => 'date',
        ];
    }
}
