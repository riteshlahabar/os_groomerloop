<?php

namespace Modules\Catalog\Http\Controllers\Api\V1;

use Modules\Catalog\Actions\SetServiceAvailability;
use Modules\Catalog\Http\Requests\SetServiceAvailabilityRequest;
use Modules\Catalog\Http\Resources\ServiceResource;
use Modules\Catalog\Models\Service;

/**
 * When a service may be booked (spec §10 "availability rules").
 *
 * Its own controller and endpoint, because it is a different use case from editing the service and
 * the whole set is replaced at once — a PUT that takes every window is honest about that, where a
 * field on the service edit form would imply the windows merge.
 *
 * What this does *not* do is decide availability. It records the service's own restriction; §11 owns
 * business hours and staff availability, and §12's booking engine combines all three server-side.
 */
final class ServiceAvailabilityController
{
    public function __invoke(
        SetServiceAvailabilityRequest $request,
        Service $service,
        SetServiceAvailability $set,
    ): ServiceResource {
        $service = $set->execute($service, $request->windows());

        return ServiceResource::make($service->load(['category', 'addOns', 'availabilityWindows']));
    }
}
