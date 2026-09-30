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
     * Charge a stored payment method.
     *
     * A decline comes back as an unsuccessful ChargeResult, not an exception — see the
     * reasoning on that class.
     */
    public function charge(string $customerReference, int $amountCents, string $currency, string $description): ChargeResult;
}
