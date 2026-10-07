<?php

namespace Modules\CustomerPortal\Contracts;

/**
 * The only way another module (Notifications) learns a customer's account-claim link — never by
 * constructing `route('customer-portal.claim.show', ...)` itself, the same seam
 * `Booking\Contracts\CancellationLinks` already established for the identical reason.
 */
interface AccountClaimLinks
{
    /**
     * A durable, signed URL a customer can open with no prior login to set their Customer
     * Portal password. Not time-limited by the signature itself: unlike a cancellation link,
     * there is no appointment state to make an old one stop mattering, so a fresh request simply
     * issues a new link and the account's `password_set_at` is what actually changes, not the
     * link's validity.
     */
    public function urlFor(int $customerId): string;
}
