<?php

namespace Modules\Automation\Domain;

use Modules\Notifications\Domain\NotificationType;
use Modules\Scheduling\Events\AppointmentStatusChanged;

/**
 * The spec §18 trigger/action catalogue, as a fixed, named list rather than a generic rule
 * builder — every pair in the spec's own table is enumerable, so there is nothing a free-form
 * "trigger + conditions + action" editor would buy here that this enum does not already state
 * more plainly. Each case corresponds to exactly one spec §18 row:
 *
 *   - "New booking → send confirmation" and "Upcoming appointment → send reminder" are **not**
 *     here. Both already happen unconditionally today — `SendAppointmentBookedNotification` and
 *     `notifications:send-reminders` — and are not configurable or disableable. Turning either
 *     into an optional "automation" a tenant could switch off would be a behaviour change to a
 *     mechanism customers already rely on, not a new capability; left alone deliberately.
 *   - "New inquiry → create lead + notify staff" is **not** here either: nothing in the product
 *     captures an inquiry anywhere today (a tenant's own §14 website only links to the booking
 *     wizard, no contact/inquiry form exists), so there is no event or record to trigger from.
 *     Blocked on that gap, not on this module.
 *
 * The five that remain are genuinely new behaviour, each off by default (`automation_settings`
 * has no row until a tenant turns one on) — spec §18's own "disable controls" requirement, read
 * as "nothing fires until the owner asks for it," the same direction invariant #4 already
 * defaults every other optional capability.
 */
enum AutomationKey: string
{
    case AppointmentCompletedFollowUp = 'appointment_completed_follow_up';
    case RebookingReminder = 'rebooking_reminder';
    case ReviewRequest = 'review_request';
    case NoShowFollowUp = 'no_show_follow_up';
    case CustomerRetentionTag = 'customer_retention_tag';

    public function label(): string
    {
        return match ($this) {
            self::AppointmentCompletedFollowUp => 'Appointment follow-up',
            self::RebookingReminder => 'Rebooking reminder',
            self::ReviewRequest => 'Review request',
            self::NoShowFollowUp => 'No-show follow-up',
            self::CustomerRetentionTag => 'Inactive customer tag',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::AppointmentCompletedFollowUp => 'Send a message as soon as an appointment is marked completed.',
            self::RebookingReminder => 'Remind a customer to rebook a set number of days after a completed appointment, if they haven\'t already.',
            self::ReviewRequest => 'Ask a customer for a review a set number of days after a completed appointment.',
            self::NoShowFollowUp => 'Send a message a set number of days after a no-show.',
            self::CustomerRetentionTag => 'Tag a customer when they haven\'t booked in a set number of days.',
        };
    }

    /**
     * True for the four candidates `automation:run-due` sweeps on a delay; false for the one
     * that fires immediately off {@see AppointmentStatusChanged}.
     */
    public function needsDelay(): bool
    {
        return $this !== self::AppointmentCompletedFollowUp;
    }

    public function defaultDelayDays(): ?int
    {
        return match ($this) {
            self::AppointmentCompletedFollowUp => null,
            self::RebookingReminder => 60,
            self::ReviewRequest => 2,
            self::NoShowFollowUp => 1,
            self::CustomerRetentionTag => 90,
        };
    }

    /**
     * Null for `CustomerRetentionTag`, which tags rather than sends a message.
     */
    public function notificationType(): ?NotificationType
    {
        return match ($this) {
            self::AppointmentCompletedFollowUp => NotificationType::AppointmentFollowUp,
            self::RebookingReminder => NotificationType::RebookingReminder,
            self::ReviewRequest => NotificationType::ReviewRequest,
            self::NoShowFollowUp => NotificationType::NoShowFollowUp,
            self::CustomerRetentionTag => null,
        };
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }
}
