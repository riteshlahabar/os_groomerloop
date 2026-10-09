<?php

namespace Modules\Booking\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Booking\Actions\SubmitPublicBooking;
use Modules\Booking\Http\Requests\PublicBookingRequest;
use Modules\Booking\Http\Resources\PublicAppointmentResource;
use Symfony\Component\HttpFoundation\Response;

final class PublicBookingController
{
    public function store(PublicBookingRequest $request, SubmitPublicBooking $submit): JsonResponse
    {
        // Who is booking comes from the request's own session, never from its body — the one
        // thing only the HTTP layer can answer, so it is read here and passed in rather than
        // reached for inside the action.
        $appointment = $submit->execute(
            $request->bookingAttributes(),
            $request->signedInCustomerId(),
        );

        return PublicAppointmentResource::make($appointment)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
