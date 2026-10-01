<?php

namespace Modules\Team\Http\Controllers\Api\V1;

use Modules\Team\Actions\SetWorkingHours;
use Modules\Team\Http\Requests\SetWorkingHoursRequest;
use Modules\Team\Http\Resources\StaffMemberResource;
use Modules\Team\Models\StaffMember;

final class StaffWorkingHoursController
{
    public function __invoke(
        SetWorkingHoursRequest $request,
        StaffMember $staffMember,
        SetWorkingHours $set,
    ): StaffMemberResource {
        $staffMember = $set->execute($staffMember, $request->shifts());

        return StaffMemberResource::make($staffMember->load('workingHours'));
    }
}
