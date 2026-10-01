<?php

namespace Modules\Scheduling\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Scheduling\Actions\CancelWaitlistEntry;
use Modules\Scheduling\Actions\JoinWaitlist;
use Modules\Scheduling\Http\Requests\JoinWaitlistRequest;
use Modules\Scheduling\Http\Requests\ListWaitlistRequest;
use Modules\Scheduling\Http\Resources\WaitlistEntryResource;
use Modules\Scheduling\Models\WaitlistEntry;
use Modules\Scheduling\Services\WaitlistIndex;
use Symfony\Component\HttpFoundation\Response;

/**
 * The §11 waitlist. Converting an entry into an appointment is its own controller — the same
 * split `AppointmentRescheduleController` uses for a distinct use case alongside plain CRUD.
 */
final class WaitlistController
{
    public function index(ListWaitlistRequest $request, WaitlistIndex $index): AnonymousResourceCollection
    {
        return WaitlistEntryResource::collection($index->paginate($request->filters()));
    }

    public function store(JoinWaitlistRequest $request, JoinWaitlist $join): JsonResponse
    {
        $entry = $join->execute($request->waitlistAttributes());

        return WaitlistEntryResource::make($entry)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Route model binding, resolved after ResolveTenant (D-014). Another business's waitlist
     * entry is simply not found — 404, not 403.
     */
    public function destroy(WaitlistEntry $waitlistEntry, CancelWaitlistEntry $cancel): WaitlistEntryResource
    {
        return WaitlistEntryResource::make($cancel->execute($waitlistEntry));
    }
}
