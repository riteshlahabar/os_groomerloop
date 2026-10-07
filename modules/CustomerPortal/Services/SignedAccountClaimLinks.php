<?php

namespace Modules\CustomerPortal\Services;

use Illuminate\Support\Facades\URL;
use Modules\CustomerPortal\Contracts\AccountClaimLinks;
use Modules\Tenancy\Support\TenantContext;
use RuntimeException;

/**
 * Signs the URL with the app's own key (Laravel's `signed` route middleware), the same mechanism
 * `Booking\Services\SignedCancellationLinks` uses and for the same reason: nothing to migrate
 * onto `customers` beyond the columns Phase 1 already added, and nothing to clean up afterward.
 */
final class SignedAccountClaimLinks implements AccountClaimLinks
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function urlFor(int $customerId): string
    {
        $tenant = $this->tenant->tenant();

        if ($tenant === null) {
            // Every caller of this class runs inside a request already carrying a resolved
            // tenant (the portal's own login page, or Notifications dispatching on its behalf).
            // Reaching this means something is being called outside that context entirely.
            throw new RuntimeException('Cannot build an account-claim link with no tenant in context.');
        }

        return URL::signedRoute('customer-portal.claim.show', [
            'tenant' => $tenant->getKey(),
            'customer' => $customerId,
        ]);
    }
}
