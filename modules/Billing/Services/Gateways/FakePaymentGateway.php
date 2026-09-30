<?php

namespace Modules\Billing\Services\Gateways;

use Illuminate\Support\Str;
use Modules\Billing\Contracts\PaymentGateway;
use Modules\Billing\Domain\ChargeResult;
use Modules\Billing\Domain\PaymentMethodDetails;
use Modules\Billing\Exceptions\GatewayFailure;

/**
 * The gateway used in development, in tests, and anywhere no real provider is configured.
 *
 * It is a first-class driver, not a stub: it passes the same PaymentGatewayContractTest every
 * real driver must pass (CI guard #6). A fake that is allowed to behave differently from the
 * real thing is how a suite goes green against behaviour production does not have.
 *
 * Declines are triggered by convention rather than randomly, so the dunning path of spec §24
 * is testable end to end: a token containing "decline" is declined, one containing "error"
 * raises a gateway failure. Deterministic beats realistic here — a flaky billing suite gets
 * ignored.
 */
final class FakePaymentGateway implements PaymentGateway
{
    /** @var array<string, list<PaymentMethodDetails>> */
    private array $methods = [];

    /** @var list<array{customer: string, amount: int, currency: string, description: string}> */
    private array $charges = [];

    public function createCustomer(string $name, string $email): string
    {
        return 'fake_cus_'.Str::lower(Str::random(16));
    }

    public function storePaymentMethod(string $customerReference, string $token): PaymentMethodDetails
    {
        if (str_contains($token, 'error')) {
            throw GatewayFailure::rejected('fake', 'the token was refused');
        }

        $details = new PaymentMethodDetails(
            token: 'fake_pm_'.Str::lower(Str::random(16)),
            brand: str_contains($token, 'amex') ? 'American Express' : 'Visa',
            lastFour: substr(preg_replace('/\D/', '', $token).'4242', -4),
            expiryMonth: 12,
            expiryYear: (int) date('Y') + 3,
        );

        $this->methods[$customerReference][] = $details;

        return $details;
    }

    public function forgetPaymentMethod(string $customerReference, string $token): void
    {
        $this->methods[$customerReference] = array_values(array_filter(
            $this->methods[$customerReference] ?? [],
            static fn (PaymentMethodDetails $m): bool => $m->token !== $token,
        ));
    }

    public function charge(string $customerReference, int $amountCents, string $currency, string $description): ChargeResult
    {
        if (str_contains($description, 'gateway-error')) {
            throw GatewayFailure::unreachable('fake');
        }

        $this->charges[] = [
            'customer' => $customerReference,
            'amount' => $amountCents,
            'currency' => $currency,
            'description' => $description,
        ];

        // The convention that makes the dunning cycle testable.
        if (str_contains($description, 'decline')) {
            return ChargeResult::declined(
                reference: 'fake_ch_'.Str::lower(Str::random(16)),
                amountCents: $amountCents,
                code: 'card_declined',
                message: 'The card was declined.',
            );
        }

        return ChargeResult::succeeded(
            reference: 'fake_ch_'.Str::lower(Str::random(16)),
            amountCents: $amountCents,
        );
    }

    /**
     * Test affordance, not part of the contract.
     *
     * @return list<array{customer: string, amount: int, currency: string, description: string}>
     */
    public function recordedCharges(): array
    {
        return $this->charges;
    }
}
