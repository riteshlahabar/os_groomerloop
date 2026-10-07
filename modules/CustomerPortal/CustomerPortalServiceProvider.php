<?php

namespace Modules\CustomerPortal;

use App\Support\ModuleServiceProvider;
use Modules\CustomerPortal\Contracts\AccountClaimLinks;
use Modules\CustomerPortal\Services\SignedAccountClaimLinks;

/**
 * Customer Portal (`D-043`) — a deliberate product extension, not spec v1.0 subject code (the
 * owner asked for a customer-facing login; §1–§40 never mentions one). Depends on Crm's
 * `CustomerDirectory` (login identity, password), Scheduling's `AppointmentScheduler` and Pets'
 * `PetDirectory` (both read-only, feeding this module's own appointment/pet history views), and
 * Notifications' `MessageSender` (the account-claim email) — so it boots after all four, which is
 * why it is listed last in `bootstrap/providers.php`.
 */
final class CustomerPortalServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();

        $this->app->bind(AccountClaimLinks::class, SignedAccountClaimLinks::class);
    }
}
