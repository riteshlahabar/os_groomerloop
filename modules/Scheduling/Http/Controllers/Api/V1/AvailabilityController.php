<?php

namespace Modules\Scheduling\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Scheduling\Http\Requests\AvailabilityQueryRequest;
use Modules\Scheduling\Services\AvailabilityEngine;

/**
 * "Can this appointment happen" (spec §11, invariant #2), asked speculatively — the same
 * composed check the booking action re-asks for real, inside its lock, immediately before
 * writing. A yes here is never a hold on the slot; it can still lose a race to another request
 * between this answer and the next `POST /appointments`.
 */
final class AvailabilityController
{
    public function __invoke(AvailabilityQueryRequest $request, AvailabilityEngine $availability): JsonResponse
    {
        $available = $availability->isAvailable(
            $request->serviceId(),
            $request->staffMemberId(),
            $request->start(),
        );

        return response()->json(['data' => ['available' => $available]]);
    }
}
