<?php

namespace Modules\Billing\Domain;

/**
 * Spec §24 "Invoices/receipts" and "Billing history".
 *
 * Void and Uncollectible are distinct on purpose: voiding says the invoice should never have
 * been raised, writing it off says it was owed and will not be collected. Collapsing them
 * would make revenue reporting (§36 MRR, churn) unable to tell a billing mistake from a bad
 * debt.
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Paid = 'paid';
    case Void = 'void';
    case Uncollectible = 'uncollectible';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Open => 'Due',
            self::Paid => 'Paid',
            self::Void => 'Voided',
            self::Uncollectible => 'Written off',
        };
    }

    public function isSettled(): bool
    {
        return $this === self::Paid;
    }

    /**
     * Does this invoice still represent money the business owes?
     */
    public function isOutstanding(): bool
    {
        return $this === self::Open;
    }

    /**
     * A paid, voided or written-off invoice is a historical record and must never change
     * again — spec §24 keeps billing history, and history that can be edited is not history.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Paid, self::Void, self::Uncollectible], strict: true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }
}
