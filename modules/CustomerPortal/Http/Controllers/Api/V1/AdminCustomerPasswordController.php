<?php

namespace Modules\CustomerPortal\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Crm\Contracts\CustomerDirectory;
use Modules\CustomerPortal\Actions\AdminResetCustomerPassword;
use Modules\CustomerPortal\Http\Requests\AdminResetCustomerPasswordRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/admin/customers`' own "update password" control (D-043) — staff setting or replacing a
 * customer's Customer Portal password directly, rather than only through the customer's own
 * signed claim-link email.
 */
final class AdminCustomerPasswordController
{
    public function __invoke(
        AdminResetCustomerPasswordRequest $request,
        AdminResetCustomerPassword $reset,
        CustomerDirectory $customers,
        string $customer,
    ): JsonResponse {
        $customerId = (int) $customer;

        abort_unless($customers->exists($customerId), Response::HTTP_NOT_FOUND);

        $reset->execute($customerId, $request->string('password')->toString());

        return response()->json(['message' => 'Password updated.']);
    }
}
