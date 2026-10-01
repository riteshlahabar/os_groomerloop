<?php

namespace Modules\Team\Http\Controllers\Api\V1;

use Modules\Team\Actions\DeactivateStaffMember;
use Modules\Team\Http\Resources\StaffMemberResource;
use Modules\Team\Models\StaffMember;

final class StaffReactivationController
{
    public function __invoke(StaffMember $staffMember, DeactivateStaffMember $reactivate): StaffMemberResource
    {
        return StaffMemberResource::make($reactivate->reactivate($staffMember));
    }
}
