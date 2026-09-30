<?php

namespace Modules\Billing\Domain;

/**
 * What a gateway said when asked for money.
 *
 * A failed charge is a *result*, not an exception. Declines are ordinary and expected — an
 * expired card, insufficient funds — and the product's answer to them is the dunning cycle of
 * spec §24, not an error page. Exceptions are reserved for the gateway being unreachable or
 * misconfigured, which is a different problem with a different response.
 */
final readonly class ChargeResult
{
    private function __construct(
        public bool $successful,
        public string $reference,
        public int $amountCents,
        public ?string $failureCode = null,
        public ?string $failureMessage = null,
    ) {}

    public static function succeeded(string $reference, int $amountCents): self
    {
        return new self(
            successful: true,
            reference: $reference,
            amountCents: $amountCents,
        );
    }

    public static function declined(string $reference, int $amountCents, string $code, string $message): self
    {
        return new self(
            successful: false,
            reference: $reference,
            amountCents: $amountCents,
            failureCode: $code,
            failureMessage: $message,
        );
    }

    public function failed(): bool
    {
        return ! $this->successful;
    }
}
