<?php

namespace Modules\Scheduling\Policies;

use App\Models\User;
use Modules\Identity\Domain\Permission;
use Modules\Scheduling\Domain\AppointmentStatus;
use Modules\Scheduling\Models\Appointment;

/**
 * Who may move an appointment through its state machine (spec §11, §5).
 *
 * `appointments.update_status` and `appointments.manage` are deliberately separate permissions —
 * a Groomer holds the first but not the second, because a groomer progresses their own day
 * (checked-in → in-service → completed) but must not reschedule or cancel a booking. Cancelling
 * and marking a no-show are both "this appointment is not going to happen" decisions, the same
 * shape as `ManageAppointments` already covers for rescheduling, so both require it here too —
 * a route permission alone cannot express "this status, but not that one."
 */
final class AppointmentPolicy
{
    public function updateStatus(User $actor, Appointment $appointment, AppointmentStatus $next): bool
    {
        if (in_array($next, [AppointmentStatus::Cancelled, AppointmentStatus::NoShow], strict: true)) {
            return $actor->hasPermission(Permission::ManageAppointments);
        }

        return $actor->hasPermission(Permission::UpdateAppointmentStatus);
    }
}
