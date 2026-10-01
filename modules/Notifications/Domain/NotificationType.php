<?php

namespace Modules\Notifications\Domain;

/**
 * The spec §13 message types this module can send. Review requests, rebooking prompts and
 * announcements are deliberately absent — those are triggered by Phase 2's Reviews (§20) and
 * Retention (§22) modules, which do not exist yet; this enum only grows the types those modules
 * will need once they do, the same "the module that ships second owns the link" shape `D-017`
 * already established.
 */
enum NotificationType: string
{
    case BookingRequested = 'booking_requested';
    case BookingConfirmed = 'booking_confirmed';
    case BookingCancelled = 'booking_cancelled';
    case BookingRescheduled = 'booking_rescheduled';
    case AppointmentReminder = 'appointment_reminder';
    case NoShowFollowUp = 'no_show_follow_up';

    public function label(): string
    {
        return match ($this) {
            self::BookingRequested => 'Booking requested',
            self::BookingConfirmed => 'Booking confirmed',
            self::BookingCancelled => 'Booking cancelled',
            self::BookingRescheduled => 'Booking rescheduled',
            self::AppointmentReminder => 'Appointment reminder',
            self::NoShowFollowUp => 'No-show follow-up',
        };
    }

    /**
     * Transactional, not marketing (spec §28, invariant #9) — gated by
     * `CustomerDirectory::mayContact()`, never `mayMarketTo()`. Every type this enum has today is
     * transactional; a future announcement/review-request type would not be.
     */
    public function isTransactional(): bool
    {
        return true;
    }
}
