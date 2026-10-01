<?php

namespace Modules\Scheduling\Http\Controllers\Api\V1;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Scheduling\Actions\SetBusinessHours;
use Modules\Scheduling\Http\Requests\SetBusinessHoursRequest;
use Modules\Scheduling\Http\Resources\BusinessHourResource;
use Modules\Scheduling\Models\BusinessHour;

/**
 * The week the business is open (spec §11, §7 step 1).
 *
 * A tenant-wide collection rather than a singleton resource like `BusinessProfile`: more than one
 * window can exist per day (closing for lunch), so there is no single row to upsert. The tenant
 * scope answers "whose hours" by itself — no {id} in the route.
 */
final class BusinessHoursController
{
    public function index(): AnonymousResourceCollection
    {
        return BusinessHourResource::collection(
            BusinessHour::query()->orderBy('day_of_week')->orderBy('starts_at')->get()
        );
    }

    public function update(SetBusinessHoursRequest $request, SetBusinessHours $setHours): AnonymousResourceCollection
    {
        $setHours->execute($request->windows());

        return $this->index();
    }
}
