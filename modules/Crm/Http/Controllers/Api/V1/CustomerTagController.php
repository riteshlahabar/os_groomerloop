<?php

namespace Modules\Crm\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Crm\Http\Requests\StoreCustomerTagRequest;
use Modules\Crm\Http\Resources\CustomerTagResource;
use Modules\Crm\Models\CustomerTag;
use Symfony\Component\HttpFoundation\Response;

/**
 * The business's tag vocabulary (spec §8).
 *
 * Tags are also created on the fly when applied to a customer, so this exists for managing
 * the list rather than as the only way in — a receptionist types a tag while looking at a
 * customer, they do not visit a settings screen first.
 */
final class CustomerTagController
{
    public function index(): AnonymousResourceCollection
    {
        return CustomerTagResource::collection(
            CustomerTag::query()->orderBy('name')->paginate(100)
        );
    }

    public function store(StoreCustomerTagRequest $request): JsonResponse
    {
        // firstOrCreate on the slug, which the model derives from the name: asking twice for
        // the same tag returns the same tag rather than colliding on the unique index.
        $tag = CustomerTag::query()->firstOrCreate(
            ['slug' => \Illuminate\Support\Str::slug($request->string('name')->toString())],
            $request->safe()->only(['name', 'colour']),
        );

        return CustomerTagResource::make($tag)
            ->response()
            ->setStatusCode($tag->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    /**
     * Removing a tag detaches it from every customer; it does not touch the customers.
     * The pivot's cascade does that work.
     */
    public function destroy(CustomerTag $customerTag, AuditRecorder $audit): JsonResponse
    {
        $audit->record('customer_tag.deleted', $customerTag, ['name' => $customerTag->name]);

        $customerTag->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
