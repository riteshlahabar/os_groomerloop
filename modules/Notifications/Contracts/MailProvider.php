<?php

namespace Modules\Notifications\Contracts;

/**
 * Sending an email, behind an interface (invariant #5, spec §30).
 *
 * No real driver exists yet — `D-025` records why the bound implementation logs instead of
 * sending, matching `PaymentGateway`'s precedent that nothing above this line may know which
 * provider (or whether a real one) is in use.
 */
interface MailProvider
{
    /**
     * @return bool true if the message was accepted for delivery
     */
    public function send(string $toEmail, string $toName, string $subject, string $body): bool;
}
