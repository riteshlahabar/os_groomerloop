<?php

namespace Modules\Billing\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\ChangeSubscriptionPlan;
use Modules\Billing\Http\Requests\ChangePlanRequest;
use Modules\Billing\Http\Resources\SubscriptionResource;
use Modules\Billing\Models\Subscription;
use Modules\Tenancy\Support\TenantContext;

/**
 * Upgrade and downgrade (spec §24).
 */
final class SubscriptionPlanController
{
    public function __invoke(
        ChangePlanRequest $request,
        ChangeSubscriptionPlan $change,
        TenantContext $tenants,
    ): JsonResponse {
        $subscription = Subscription::query()->current()->latest('id')->first();

        if ($subscription === null) {
            throw ValidationException::withMessages([
                'plan' => 'This business has no subscription to change. Start one first.',
            ]);
        }

        $subscription = $change->execute(
            tenant: $tenants->tenant(),
            subscription: $subscription,
            planKey: $request->planKey(),
        );

        return SubscriptionResource::make($subscription)->response();
    }
}
