<?php

namespace Modules\Scheduling\Models;

use App\Domain\DayOfWeek;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Scheduling\Database\Factories\BusinessHourFactory;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * One window the business is open (spec §11, §7 "business hours and closed days").
 *
 * More than one row per day is allowed — closing for lunch is real, the same reasoning
 * `StaffWorkingHour` uses. No rows for a day means the business is closed that day.
 *
 * @property DayOfWeek $day_of_week
 */
final class BusinessHour extends Model
{
    /** @use HasFactory<BusinessHourFactory> */
    use BelongsToTenant, HasFactory;

    protected static string $factory = BusinessHourFactory::class;

    protected $table = 'business_hours';

    protected $fillable = [
        'day_of_week',
        'starts_at',
        'ends_at',
    ];

    /**
     * Does this window contain the whole of a booking that starts at $time and runs $minutes?
     * The end is inclusive of the boundary — the same convention every other availability-rule
     * model in this codebase uses.
     */
    public function accommodates(string $time, int $minutes): bool
    {
        $start = self::minutesFromMidnight($time);

        if ($start === null) {
            return false;
        }

        $windowStart = self::minutesFromMidnight($this->startsAtString());
        $windowEnd = self::minutesFromMidnight($this->endsAtString());

        if ($windowStart === null || $windowEnd === null) {
            return false;
        }

        return $start >= $windowStart && ($start + $minutes) <= $windowEnd;
    }

    public function startsAtString(): string
    {
        return $this->timeString('starts_at');
    }

    public function endsAtString(): string
    {
        return $this->timeString('ends_at');
    }

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
