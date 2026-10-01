<?php

namespace Modules\Catalog\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Catalog\Actions\CreateService;
use Modules\Catalog\Actions\DeactivateService;
use Modules\Catalog\Actions\UpdateService;
use Modules\Catalog\Http\Requests\ListServicesRequest;
use Modules\Catalog\Http\Requests\StoreServiceRequest;
use Modules\Catalog\Http\Requests\UpdateServiceRequest;
use Modules\Catalog\Http\Resources\ServiceResource;
use Modules\Catalog\Models\Service;
use Modules\Catalog\Services\ServiceIndex;
use Symfony\Component\HttpFoundation\Response;

/**
 * The service menu (spec §10).
 *
 * Categories and availability rules are their own controllers — different use cases, and it keeps
 * this one about the thing a salon edits on the services screen.
 */
final class ServiceController
{
    public function index(ListServicesRequest $request, ServiceIndex $index): AnonymousResourceCollection
    {
        return ServiceResource::collection($index->paginate($request->filters()));
    }

    public function store(StoreServiceRequest $request, CreateService $create): JsonResponse
    {
        $service = $create->execute($request->serviceAttributes(), $request->addOnIds());

        return ServiceResource::make($service->load(['category', 'addOns']))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Route model binding, resolved after ResolveTenant thanks to the global middleware priority in
     * bootstrap/app.php (D-014). Another business's service is simply not found — 404, not 403.
     */
    public function show(Service $service): ServiceResource
    {
        return ServiceResource::make(
            $service->load(['category', 'addOns', 'availabilityWindows'])
        );
    }

    public function update(
        UpdateServiceRequest $request,
        Service $service,
        UpdateService $update,
    ): ServiceResource {
        $service = $update->execute($service, $request->serviceAttributes(), $request->addOnIds());

        return ServiceResource::make($service->load(['category', 'addOns']));
    }

    /**
     * Deactivates rather than deletes (invariant #4): every appointment ever booked references a
     * service, and §11 history has to keep resolving its name and duration.
     */
    public function destroy(Service $service, DeactivateService $deactivate): JsonResponse
    {
        $deactivate->execute($service);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
