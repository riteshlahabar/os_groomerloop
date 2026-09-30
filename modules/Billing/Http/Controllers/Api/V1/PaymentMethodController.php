<?php

namespace Modules\Billing\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Billing\Actions\ForgetPaymentMethod;
use Modules\Billing\Actions\StorePaymentMethod;
use Modules\Billing\Http\Requests\StorePaymentMethodRequest;
use Modules\Billing\Http\Resources\PaymentMethodResource;
use Modules\Billing\Models\PaymentMethod;
use Modules\Tenancy\Support\TenantContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Payment method management (spec §24).
 */
final class PaymentMethodController
{
    public function index(): AnonymousResourceCollection
    {
        return PaymentMethodResource::collection(
            PaymentMethod::query()->orderByDesc('is_default')->orderBy('id')->paginate(25)
        );
    }

    public function store(
        StorePaymentMethodRequest $request,
        StorePaymentMethod $store,
        TenantContext $tenants,
    ): JsonResponse {
        $method = $store->execute(
            tenant: $tenants->tenant(),
            token: $request->token(),
            makeDefault: $request->makeDefault(),
        );

        return PaymentMethodResource::make($method)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(PaymentMethod $paymentMethod, ForgetPaymentMethod $forget): JsonResponse
    {
        $forget->execute($paymentMethod);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
