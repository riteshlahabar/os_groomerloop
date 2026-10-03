<?php

namespace Modules\Notifications\Contracts;

/**
 * Sending an email, behind an interface (invariant #5, spec §30).
 *
 * Two implementations: `SmtpMailProvider`, bound since `D-032`, which sends through the
 * business's own SMTP account or the platform's; and `LogMailProvider`, which writes to the
 * application log and which the SMTP driver falls back to when neither account is configured.
 * Nothing above this line may know which one ran, or whether delivery was real — the
 * `PaymentGateway` precedent (`D-025`), and invariant #5.
 */
interface MailProvider
{
    /**
     * @return bool true if the message was accepted for delivery
     */
    public function send(string $toEmail, string $toName, string $subject, string $body): bool;
}
