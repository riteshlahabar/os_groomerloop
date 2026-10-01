<?php

namespace Modules\Scheduling\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Catalog\Contracts\ServiceCatalog;
use Modules\Scheduling\Models\Appointment;
use Modules\Scheduling\Services\AvailabilityEngine;
use Modules\Team\Models\StaffMember;

/**
 * Move an appointment to a new time (spec §11). Same concurrency shape as `BookAppointment` —
 * lock the staff member, re-check availability, excluding this appointment's own (about to be
 * replaced) slot from the conflict check.
 */
final class RescheduleAppointment
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly AvailabilityEngine $availability,
        private readonly ServiceCatalog $catalog,
    ) {}

    public function execute(Appointment $appointment, Carbon $start): Appointment
    {
        if ($appointment->status->isTerminal()) {
            throw ValidationException::withMessages([
                'starts_at' => 'A completed, cancelled or no-show appointment cannot be rescheduled.',
            ]);
        }

        $service = $this->catalog->find($appointment->service_id);
        $staffMemberId = $appointment->staff_member_id;
        $previousStart = $appointment->starts_at;

        return DB::transaction(function () use ($appointment, $start, $service, $staffMemberId, $previousStart): Appointment {
            if ($staffMemberId !== null) {
                StaffMember::query()->whereKey($staffMemberId)->lockForUpdate()->first();
            }

            if (! $this->availability->isAvailable(
                $appointment->service_id,
                $staffMemberId,
                $start,
                excludingAppointmentId: $appointment->getKey(),
            )) {
                throw ValidationException::withMessages([
                    'starts_at' => 'That slot is no longer available.',
                ]);
            }

            $appointment->starts_at = $start;
            $appointment->ends_at = $start->copy()->addMinutes($service->occupiesMinutes);
            $appointment->save();

            $this->audit->record('appointment.rescheduled', $appointment, [
                'from' => $previousStart->toIso8601String(),
                'to' => $start->toIso8601String(),
            ]);

            return $appointment->refresh();
        });
    }
}
