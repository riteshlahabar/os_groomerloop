<?php

namespace Modules\Billing\Services\Gateways;

use Modules\Billing\Domain\GatewayEvent;
use Modules\Billing\Domain\GatewayEventType;
use Modules\Billing\Domain\PaymentMethodDetails;
use Modules\Billing\Exceptions\GatewayFailure;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Verifies a Stripe webhook and translates it into a provider-neutral `GatewayEvent`.
 *
 * This is the inbound twin of `StripeGateway`, and the only Stripe-shaped code on the webhook
 * path: everything downstream of `translate()` sees the neutral value object, so invariant #5
 * holds for traffic arriving as well as traffic sent. A second provider adds its own
 * translator and its own route, and `HandleGatewayEvent` is untouched.
 *
 * **Signature verification is not optional and is not a configuration choice.** This endpoint
 * is unauthenticated by necessity — Stripe has no session — so the signature is the *only*
 * thing separating a real event from anyone on the internet posting
 * "charge.succeeded" to mark their own invoice paid. An unverifiable request is refused, and a
 * missing webhook secret refuses every request rather than skipping the check.
 */
final class StripeWebhookTranslator
{
    public function __construct(private readonly string $webhookSecret) {}

    /**
     * @throws GatewayFailure when the secret is absent, or the payload/signature do not verify
     */
    public function translate(string $payload, ?string $signature): GatewayEvent
    {
        if ($this->webhookSecret === '') {
            throw GatewayFailure::rejected('stripe', 'No webhook signing secret is configured.');
        }

        if ($signature === null || $signature === '') {
            throw GatewayFailure::rejected('stripe', 'The request carried no signature header.');
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $this->webhookSecret);
        } catch (SignatureVerificationException $e) {
            throw GatewayFailure::rejected('stripe', 'The signature did not verify: '.$e->getMessage());
        } catch (UnexpectedValueException $e) {
            throw GatewayFailure::rejected('stripe', 'The payload was not valid JSON: '.$e->getMessage());
        }

        return $this->toGatewayEvent($event);
    }

    /**
     * Stripe fires both `payment_intent.succeeded` and `charge.succeeded` for one payment, so
     * two events can describe the same fact. They are both translated and both handled: the
     * invoice guard in `HandleGatewayEvent` is what makes the second one a no-op, since
     * idempotency by event id cannot help with two genuinely different events.
     */
    private function toGatewayEvent(Event $event): GatewayEvent
    {
        $object = $event->data?->object;
        $type = $event->type;

        $neutral = match ($type) {
            'payment_intent.succeeded', 'charge.succeeded' => GatewayEventType::ChargeSucceeded,
            'payment_intent.payment_failed', 'charge.failed' => GatewayEventType::ChargeFailed,
            'charge.refunded' => GatewayEventType::ChargeRefunded,
            'charge.dispute.created' => GatewayEventType::ChargeDisputed,

            // `automatically_updated` is the card-account-updater case: the issuer reissued the
            // card and Stripe followed it. `updated` covers an expiry or billing-detail edit.
            'payment_method.automatically_updated', 'payment_method.updated' => GatewayEventType::PaymentMethodUpdated,

            'payment_method.detached' => GatewayEventType::PaymentMethodDetached,

            // Everything else — `customer.source.expiring` (legacy sources, which this product
            // does not use), invoice and subscription events from Stripe's own billing
            // product, which this application does not drive. Recorded, not acted on.
            default => null,
        };

        return new GatewayEvent(
            id: $event->id,
            providerType: (string) $type,
            type: $neutral,
            customerReference: $this->stringOf($object?->customer ?? null),
            chargeReference: $this->chargeReferenceOf($object, $neutral),
            paymentMethodToken: $this->paymentMethodTokenOf($object, $neutral),
            card: $this->cardOf($object, $neutral),
            failureCode: $this->failureCodeOf($object, $neutral),
            failureMessage: $this->failureMessageOf($object, $neutral),
        );
    }

    /**
     * `ChargeSubscription` stores the PaymentIntent id in `invoices.gateway_charge_id`
     * (`StripeGateway::charge()` returns `$intent->id`), so that is the reference to match on.
     * A `charge.*` event names its intent in `payment_intent`; its own `id` is a `ch_...`
     * this application never stores, and is used only as a last resort so the row is traceable.
     */
    private function chargeReferenceOf(mixed $object, ?GatewayEventType $type): ?string
    {
        if (! in_array($type, [
            GatewayEventType::ChargeSucceeded,
            GatewayEventType::ChargeFailed,
            GatewayEventType::ChargeRefunded,
            GatewayEventType::ChargeDisputed,
        ], true)) {
            return null;
        }

        return $this->stringOf($object?->payment_intent ?? null)
            ?? $this->stringOf($object?->charge ?? null)
            ?? $this->stringOf($object?->id ?? null);
    }

    private function paymentMethodTokenOf(mixed $object, ?GatewayEventType $type): ?string
    {
        if (! in_array($type, [
            GatewayEventType::PaymentMethodUpdated,
            GatewayEventType::PaymentMethodDetached,
        ], true)) {
            return null;
        }

        return $this->stringOf($object?->id ?? null);
    }

    private function cardOf(mixed $object, ?GatewayEventType $type): ?PaymentMethodDetails
    {
        if ($type !== GatewayEventType::PaymentMethodUpdated) {
            return null;
        }

        $card = $object?->card ?? null;
        $token = $this->stringOf($object?->id ?? null);

        if ($card === null || $token === null) {
            return null;
        }

        return new PaymentMethodDetails(
            token: $token,
            brand: StripeGateway::brandLabel((string) ($card->brand ?? 'card')),
            lastFour: (string) ($card->last4 ?? '0000'),
            expiryMonth: (int) ($card->exp_month ?? 1),
            expiryYear: (int) ($card->exp_year ?? (int) date('Y')),
        );
    }

    private function failureCodeOf(mixed $object, ?GatewayEventType $type): ?string
    {
        if ($type !== GatewayEventType::ChargeFailed) {
            return null;
        }

        $error = $object?->last_payment_error ?? null;

        return $this->stringOf($error?->decline_code ?? null)
            ?? $this->stringOf($error?->code ?? null)
            ?? $this->stringOf($object?->failure_code ?? null)
            ?? 'card_declined';
    }

    private function failureMessageOf(mixed $object, ?GatewayEventType $type): ?string
    {
        if ($type !== GatewayEventType::ChargeFailed) {
            return null;
        }

        $error = $object?->last_payment_error ?? null;

        $message = $this->stringOf($error?->message ?? null)
            ?? $this->stringOf($object?->failure_message ?? null)
            ?? 'The charge was declined.';

        // invoices.failure_message is varchar(255); a provider message is not length-bound.
        return mb_substr($message, 0, 255);
    }

    /**
     * Stripe expands some fields into objects and leaves others as id strings depending on
     * the event, so every read goes through this rather than assuming one shape.
     */
    private function stringOf(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_object($value) && isset($value->id) && is_string($value->id) && $value->id !== '') {
            return $value->id;
        }

        return null;
    }
}
