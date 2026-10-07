<?php

namespace Modules\CustomerPortal\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\CustomerPortal\Http\Resources\AppointmentResource;
use Modules\Scheduling\Contracts\AppointmentScheduler;

final class AppointmentController
{
    public function __invoke(Request $request, AppointmentScheduler $scheduler): AnonymousResourceCollection
    {
        $customerId = (int) $request->user('customer')->getAuthIdentifier();

        return AppointmentResource::collection($scheduler->appointmentsForCustomer($customerId));
    }
}
