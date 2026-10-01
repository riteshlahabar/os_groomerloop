<?php

namespace Modules\Team\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Team\Actions\ScheduleTimeOff;
use Modules\Team\Http\Requests\ScheduleTimeOffRequest;
use Modules\Team\Http\Resources\StaffTimeOffResource;
use Modules\Team\Models\StaffMember;
use Modules\Team\Models\StaffTimeOff;
use Symfony\Component\HttpFoundation\Response;

final class StaffTimeOffController
{
    public function store(
        ScheduleTimeOffRequest $request,
        StaffMember $staffMember,
        ScheduleTimeOff $schedule,
    ): JsonResponse {
        $absence = $schedule->execute($staffMember, $request->timeOffAttributes());

        return StaffTimeOffResource::make($absence)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Both ids are route-bound, but the tenant scope alone does not prove they belong together —
     * two staff members in the same business are the same tenant. A time-off id that belongs to a
     * different staff member than the one in the URL is treated as not found, the same
     * within-tenant ownership check Pets' PetDirectory::belongsTo() exists for.
     */
    public function destroy(StaffMember $staffMember, StaffTimeOff $timeOff, ScheduleTimeOff $schedule): JsonResponse
    {
        abort_if($timeOff->staff_member_id !== $staffMember->getKey(), Response::HTTP_NOT_FOUND);

        $schedule->cancel($staffMember, $timeOff);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
