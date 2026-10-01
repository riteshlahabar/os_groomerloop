<?php

namespace Modules\Team\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Team\Actions\CreateStaffMember;
use Modules\Team\Actions\DeactivateStaffMember;
use Modules\Team\Actions\SyncStaffServices;
use Modules\Team\Actions\UpdateStaffMember;
use Modules\Team\Http\Requests\ListStaffRequest;
use Modules\Team\Http\Requests\StoreStaffMemberRequest;
use Modules\Team\Http\Requests\UpdateStaffMemberRequest;
use Modules\Team\Http\Resources\StaffMemberResource;
use Modules\Team\Models\StaffMember;
use Modules\Team\Services\StaffIndex;
use Symfony\Component\HttpFoundation\Response;

/**
 * The team roster (spec §23).
 *
 * Working hours, time off and reactivation are their own controllers — different use cases,
 * same split Catalog uses for service availability.
 */
final class StaffMemberController
{
    public function index(ListStaffRequest $request, StaffIndex $index): AnonymousResourceCollection
    {
        return StaffMemberResource::collection($index->paginate($request->filters()));
    }

    public function store(
        StoreStaffMemberRequest $request,
        CreateStaffMember $create,
        SyncStaffServices $services,
    ): JsonResponse {
        $staff = $create->execute($request->staffAttributes(), $request->serviceIds(), $request->userId());

        return $this->withServiceIds($staff, $services)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Route model binding, resolved after ResolveTenant thanks to the global middleware priority in
     * bootstrap/app.php (D-014). Another business's staff member is simply not found — 404, not 403.
     */
    public function show(StaffMember $staffMember, SyncStaffServices $services): StaffMemberResource
    {
        $staffMember->load(['workingHours', 'timeOff', 'user']);

        return $this->withServiceIds($staffMember, $services);
    }

    public function update(
        UpdateStaffMemberRequest $request,
        StaffMember $staffMember,
        UpdateStaffMember $update,
        SyncStaffServices $services,
    ): StaffMemberResource {
        $staffMember = $update->execute($staffMember, $request->staffAttributes(), $request->serviceIds());

        return $this->withServiceIds($staffMember, $services);
    }

    /**
     * Deactivates rather than deletes (invariant #4): every appointment ever booked references who did
     * the work, and §11 history has to keep resolving their name.
     */
    public function destroy(StaffMember $staffMember, DeactivateStaffMember $deactivate): JsonResponse
    {
        $deactivate->execute($staffMember);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    private function withServiceIds(StaffMember $staff, SyncStaffServices $services): StaffMemberResource
    {
        $staff->setAttribute('service_ids', $services->current($staff));

        return StaffMemberResource::make($staff);
    }
}
