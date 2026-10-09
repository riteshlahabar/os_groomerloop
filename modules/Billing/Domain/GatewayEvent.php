<?php

namespace Modules\Billing\Domain;

/**
 * One inbound gateway notification, already translated out of its provider's own shape.
 *
 * Everything a driver's webhook payload contains that this product can act on, and nothing
 * else — no raw payload, so a handler cannot quietly start depending on a Stripe-shaped field
 * and defeat invariant #5. Carries no card number for the same reason
 * `PaymentMethodDetails` cannot: there is no field that could hold one (§28).
 *
 * `id` is the provider's own event id. It is the idempotency key: gateways retry, and a
 * replayed failure that incremented the dunning counter a second time could push a paying
 * business into its grace period for nothing.
 */
final readonly class GatewayEvent
{
    public function __construct(
        /** The provider's event id, unique per event and stable across its retries. */
        public string $id,

        /** The provider's own event name, kept for the receipt log only — never branched on. */
        public string $providerType,

        /**
         * Null for an event this product has no case for. Expressible on purpose: an
         * unhandled event still has to be recorded and acknowledged, or the gateway retries
         * it for days, so "understood, nothing to do" must be sayable without inventing a
         * near-enough type for it.
         */
        public ?GatewayEventType $type,

        /** The gateway's customer id, where the event names one. */
        public ?string $customerReference = null,

        /**
         * The reference a charge was recorded under — matched against
         * `invoices.gateway_charge_id`, which is what both halves of `ChargeSubscription`
         * store, so a late webhook can find the invoice its charge belongs to.
         */
        public ?string $chargeReference = null,

        /** The stored payment method this event is about, matched against `payment_methods.token`. */
        public ?string $paymentMethodToken = null,

        /** Present on PaymentMethodUpdated: the new, safe remnant to store. */
        public ?PaymentMethodDetails $card = null,

        public ?string $failureCode = null,
        public ?string $failureMessage = null,
    ) {}
}
