<?php

namespace Modules\Billing\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Billing\Actions\StartSubscription;
use Modules\Billing\Http\Requests\StartSubscriptionRequest;
use Modules\Billing\Http\Resources\SubscriptionResource;
use Modules\Billing\Models\Subscription;
use Modules\Tenancy\Support\TenantContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * The business's current subscription (spec §24).
 *
 * Changing plan, cancelling and reactivating are each their own controller: they are
 * different use cases with different permissions and different audit trails, and folding
 * them in here as extra methods is how a controller grows past 200 lines.
 */
final class SubscriptionController
{
    public function show(TenantContext $tenants): JsonResponse
    {
        $subscription = Subscription::query()->current()->latest('id')->first();

        // Null rather than 404: "this business has not subscribed yet" is a normal state the
        // billing screen has to render, not a missing resource.
        return response()->json([
            'data' => $subscription === null
                ? null
                : SubscriptionResource::make($subscription)->resolve(),
        ]);
    }

    public function store(
        StartSubscriptionRequest $request,
        StartSubscription $start,
        TenantContext $tenants,
    ): JsonResponse {
        $subscription = $start->execute(
            tenant: $tenants->tenant(),
            planKey: $request->planKey(),
        );

        return SubscriptionResource::make($subscription)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
