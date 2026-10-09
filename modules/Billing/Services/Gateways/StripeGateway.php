<?php

namespace Modules\Billing\Services\Gateways;

use Modules\Billing\Contracts\PaymentGateway;
use Modules\Billing\Domain\ChargeResult;
use Modules\Billing\Domain\PaymentMethodDetails;
use Modules\Billing\Exceptions\GatewayFailure;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;

/**
 * The real driver for invariant #5/spec §30 — everything above `PaymentGateway` is unaware
 * this is Stripe rather than the fake. Card data never reaches this class or anything that
 * calls it: `$token` is always a Stripe PaymentMethod id (`pm_...`) produced by Stripe.js in
 * the customer's own browser, the same §28 guarantee `PaymentGateway`'s own docblock states.
 *
 * `off_session`/`confirm` on `charge()` is the standard Stripe pattern for billing a card that
 * is not present in the current request — exactly what a monthly subscription charge is.
 */
final class StripeGateway implements PaymentGateway
{
    private readonly StripeClient $client;

    public function __construct(string $secretKey)
    {
        $this->client = new StripeClient($secretKey);
    }

    public function createCustomer(string $name, string $email): string
    {
        try {
            $customer = $this->client->customers->create([
                'name' => $name,
                'email' => $email,
            ]);
        } catch (ApiErrorException $e) {
            throw GatewayFailure::unreachable('stripe', $e->getMessage());
        }

        return $customer->id;
    }

    public function storePaymentMethod(string $customerReference, string $token): PaymentMethodDetails
    {
        try {
            $paymentMethod = $this->client->paymentMethods->attach($token, [
                'customer' => $customerReference,
            ]);
        } catch (CardException|InvalidRequestException $e) {
            throw GatewayFailure::rejected('stripe', $e->getMessage());
        } catch (ApiErrorException $e) {
            throw GatewayFailure::unreachable('stripe', $e->getMessage());
        }

        $card = $paymentMethod->card;

        return new PaymentMethodDetails(
            token: $paymentMethod->id,
            brand: self::brandLabel((string) ($card->brand ?? 'card')),
            lastFour: (string) ($card->last4 ?? '0000'),
            expiryMonth: (int) ($card->exp_month ?? 1),
            expiryYear: (int) ($card->exp_year ?? (int) date('Y')),
        );
    }

    public function forgetPaymentMethod(string $customerReference, string $token): void
    {
        try {
            $this->client->paymentMethods->detach($token);
        } catch (InvalidRequestException) {
            // Already detached, or never existed — idempotent per the contract: webhooks
            // replay and retries happen.
        } catch (ApiErrorException $e) {
            throw GatewayFailure::unreachable('stripe', $e->getMessage());
        }
    }

    public function setDefaultPaymentMethod(string $customerReference, string $token): void
    {
        try {
            $this->client->customers->update($customerReference, [
                'invoice_settings' => ['default_payment_method' => $token],
            ]);
        } catch (ApiErrorException $e) {
            throw GatewayFailure::unreachable('stripe', $e->getMessage());
        }
    }

    public function charge(string $customerReference, int $amountCents, string $currency, string $description): ChargeResult
    {
        $paymentMethod = $this->defaultPaymentMethodFor($customerReference);

        if ($paymentMethod === null) {
            throw GatewayFailure::rejected('stripe', 'The customer has no payment method on file.');
        }

        try {
            $intent = $this->client->paymentIntents->create([
                'amount' => $amountCents,
                'currency' => strtolower($currency),
                'customer' => $customerReference,
                'payment_method' => $paymentMethod,
                'payment_method_types' => ['card'],
                'description' => $description,
                'off_session' => true,
                'confirm' => true,
            ]);
        } catch (CardException $e) {
            $error = $e->getError();

            return ChargeResult::declined(
                reference: (string) ($error->payment_intent->id ?? ('stripe_failed_'.uniqid())),
                amountCents: $amountCents,
                code: (string) ($error->decline_code ?? $error->code ?? 'card_declined'),
                message: $e->getMessage(),
            );
        } catch (InvalidRequestException $e) {
            throw GatewayFailure::rejected('stripe', $e->getMessage());
        } catch (ApiErrorException $e) {
            throw GatewayFailure::unreachable('stripe', $e->getMessage());
        }

        if ($intent->status !== 'succeeded') {
            // Requires 3DS or another action the customer isn't present to complete — not
            // something an off-session charge can recover from itself. The dunning cycle
            // treats this the same as any other non-collection.
            return ChargeResult::declined(
                reference: $intent->id,
                amountCents: $amountCents,
                code: 'requires_action',
                message: 'The charge requires additional authentication from the customer.',
            );
        }

        return ChargeResult::succeeded(reference: $intent->id, amountCents: $amountCents);
    }

    /**
     * `charge()` takes no payment-method token (see the contract's own docblock), so the
     * customer's own Stripe-side default — kept in sync by `setDefaultPaymentMethod()` — is
     * what decides which card is billed. Falls back to the most recently attached card only
     * for a customer whose default was never explicitly set (shouldn't happen once
     * `StorePaymentMethod` has run, but a customer created outside this flow may reach here).
     */
    private function defaultPaymentMethodFor(string $customerReference): ?string
    {
        try {
            $customer = $this->client->customers->retrieve($customerReference);
        } catch (ApiErrorException $e) {
            throw GatewayFailure::unreachable('stripe', $e->getMessage());
        }

        $default = $customer->invoice_settings->default_payment_method ?? null;

        if (is_string($default) && $default !== '') {
            return $default;
        }

        try {
            $methods = $this->client->paymentMethods->all([
                'customer' => $customerReference,
                'type' => 'card',
                'limit' => 1,
            ]);
        } catch (ApiErrorException $e) {
            throw GatewayFailure::unreachable('stripe', $e->getMessage());
        }

        return $methods->data[0]->id ?? null;
    }

    /**
     * Public because `StripeWebhookTranslator` — the inbound twin of this class — has to label
     * a card from a webhook payload the same way a card from an API response is labelled, or
     * an auto-updated Visa could come back spelled "visa" on the billing screen.
     */
    public static function brandLabel(string $stripeBrand): string
    {
        return match ($stripeBrand) {
            'amex' => 'American Express',
            'diners' => 'Diners Club',
            'discover' => 'Discover',
            'jcb' => 'JCB',
            'mastercard' => 'Mastercard',
            'unionpay' => 'UnionPay',
            'visa' => 'Visa',
            default => ucfirst($stripeBrand),
        };
    }
}
