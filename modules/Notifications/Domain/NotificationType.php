<?php

namespace Modules\Notifications\Domain;

/**
 * The spec §13 message types this module can send.
 *
 * `AppointmentFollowUp`, `RebookingReminder`, `ReviewRequest` and `CustomerRetention` were added
 * 2026-10-05 by Automation (spec §18) — the original docblock here said these would arrive with
 * Reviews (§20) or Retention (§22), but §18 shipped first and needed them, the same "the module
 * that ships second owns the link" shape `D-017` established. §20's own review *collection* flow
 * is still unbuilt; `ReviewRequest` only sends the ask.
 */
enum NotificationType: string
{
    case BookingRequested = 'booking_requested';
    case BookingConfirmed = 'booking_confirmed';
    case BookingCancelled = 'booking_cancelled';
    case BookingRescheduled = 'booking_rescheduled';
    case AppointmentReminder = 'appointment_reminder';
    case NoShowFollowUp = 'no_show_follow_up';
    case AppointmentFollowUp = 'appointment_follow_up';
    case RebookingReminder = 'rebooking_reminder';
    case ReviewRequest = 'review_request';
    case CustomerRetention = 'customer_retention';

    /**
     * Added by the Customer Portal (`D-043`) — not a §13 spec row, but the same catalogue is the
     * honest place for it: one signed link, carried by `NotificationDispatcher` exactly like
     * `cancel_url`/`review_url` already are, rather than a second send path outside this module.
     */
    case AccountClaimLink = 'account_claim_link';

    public function label(): string
    {
        return match ($this) {
            self::BookingRequested => 'Booking requested',
            self::BookingConfirmed => 'Booking confirmed',
            self::BookingCancelled => 'Booking cancelled',
            self::BookingRescheduled => 'Booking rescheduled',
            self::AppointmentReminder => 'Appointment reminder',
            self::NoShowFollowUp => 'No-show follow-up',
            self::AppointmentFollowUp => 'Appointment follow-up',
            self::RebookingReminder => 'Rebooking reminder',
            self::ReviewRequest => 'Review request',
            self::CustomerRetention => 'Customer retention check-in',
            self::AccountClaimLink => 'Portal account access',
        };
    }

    /**
     * Transactional (gated by `CustomerDirectory::mayContact()`) or marketing (the stricter
     * `mayMarketTo()`, invariant #9) — spec §28. A message *about a booking that happened* is
     * transactional even when Automation, not a direct booking action, is what sent it
     * (`AppointmentFollowUp`, `NoShowFollowUp`: closing the loop on a visit already rendered). A
     * message asking for something *next* — a review, a rebooking, a reason to come back — is an
     * ask on the business's behalf, not a record of what already happened, so it is marketing
     * even though its wording is friendly rather than promotional.
     */
    public function isTransactional(): bool
    {
        return match ($this) {
            self::RebookingReminder, self::ReviewRequest, self::CustomerRetention => false,
            default => true,
        };
    }
}
