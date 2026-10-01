<?php

namespace Modules\Scheduling\Events;

use Modules\Scheduling\Domain\AppointmentSummary;

/**
 * Fired once a booking commits (spec §11), for any module that cares without Scheduling knowing
 * it exists (D-007's other sanctioned crossing, alongside Contracts). Notifications (§13) listens
 * for this to send the booking-requested/confirmation message; nothing in this module imports
 * Notifications to do that.
 *
 * Carries the same readonly `AppointmentSummary` the `AppointmentScheduler` contract hands other
 * modules — never the Eloquent model, for the same boundary reason.
 */
final readonly class AppointmentBooked
{
    public function __construct(public AppointmentSummary $appointment) {}
}
