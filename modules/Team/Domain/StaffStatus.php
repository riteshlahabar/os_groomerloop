<?php

namespace Modules\Team\Domain;

/**
 * Whether a member of staff is still working here (spec §23 "invite/deactivate staff").
 *
 * Two states, and nothing deletes. A groomer who leaves has months of appointments behind them and
 * §11 history has to keep naming who did the work — a deleted row would leave last year's
 * appointments attributed to nobody (invariant #4).
 *
 * `Inactive` is the leaver: they stop appearing in the calendar's groomer picker, stop being
 * assignable to new appointments, and stop being offered on the §12 booking page — while every
 * appointment they have already done still resolves their name.
 */
enum StaffStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'No longer working here',
        };
    }

    /**
     * May this person be put on a new appointment?
     */
    public function isAssignable(): bool
    {
        return $this === self::Active;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }
}
