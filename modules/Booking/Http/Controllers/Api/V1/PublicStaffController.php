<?php

namespace Modules\Booking\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Booking\Http\Resources\PublicStaffResource;
use Modules\Team\Contracts\StaffDirectory;

final class PublicStaffController
{
    public function index(Request $request, StaffDirectory $staff): AnonymousResourceCollection
    {
        $serviceId = $request->filled('service_id') ? (int) $request->input('service_id') : null;

        return PublicStaffResource::collection($staff->bookableOnline($serviceId));
    }
}
