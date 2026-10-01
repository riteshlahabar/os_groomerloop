<?php

namespace Modules\Team\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * An exception to the rota (spec §23 "availability"): holiday, sickness, an afternoon out.
 *
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 */
final class StaffTimeOff extends Model
{
    use BelongsToTenant;

    protected $table = 'staff_time_off';

    protected $fillable = [
        'starts_at',
        'ends_at',
        'is_all_day',
        'reason',
    ];

    protected $attributes = [
        'is_all_day' => false,
    ];

    /**
     * @return BelongsTo<StaffMember, $this>
     */
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    /**
     * Does this absence cover the given moment?
     *
     * Half-open on purpose — start inclusive, end exclusive. Time off ending at 13:00 leaves the
     * groomer available at 13:00, which is what "back after lunch" means. Treating both ends as
     * inclusive would make every absence a minute longer than stated and quietly lose the slot that
     * touches its boundary.
     */
    public function covers(DateTimeInterface $moment): bool
    {
        return $this->starts_at <= $moment && $this->ends_at > $moment;
    }

    /**
     * Does this absence overlap a booking that starts at $start and runs to $end?
     *
     * The question Phase 8 actually asks: a groom booked 12:00-13:30 collides with a 13:00 dentist
     * appointment even though it starts before it.
     */
    public function overlaps(DateTimeInterface $start, DateTimeInterface $end): bool
    {
        return $this->starts_at < $end && $this->ends_at > $start;
    }

    /**
     * Absences that could overlap the given range, narrowed in SQL so Phase 8 does not load a
     * groomer's whole holiday history to check one slot.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOverlapping(Builder $query, DateTimeInterface $start, DateTimeInterface $end): Builder
    {
        return $query->where('starts_at', '<', $end)->where('ends_at', '>', $start);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_all_day' => 'boolean',
        ];
    }
}
