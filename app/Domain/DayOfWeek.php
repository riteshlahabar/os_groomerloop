<?php

namespace App\Domain;

use DateTimeInterface;

/**
 * A day of the week, in ISO-8601 numbering.
 *
 * Shared kernel rather than a module's own enum, because three modules need exactly this vocabulary
 * and they must agree: Catalog's per-service availability rules (§10), Team's staff working hours
 * (§23), and Scheduling's business hours and calendar (§11). A per-module copy would be three
 * chances to number the days differently, and the failure mode is silent — a service or a groomer
 * that reads as available on the wrong day.
 *
 * Monday = 1 through Sunday = 7, matching `Carbon::dayOfWeekIso` and `date('N')`. PHP's *native*
 * `date('w')` is Sunday = 0, which differs by one; anything comparing the two numbering schemes is
 * off by a day, and nobody finds that until a customer turns up on a Sunday.
 */
enum DayOfWeek: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    public function label(): string
    {
        return match ($this) {
            self::Monday => 'Monday',
            self::Tuesday => 'Tuesday',
            self::Wednesday => 'Wednesday',
            self::Thursday => 'Thursday',
            self::Friday => 'Friday',
            self::Saturday => 'Saturday',
            self::Sunday => 'Sunday',
        };
    }

    /**
     * Short form, for a calendar column heading.
     */
    public function abbreviation(): string
    {
        return substr($this->label(), 0, 3);
    }

    /**
     * The one correct way to get this from a date. `format('N')` is the ISO number, so this cannot
     * disagree with the case values above.
     */
    public static function fromDate(DateTimeInterface $date): self
    {
        return self::from((int) $date->format('N'));
    }

    /**
     * Saturday and Sunday. Grooming salons work weekends heavily, so this exists for labelling and
     * reporting only — never to decide availability, which is always read from stored hours.
     */
    public function isWeekend(): bool
    {
        return $this === self::Saturday || $this === self::Sunday;
    }

    /**
     * @return list<int>
     */
    public static function values(): array
    {
        return array_map(static fn (self $d): int => $d->value, self::cases());
    }
}
