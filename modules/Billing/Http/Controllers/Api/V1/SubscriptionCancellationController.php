<?php

namespace Modules\Billing\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\CancelSubscription;
use Modules\Billing\Http\Requests\CancelSubscriptionRequest;
use Modules\Billing\Http\Resources\SubscriptionResource;
use Modules\Billing\Models\Subscription;
use Modules\Tenancy\Support\TenantContext;

/**
 * Cancellation (spec §24).
 *
 * Its own controller because it is the one billing action with a consequence the customer
 * cannot undo by repeating it, and because the audit trail for "who cancelled this business"
 * deserves to be findable in one place.
 */
final class SubscriptionCancellationController
{
    public function __invoke(
        CancelSubscriptionRequest $request,
        CancelSubscription $cancel,
        TenantContext $tenants,
    ): JsonResponse {
        $subscription = Subscription::query()->current()->latest('id')->first();

        if ($subscription === null) {
            throw ValidationException::withMessages([
                'subscription' => 'This business has no active subscription.',
            ]);
        }

        $subscription = $cancel->execute(
            tenant: $tenants->tenant(),
            subscription: $subscription,
            immediately: $request->immediately(),
            reason: $request->reason(),
        );

        return SubscriptionResource::make($subscription)->response();
    }
}
