<?php

namespace Modules\Billing\Services;

use Modules\Billing\Models\Invoice;
use Modules\Tenancy\Support\TenantContext;

/**
 * Allocates the human-facing invoice number (spec §24).
 *
 * Sequential per year, globally unique, and never reused: GL-2026-000417. Support is given
 * one string and finds exactly one invoice across the platform.
 *
 * Deliberately NOT the primary key and deliberately not derived from it. Exposing row ids in
 * a customer-visible document leaks how many invoices the platform has raised, and ties the
 * document number to a database detail that a migration could change.
 */
final class InvoiceNumbers
{
    public function __construct(private readonly TenantContext $tenants) {}

    /**
     * Allocate the next number.
     *
     * Reads across all tenants on purpose: the sequence is platform-wide, so scoping the
     * MAX() to the current business would hand the same number to two businesses in the same
     * year. This is a considered exception to invariant #1 — it reads one aggregate of one
     * column and returns no tenant's data.
     *
     * Must be called inside the transaction that creates the invoice. The row lock taken by
     * the aggregate plus the unique index on `number` is what prevents two concurrent
     * checkouts claiming the same one; the index is the real guarantee, this just avoids
     * losing the race in the common case.
     */
    public function next(): string
    {
        $prefix = (string) config('billing.invoice.prefix', 'GL');
        $year = date('Y');

        $sequence = $this->tenants->withoutTenancy(function () use ($prefix, $year): int {
            $latest = Invoice::query()
                ->withoutGlobalScopes()
                ->where('number', 'like', "{$prefix}-{$year}-%")
                ->lockForUpdate()
                ->orderByDesc('number')
                ->value('number');

            if ($latest === null) {
                return 1;
            }

            return ((int) substr((string) $latest, -6)) + 1;
        });

        return sprintf('%s-%s-%06d', $prefix, $year, $sequence);
    }
}
