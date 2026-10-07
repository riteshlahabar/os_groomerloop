<?php

namespace Modules\CustomerPortal\Actions;

use Modules\Audit\Contracts\AuditRecorder;
use Modules\Crm\Contracts\CustomerDirectory;

/**
 * Sets a customer's Customer Portal password from a verified signed claim link — the `signed`
 * route middleware has already authenticated the *request* before this ever runs (see
 * `AccountClaimController`), so there is no separate credential check here.
 *
 * `CustomerDirectory::setPassword()` is the only way the password column is ever written
 * (D-007; Crm owns `Customer`), the same boundary every other cross-module write in this module
 * respects.
 */
final class ClaimCustomerAccount
{
    public function __construct(
        private readonly CustomerDirectory $customers,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(int $customerId, string $plainPassword): void
    {
        $this->customers->setPassword($customerId, $plainPassword);

        // No Eloquent model to hand the recorder as $subject (D-007) — the customer id alone,
        // in the event name's own context, is what every other platform-side audit row for an
        // actor outside the `users` table already does (see the ambient-tenant_id note in
        // CLAUDE.md's "Known traps").
        $this->audit->record('customer.account_claimed', properties: ['customer_id' => $customerId]);
    }
}
