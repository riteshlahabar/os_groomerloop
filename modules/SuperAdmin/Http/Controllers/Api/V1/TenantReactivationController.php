<?php

namespace Modules\SuperAdmin\Http\Controllers\Api\V1;

use Modules\SuperAdmin\Actions\ReactivateTenant;
use Modules\SuperAdmin\Http\Resources\PlatformTenantDetailResource;
use Modules\Tenancy\Models\Tenant;

final class TenantReactivationController
{
    public function store(Tenant $tenant, ReactivateTenant $reactivate): PlatformTenantDetailResource
    {
        return PlatformTenantDetailResource::make($reactivate->execute($tenant));
    }
}
