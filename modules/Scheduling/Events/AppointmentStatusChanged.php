<?php

namespace Modules\Scheduling\Events;

use Modules\Scheduling\Domain\AppointmentStatus;
use Modules\Scheduling\Domain\AppointmentSummary;

/**
 * Fired on every state-machine transition (spec §11), including cancellation and no-show.
 * Notifications (§13) listens for this to send the right message per destination status
 * (confirmed, cancelled, no-show follow-up) rather than Scheduling knowing what each one means
 * to send.
 */
final readonly class AppointmentStatusChanged
{
    public function __construct(
        public AppointmentSummary $appointment,
        public AppointmentStatus $from,
        public AppointmentStatus $to,
    ) {}
}
