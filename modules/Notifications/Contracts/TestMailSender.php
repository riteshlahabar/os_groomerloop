<?php

namespace Modules\Notifications\Contracts;

/**
 * Send one throwaway message to prove a business's mail configuration works (`D-032`).
 *
 * Separate from `TenantMailSettings` on purpose: the implementation needs `MailProvider`, and
 * the bound `MailProvider` already needs `TenantMailConfiguration` — folding this into the same
 * contract would make the container resolve a cycle. Separate from `MailProvider` too, because
 * a caller outside this module must not be able to send arbitrary mail, only a fixed test.
 *
 * Goes out through exactly the same provider as a real notification, so a green result means
 * the next appointment reminder will take the same path. It is not recorded in
 * `notification_logs` — that log is about messages to customers, and a test is addressed to
 * whoever is configuring the account.
 */
interface TestMailSender
{
    /**
     * @return bool true if the provider accepted the message for the current tenant
     */
    public function send(string $toEmail): bool;
}
