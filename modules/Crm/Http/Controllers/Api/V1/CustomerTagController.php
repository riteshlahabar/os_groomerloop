<?php

namespace Modules\Crm\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Crm\Actions\UpsertCustomerTag;
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

    /**
     * 200 rather than 201 when the tag already existed, so a client that asks twice can tell
     * that nothing new was made.
     */
    public function store(StoreCustomerTagRequest $request, UpsertCustomerTag $upsert): JsonResponse
    {
        $tag = $upsert->execute(
            $request->string('name')->toString(),
            $request->input('colour'),
        );

        // Unreachable over HTTP: StoreCustomerTagRequest already refuses a name with nothing
        // to slug. Handled rather than assumed, because the action is also called from
        // SyncCustomerTags where a nameless tag is simply skipped.
        abort_if($tag === null, Response::HTTP_UNPROCESSABLE_ENTITY);

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
