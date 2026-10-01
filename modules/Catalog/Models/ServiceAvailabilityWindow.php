<?php

namespace Modules\Catalog\Models;

use App\Domain\DayOfWeek;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * One day's window in which a service may be booked (spec §10 "availability rules").
 *
 * A service with no windows at all is unrestricted — available whenever the business is open. That
 * default matters: the common case is a salon that sells everything all week, and it must not have
 * to fill in seven rows to say so.
 *
 * @property DayOfWeek $day_of_week
 */
final class ServiceAvailabilityWindow extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'day_of_week',
        'starts_at',
        'ends_at',
    ];

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Does this window contain the whole of a booking that starts at $time and runs $minutes?
     *
     * The end is inclusive of the boundary: a window ending at 12:00 accepts a groom that finishes
     * exactly at 12:00. Anything stricter would make a salon's "mornings until noon" mean 11:59.
     */
    public function accommodates(string $time, int $minutes): bool
    {
        $start = $this->minutesFromMidnight($time);

        if ($start === null) {
            return false;
        }

        return $start >= $this->minutesFromMidnight($this->startsAtString())
            && ($start + $minutes) <= $this->minutesFromMidnight($this->endsAtString());
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
     * shape it wrote, so both are normalised to HH:MM here rather than in each consumer.
     */
    private function timeString(string $attribute): string
    {
        $value = (string) $this->getAttribute($attribute);

        return substr($value, 0, 5);
    }

    private function minutesFromMidnight(string $time): ?int
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
