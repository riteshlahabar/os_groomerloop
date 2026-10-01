<?php

namespace Modules\Scheduling\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Scheduling\Actions\ConvertWaitlistEntryToAppointment;
use Modules\Scheduling\Http\Requests\ConvertWaitlistEntryRequest;
use Modules\Scheduling\Http\Resources\AppointmentResource;
use Modules\Scheduling\Models\WaitlistEntry;
use Symfony\Component\HttpFoundation\Response;

final class WaitlistConversionController
{
    public function __invoke(
        ConvertWaitlistEntryRequest $request,
        WaitlistEntry $waitlistEntry,
        ConvertWaitlistEntryToAppointment $convert,
    ): JsonResponse {
        $appointment = $convert->execute($waitlistEntry, $request->start(), $request->staffMemberId());
        $appointment->load(['addOns', 'statusHistory']);

        return AppointmentResource::make($appointment)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
