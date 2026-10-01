<?php

namespace Modules\Scheduling\Http\Controllers\Api\V1;

use Illuminate\Support\Facades\Gate;
use Modules\Scheduling\Actions\UpdateAppointmentStatus;
use Modules\Scheduling\Http\Requests\UpdateAppointmentStatusRequest;
use Modules\Scheduling\Http\Resources\AppointmentResource;
use Modules\Scheduling\Models\Appointment;

/**
 * Move an appointment through its state machine (spec §11).
 *
 * Gated by `appointments.update_status` at the route level, but that alone would let a Groomer —
 * who holds it without `appointments.manage` — cancel or no-show a booking through this endpoint.
 * `AppointmentPolicy::updateStatus()` closes that gap per target status.
 */
final class AppointmentStatusController
{
    public function __invoke(
        UpdateAppointmentStatusRequest $request,
        Appointment $appointment,
        UpdateAppointmentStatus $updateStatus,
    ): AppointmentResource {
        $status = $request->status();

        Gate::authorize('updateStatus', [$appointment, $status]);

        $appointment = $updateStatus->execute($appointment, $status, $request->note());

        $appointment->load(['addOns', 'statusHistory']);

        return AppointmentResource::make($appointment);
    }
}
