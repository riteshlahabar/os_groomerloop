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
        $appointment = $submit->execute($request->bookingAttributes());

        return PublicAppointmentResource::make($appointment)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
