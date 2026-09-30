<?php

namespace Modules\Crm\Domain;

/**
 * Where a customer stands with the business (spec §8 "Customer tags/status/source").
 *
 * Four states, and the boundaries matter for §22 retention and §17 growth:
 *
 *   Lead     — enquired, never booked. The §17 "contact capture" and the marketing site's
 *              lead form both land here. Counting these as customers would flatter every
 *              conversion number the product reports.
 *   Active   — has booked, and is still coming.
 *   Inactive — has booked before but has not been seen for a configured period. This is the
 *              §22 "inactive-customer identification" cohort, and the whole point of it is
 *              that they are a retention opportunity, not a dead record.
 *   Archived — deliberately set aside by the business. Hidden from day-to-day lists, never
 *              deleted (invariant #4), and still counted in history.
 */
enum CustomerStatus: string
{
    case Lead = 'lead';
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Lead => 'Lead',
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Archived => 'Archived',
        };
    }

    /**
     * Does this customer appear in the ordinary working lists?
     */
    public function isCurrent(): bool
    {
        return $this !== self::Archived;
    }

    /**
     * Has this customer ever actually been a customer?
     *
     * Used by §16 and §36 counts so a book full of unconverted leads does not read as a
     * thriving business.
     */
    public function hasConverted(): bool
    {
        return $this !== self::Lead;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }
}
