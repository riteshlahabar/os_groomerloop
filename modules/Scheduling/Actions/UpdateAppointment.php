<?php

namespace Modules\Scheduling\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Scheduling\Models\Appointment;
use Modules\Scheduling\Services\AvailabilityEngine;
use Modules\Team\Contracts\StaffDirectory;
use Modules\Team\Models\StaffMember;

/**
 * Edit an appointment's notes, add-ons or assigned groomer — never its time (that's
 * `RescheduleAppointment`) or its status (`UpdateAppointmentStatus`), the same one-action-per-
 * concern split Team's lifecycle actions use.
 *
 * Reassigning the groomer still needs the full availability re-check at the appointment's
 * existing time, so it shares `AvailabilityEngine` and the same staff-row lock as booking —
 * only the service and staff eligibility checks a brand new appointment needs are skipped here.
 */
final class UpdateAppointment
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly AvailabilityEngine $availability,
        private readonly StaffDirectory $staff,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>|null  $addOnServiceIds  null leaves add-ons alone; [] clears them
     */
    public function execute(Appointment $appointment, array $attributes, ?array $addOnServiceIds = null): Appointment
    {
        $reassigningStaff = array_key_exists('staff_member_id', $attributes)
            && $attributes['staff_member_id'] !== $appointment->staff_member_id;

        return DB::transaction(function () use ($appointment, $attributes, $addOnServiceIds, $reassigningStaff): Appointment {
            if ($reassigningStaff) {
                $this->reassignStaff($appointment, $attributes['staff_member_id']);
            }

            $appointment->fill($attributes);
            $changed = array_keys($appointment->getDirty());
            $appointment->save();

            if ($addOnServiceIds !== null) {
                $appointment->addOns()->delete();
                foreach (array_unique($addOnServiceIds) as $addOnId) {
                    $appointment->addOns()->create(['service_id' => (int) $addOnId]);
                }
            }

            if ($changed !== [] || $addOnServiceIds !== null) {
                $this->audit->record('appointment.updated', $appointment, [
                    'changed' => $changed,
                    'add_ons_changed' => $addOnServiceIds !== null,
                ]);
            }

            return $appointment->refresh();
        });
    }

    private function reassignStaff(Appointment $appointment, ?int $newStaffMemberId): void
    {
        if ($newStaffMemberId !== null) {
            StaffMember::query()->whereKey($newStaffMemberId)->lockForUpdate()->first();

            if (! $this->staff->canPerform($newStaffMemberId, $appointment->service_id)) {
                throw ValidationException::withMessages([
                    'staff_member_id' => 'This staff member is not eligible to perform the selected service.',
                ]);
            }
        }

        if (! $this->availability->isAvailable(
            $appointment->service_id,
            $newStaffMemberId,
            $appointment->starts_at,
            excludingAppointmentId: $appointment->getKey(),
        )) {
            throw ValidationException::withMessages([
                'staff_member_id' => 'That staff member is not available for this appointment\'s time.',
            ]);
        }
    }
}
