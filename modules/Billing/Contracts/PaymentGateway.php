<?php

namespace Modules\Billing\Contracts;

use Modules\Billing\Domain\ChargeResult;
use Modules\Billing\Domain\PaymentMethodDetails;
use Modules\Billing\Exceptions\GatewayFailure;

/**
 * Taking money and holding payment instruments (invariant #5, spec §30).
 *
 * Every driver — the fake included — passes PaymentGatewayContractTest, so swapping Stripe
 * for anything else is a binding change and touches no business logic. Spec §40 leaves the
 * gateway deliberately unchosen, which only works if nothing above this line knows which one
 * is in use.
 *
 * Card data never crosses this interface. The client tokenises against the gateway directly
 * and hands back a token, so the application is never in possession of a PAN (spec §28).
 */
interface PaymentGateway
{
    /**
     * Exchange a client-side token for a stored, reusable payment method.
     *
     * @param  string  $customerReference  the gateway's id for this business
     * @param  string  $token  single-use token produced by the gateway's client SDK
     *
     * @throws GatewayFailure when the gateway cannot be reached
     *                        or rejects the request outright
     */
    public function storePaymentMethod(string $customerReference, string $token): PaymentMethodDetails;

    /**
     * Forget a stored payment method. Idempotent: removing one that is already gone succeeds.
     */
    public function forgetPaymentMethod(string $customerReference, string $token): void;

    /**
     * Register a business with the gateway, returning its customer reference.
     */
    public function createCustomer(string $name, string $email): string;

    /**
     * Mark one stored payment method as the one `charge()` should use.
     *
     * `charge()` takes no payment-method token of its own — it bills "this customer's" card,
     * mirroring `PaymentMethod.is_default` (exactly one default per tenant, enforced by
     * `StorePaymentMethod`/`ForgetPaymentMethod`). This is what keeps a real gateway's own
     * notion of "default" from drifting out of sync with that local bookkeeping: both actions
     * call this whenever the local default changes. The fake tracks it only so its own
     * behaviour matches what a real driver does; it has nothing else to do with the value.
     */
    public function setDefaultPaymentMethod(string $customerReference, string $token): void;

    /**
     * Charge the customer's default payment method (see `setDefaultPaymentMethod()`).
     *
     * A decline comes back as an unsuccessful ChargeResult, not an exception — see the
     * reasoning on that class.
     */
    public function charge(string $customerReference, int $amountCents, string $currency, string $description): ChargeResult;
}
