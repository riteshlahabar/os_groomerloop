<?php

namespace Modules\Team\Models;

use App\Domain\DayOfWeek;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * One shift in a groomer's normal week (spec §23 "working hours").
 *
 * More than one row per day is allowed, because a split shift is real: in at nine, out for the school
 * run, back at two. That is the difference from a service's availability window, which is one row per
 * day on purpose — a service describing two windows would be describing staff availability.
 *
 * @property DayOfWeek $day_of_week
 */
final class StaffWorkingHour extends Model
{
    use BelongsToTenant;

    protected $table = 'staff_working_hours';

    protected $fillable = [
        'day_of_week',
        'starts_at',
        'ends_at',
    ];

    /**
     * @return BelongsTo<StaffMember, $this>
     */
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    /**
     * Does this shift contain the whole of a booking starting at $time and running $minutes?
     *
     * The end is inclusive: a shift ending at 17:00 accepts work that finishes exactly at 17:00.
     * Anything stricter would make "until five" mean 16:59, and the buffer minutes a service carries
     * would push perfectly ordinary appointments out of a full day.
     */
    public function accommodates(string $time, int $minutes): bool
    {
        $start = self::minutesFromMidnight($time);

        if ($start === null) {
            return false;
        }

        $shiftStart = self::minutesFromMidnight($this->startsAtString());
        $shiftEnd = self::minutesFromMidnight($this->endsAtString());

        if ($shiftStart === null || $shiftEnd === null) {
            return false;
        }

        return $start >= $shiftStart && ($start + $minutes) <= $shiftEnd;
    }

    public function startsAtString(): string
    {
        return $this->timeString('starts_at');
    }

    public function endsAtString(): string
    {
        return $this->timeString('ends_at');
    }

    /**
     * MySQL hands a TIME column back as "09:00:00"; a client that sent "09:00" should read the same
     * shape it wrote, or a form marks itself dirty on every load.
     */
    private function timeString(string $attribute): string
    {
        return substr((string) $this->getAttribute($attribute), 0, 5);
    }

    public static function minutesFromMidnight(string $time): ?int
    {
        if (preg_match('/^(\d{1,2}):(\d{2})/', $time, $matches) !== 1) {
            return null;
        }

        return ((int) $matches[1] * 60) + (int) $matches[2];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['day_of_week' => DayOfWeek::class];
    }
}
