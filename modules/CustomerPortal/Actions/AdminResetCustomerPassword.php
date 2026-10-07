<?php

namespace Modules\CustomerPortal\Actions;

use Modules\Audit\Contracts\AuditRecorder;
use Modules\Crm\Contracts\CustomerDirectory;

/**
 * Lets staff set or replace a customer's Customer Portal password from `/admin/customers`
 * (D-043) — the admin-side equivalent of `ClaimCustomerAccount`, which does the identical write
 * from the customer's own signed claim link. `CustomerDirectory::setPassword()` is still the
 * only place the column is ever written (D-007; Crm owns `Customer`).
 *
 * Deliberately a separate action from the claim-link flow rather than a shared one: that flow's
 * audit event (`customer.account_claimed`) means "the customer did this themselves," and
 * collapsing the two would make an audit reader unable to tell a customer setting their own
 * password from a staff member doing it for them.
 */
final class AdminResetCustomerPassword
{
    public function __construct(
        private readonly CustomerDirectory $customers,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(int $customerId, string $plainPassword): void
    {
        $this->customers->setPassword($customerId, $plainPassword);

        // No Eloquent model to hand the recorder as $subject (D-007) — the same treatment
        // ClaimCustomerAccount gives this exact situation. `DatabaseAuditRecorder` still reads
        // the acting staff user from the `web` guard explicitly, so the audit trail already
        // knows who did this without this class passing it along.
        $this->audit->record('customer.portal_password_reset_by_staff', properties: ['customer_id' => $customerId]);
    }
}
