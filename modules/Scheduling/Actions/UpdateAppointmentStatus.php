<?php

namespace Modules\Scheduling\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Scheduling\Domain\AppointmentStatus;
use Modules\Scheduling\Models\Appointment;
use Modules\Scheduling\Models\AppointmentStatusHistory;

/**
 * Move an appointment through its state machine (spec §11).
 *
 * The one place `status` is ever written — `UpdateAppointment` (plain edits) and
 * `RescheduleAppointment` (time changes) both deliberately exclude it, the exact bug Team's
 * `UpdateStaffMemberRequest` had to be fixed to avoid repeating here.
 */
final class UpdateAppointmentStatus
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(Appointment $appointment, AppointmentStatus $next, ?string $note = null): Appointment
    {
        $current = $appointment->status;

        if (! $current->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => "An appointment cannot move from \"{$current->label()}\" to \"{$next->label()}\".",
            ]);
        }

        if ($current === $next) {
            return $appointment;
        }

        if ($next === AppointmentStatus::CheckedIn && $appointment->staff_member_id === null) {
            throw ValidationException::withMessages([
                'status' => 'A groomer must be assigned before check-in.',
            ]);
        }

        DB::transaction(function () use ($appointment, $current, $next, $note): void {
            $appointment->status = $next;
            $appointment->save();

            AppointmentStatusHistory::query()->create([
                'appointment_id' => $appointment->getKey(),
                'from_status' => $current->value,
                'to_status' => $next->value,
                'changed_by' => auth()->id(),
                'note' => $note,
            ]);
        });

        $this->audit->record('appointment.status_changed', $appointment, [
            'from' => $current->value,
            'to' => $next->value,
        ]);

        return $appointment->refresh();
    }
}
