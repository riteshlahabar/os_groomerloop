<?php

namespace Modules\Catalog\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Catalog\Actions\UpsertServiceCategory;
use Modules\Catalog\Http\Requests\StoreServiceCategoryRequest;
use Modules\Catalog\Http\Resources\ServiceCategoryResource;
use Modules\Catalog\Models\ServiceCategory;
use Symfony\Component\HttpFoundation\Response;

/**
 * How a business groups its menu (spec §10).
 */
final class ServiceCategoryController
{
    public function index(): AnonymousResourceCollection
    {
        return ServiceCategoryResource::collection(
            ServiceCategory::query()
                // So a settings screen can warn before retiring a category that still has a menu
                // behind it, without loading that menu.
                ->withCount('services')
                ->orderBy('position')
                ->orderBy('name')
                ->paginate(100)
        );
    }

    /**
     * 200 rather than 201 when the category already existed, so a client asking twice can tell that
     * nothing new was made.
     */
    public function store(StoreServiceCategoryRequest $request, UpsertServiceCategory $upsert): JsonResponse
    {
        $category = $upsert->execute(
            $request->string('name')->toString(),
            (int) $request->input('position', 0),
        );

        // Unreachable over HTTP — the request already refuses a name with nothing to slug. Handled
        // rather than assumed, because the action is also called from elsewhere.
        abort_if($category === null, Response::HTTP_UNPROCESSABLE_ENTITY);

        return ServiceCategoryResource::make($category)
            ->response()
            ->setStatusCode($category->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    /**
     * Deleting a category leaves its services alone and uncategorised — the foreign key is
     * nullOnDelete. A business reorganising its menu must not be able to delete its own price list
     * as a side effect (invariant #4).
     */
    public function destroy(ServiceCategory $serviceCategory, AuditRecorder $audit): JsonResponse
    {
        $audit->record('service_category.deleted', $serviceCategory, [
            'name' => $serviceCategory->name,
            'services_left_uncategorised' => $serviceCategory->services()->count(),
        ]);

        $serviceCategory->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
