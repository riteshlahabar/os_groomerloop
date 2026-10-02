<?php

namespace Modules\SuperAdmin\Http\Controllers\Api\V1;

use Modules\SuperAdmin\Actions\SuspendTenant;
use Modules\SuperAdmin\Http\Requests\SuspendTenantRequest;
use Modules\SuperAdmin\Http\Resources\PlatformTenantDetailResource;
use Modules\Tenancy\Models\Tenant;

final class TenantSuspensionController
{
    public function store(SuspendTenantRequest $request, Tenant $tenant, SuspendTenant $suspend): PlatformTenantDetailResource
    {
        return PlatformTenantDetailResource::make($suspend->execute($tenant, $request->reason()));
    }
}
