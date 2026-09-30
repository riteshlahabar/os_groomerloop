<?php

namespace Modules\Billing\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\ReactivateSubscription;
use Modules\Billing\Http\Resources\SubscriptionResource;
use Modules\Billing\Models\Subscription;
use Modules\Tenancy\Support\TenantContext;

/**
 * Reactivation (spec §24).
 *
 * Looks at the most recent subscription regardless of status — unlike every other billing
 * endpoint, this one is specifically for the cancelled case.
 */
final class SubscriptionReactivationController
{
    public function __invoke(
        ReactivateSubscription $reactivate,
        TenantContext $tenants,
    ): JsonResponse {
        $subscription = Subscription::query()->latest('id')->first();

        if ($subscription === null) {
            throw ValidationException::withMessages([
                'subscription' => 'This business has never had a subscription.',
            ]);
        }

        $subscription = $reactivate->execute(
            tenant: $tenants->tenant(),
            subscription: $subscription,
        );

        return SubscriptionResource::make($subscription)->response();
    }
}
