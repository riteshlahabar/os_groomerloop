<?php

namespace Modules\CustomerPortal\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Crm\Contracts\CustomerDirectory;
use Modules\CustomerPortal\Actions\LoginCustomer;
use Modules\CustomerPortal\Http\Requests\LoginRequest;

final class LoginController
{
    public function __invoke(LoginRequest $request, LoginCustomer $login, CustomerDirectory $customers): JsonResponse
    {
        $customer = $login->execute(
            request: $request,
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
            remember: $request->boolean('remember'),
        );

        $customerId = (int) $customer->getAuthIdentifier();
        $contact = $customers->contactDetailsOf($customerId);

        return response()->json([
            'id' => $customerId,
            'name' => $contact?->fullName,
            'email' => $contact?->email,
            'phone' => $contact?->phone,
        ]);
    }
}
