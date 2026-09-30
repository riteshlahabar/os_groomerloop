<?php

namespace Modules\Billing\Domain;

/**
 * The safe, storable remnant of a payment method.
 *
 * Deliberately cannot hold a card number, CVC or expiry-with-PAN: the gateway holds the
 * instrument and hands back a token, and this object carries only what a person needs to
 * recognise their own card on a billing screen (spec §24 "Payment method management").
 *
 * Spec §28 requires secure handling of customer data, and the cheapest way to never leak a
 * card number is to have no field that could contain one.
 */
final readonly class PaymentMethodDetails
{
    public function __construct(
        /** Opaque gateway reference. Meaningless outside the gateway that issued it. */
        public string $token,
        public string $brand,
        public string $lastFour,
        public int $expiryMonth,
        public int $expiryYear,
    ) {}

    public function describe(): string
    {
        return sprintf('%s ending %s', $this->brand, $this->lastFour);
    }

    public function isExpired(?int $year = null, ?int $month = null): bool
    {
        $year ??= (int) date('Y');
        $month ??= (int) date('n');

        return $this->expiryYear < $year
            || ($this->expiryYear === $year && $this->expiryMonth < $month);
    }
}
