<?php

namespace Modules\Scheduling\Domain;

/**
 * The three states a waitlist entry (spec §11) ever holds. Deliberately smaller than
 * `AppointmentStatus`: there is no "offered" state, because nothing in this product yet notifies
 * a waiting customer when a slot opens (that is Notifications, §13, blocked on `D-011`) — staff
 * finds the opening and converts the entry directly.
 */
enum WaitlistStatus: string
{
    case Waiting = 'waiting';
    case Booked = 'booked';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Waiting',
            self::Booked => 'Booked',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isTerminal(): bool
    {
        return $this !== self::Waiting;
    }
}
