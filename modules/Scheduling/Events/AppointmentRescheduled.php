<?php

namespace Modules\Scheduling\Events;

use DateTimeImmutable;
use Modules\Scheduling\Domain\AppointmentSummary;

/**
 * Fired when an appointment moves to a new time (spec §11). Notifications (§13) listens for this
 * to send the reschedule notice.
 */
final readonly class AppointmentRescheduled
{
    public function __construct(
        public AppointmentSummary $appointment,
        public DateTimeImmutable $previousStart,
    ) {}
}
