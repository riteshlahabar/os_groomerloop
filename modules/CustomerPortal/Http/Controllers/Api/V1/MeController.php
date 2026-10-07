<?php

namespace Modules\CustomerPortal\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Crm\Contracts\CustomerDirectory;

final class MeController
{
    public function __invoke(Request $request, CustomerDirectory $customers): JsonResponse
    {
        $customerId = (int) $request->user('customer')->getAuthIdentifier();
        $contact = $customers->contactDetailsOf($customerId);

        return response()->json([
            'id' => $customerId,
            'name' => $contact?->fullName,
            'email' => $contact?->email,
            'phone' => $contact?->phone,
        ]);
    }
}
