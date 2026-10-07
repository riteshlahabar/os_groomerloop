<?php

namespace Modules\CustomerPortal\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Modules\Crm\Contracts\CustomerDirectory;
use Modules\CustomerPortal\Actions\ClaimCustomerAccount;
use Modules\CustomerPortal\Http\Requests\ClaimAccountRequest;
use Modules\Tenancy\Support\TenantContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * The page behind a signed account-claim link (`AccountClaimLinks::urlFor()`) — the Customer
 * Portal's own instance of the one deliberate server-rendered, state-changing exception to
 * `D-007` that `Booking\Http\Controllers\PublicCancellationController` already established
 * (there is no logged-in customer and no SPA to hand a token to yet, so the signature itself is
 * the authentication, verified by the `signed` route middleware before this controller ever
 * runs — see `Modules\CustomerPortal\Routes\web.php`).
 */
final class AccountClaimController
{
    public function __construct(
        private readonly CustomerDirectory $customers,
        private readonly TenantContext $tenantContext,
    ) {}

    public function show(string $tenant, string $customer): View
    {
        $customerId = (int) $customer;

        abort_unless($this->customers->exists($customerId), Response::HTTP_NOT_FOUND);

        return view('customer-portal.claim-account', [
            'tenant' => $this->tenantContext->tenant(),
            'customerName' => $this->customers->nameOf($customerId),
            'claimed' => false,
        ]);
    }

    public function store(ClaimAccountRequest $request, ClaimCustomerAccount $claim, string $tenant, string $customer): View
    {
        $customerId = (int) $customer;

        abort_unless($this->customers->exists($customerId), Response::HTTP_NOT_FOUND);

        $claim->execute($customerId, $request->string('password')->toString());

        // Signs them straight into the portal session rather than making them log in again
        // immediately after choosing a password.
        Auth::guard('customer')->loginUsingId($customerId);

        return view('customer-portal.claim-account', [
            'tenant' => $this->tenantContext->tenant(),
            'customerName' => $this->customers->nameOf($customerId),
            'claimed' => true,
        ]);
    }
}
