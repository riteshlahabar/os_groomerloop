<?php

namespace Modules\CustomerPortal\Actions;

use Modules\Crm\Contracts\CustomerDirectory;
use Modules\CustomerPortal\Contracts\AccountClaimLinks;
use Modules\Notifications\Contracts\MessageSender;
use Modules\Notifications\Domain\NotificationType;

/**
 * Sends a signed "set your password" link to whichever customer matches the given email in the
 * current tenant — or does nothing at all when none does. The caller (`ClaimLinkRequestController`)
 * always answers identically either way, the same email-enumeration defence `AuthenticateUser`
 * already applies to staff login: an attacker probing this endpoint learns nothing about which
 * emails belong to a real customer of this business.
 *
 * Deliberately callable for an already-claimed account too — the same link doubles as "forgot my
 * password", since requesting a fresh one and overwriting whatever was there is simpler than a
 * second flow, and it is still the owner of that inbox who can act on it.
 */
final class RequestAccountClaimLink
{
    public function __construct(
        private readonly CustomerDirectory $customers,
        private readonly AccountClaimLinks $links,
        private readonly MessageSender $sender,
    ) {}

    public function execute(string $email): void
    {
        $customerId = $this->customers->findIdByEmail($email);

        if ($customerId === null) {
            return;
        }

        $this->sender->send(
            customerId: $customerId,
            type: NotificationType::AccountClaimLink,
            context: ['claim_url' => $this->links->urlFor($customerId)],
        );
    }
}
