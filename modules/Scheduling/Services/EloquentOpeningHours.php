<?php

namespace Modules\Scheduling\Services;

use App\Domain\DayOfWeek;
use Modules\Scheduling\Contracts\OpeningHours;
use Modules\Scheduling\Models\BusinessHour;

/**
 * {@see OpeningHours} over the `business_hours` table.
 *
 * Reads once and caches for the life of the request: a tenant-site page renders the week in both
 * its hero and its footer, and the hours cannot change between those two renders.
 */
final class EloquentOpeningHours implements OpeningHours
{
    /** @var array<int, list<array{starts_at: string, ends_at: string}>>|null */
    private ?array $cache = null;

    /**
     * @return array<int, list<array{starts_at: string, ends_at: string}>>
     */
    public function weekly(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        // Every day present and empty, so a caller rendering a full week needs no day arithmetic.
        $week = [];

        foreach (DayOfWeek::cases() as $day) {
            $week[$day->value] = [];
        }

        $rows = BusinessHour::query()
            ->orderBy('day_of_week')
            ->orderBy('starts_at')
            ->get();

        foreach ($rows as $row) {
            $week[$row->day_of_week->value][] = [
                'starts_at' => $row->startsAtString(),
                'ends_at' => $row->endsAtString(),
            ];
        }

        return $this->cache = $week;
    }

    public function isUnset(): bool
    {
        foreach ($this->weekly() as $windows) {
            if ($windows !== []) {
                return false;
            }
        }

        return true;
    }
}
