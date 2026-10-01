<?php

namespace Modules\Booking\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Booking\Http\Requests\PublicAvailabilityRequest;
use Modules\Scheduling\Contracts\AppointmentScheduler;

/**
 * "Can this appointment happen", asked by a stranger — the exact same composed check
 * `AvailabilityEngine` answers for the authenticated calendar (`D-023`), reached here only
 * through Scheduling's contract, never its internal service.
 */
final class PublicAvailabilityController
{
    public function __invoke(PublicAvailabilityRequest $request, AppointmentScheduler $scheduler): JsonResponse
    {
        $available = $scheduler->isSlotAvailable(
            $request->serviceId(),
            $request->staffMemberId(),
            $request->start(),
        );

        return response()->json(['data' => ['available' => $available]]);
    }
}
