<?php

namespace Modules\Scheduling\Http\Controllers\Api\V1;

use Modules\Scheduling\Actions\RescheduleAppointment;
use Modules\Scheduling\Http\Requests\RescheduleAppointmentRequest;
use Modules\Scheduling\Http\Resources\AppointmentResource;
use Modules\Scheduling\Models\Appointment;

final class AppointmentRescheduleController
{
    public function __invoke(
        RescheduleAppointmentRequest $request,
        Appointment $appointment,
        RescheduleAppointment $reschedule,
    ): AppointmentResource {
        $appointment = $reschedule->execute($appointment, $request->start());

        $appointment->load(['addOns', 'statusHistory']);

        return AppointmentResource::make($appointment);
    }
}
