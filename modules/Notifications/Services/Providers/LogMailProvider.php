<?php

namespace Modules\Notifications\Services\Providers;

use Illuminate\Support\Facades\Log;
use Modules\Notifications\Contracts\MailProvider;

/**
 * Writes to the application log instead of sending — honest about not actually reaching an
 * inbox rather than silently pretending to, the same spirit as `FakePaymentGateway`.
 *
 * No longer the bound `MailProvider` (`D-032`): `SmtpMailProvider` is, and delegates here when
 * neither the business nor the platform has an SMTP account configured. That keeps an
 * unconfigured install behaving exactly as it did under `D-025` instead of throwing on the next
 * appointment booking.
 */
final class LogMailProvider implements MailProvider
{
    public function send(string $toEmail, string $toName, string $subject, string $body): bool
    {
        Log::info('[notifications] email', [
            'to' => $toEmail,
            'name' => $toName,
            'subject' => $subject,
            'body' => $body,
        ]);

        return true;
    }
}
