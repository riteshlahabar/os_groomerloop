<?php

namespace Modules\SuperAdmin\Http\Controllers\Api\V1;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\SuperAdmin\Http\Requests\ListPlatformTenantsRequest;
use Modules\SuperAdmin\Http\Resources\PlatformTenantDetailResource;
use Modules\SuperAdmin\Http\Resources\PlatformTenantSummaryResource;
use Modules\SuperAdmin\Services\PlatformTenantIndex;
use Modules\Tenancy\Models\Tenant;

/**
 * Spec §31's "Tenant search" and the account-level half of "Subscription/plan status" and
 * "Feature entitlements" — the list and the detail are the same use case at two depths, the
 * same split `SubscriptionController` draws between its own show and store.
 */
final class PlatformTenantController
{
    public function index(ListPlatformTenantsRequest $request, PlatformTenantIndex $tenants): AnonymousResourceCollection
    {
        return PlatformTenantSummaryResource::collection($tenants->paginate($request->filters()));
    }

    public function show(Tenant $tenant): PlatformTenantDetailResource
    {
        return PlatformTenantDetailResource::make($tenant);
    }
}
