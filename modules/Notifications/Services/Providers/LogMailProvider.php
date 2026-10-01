<?php

namespace Modules\Notifications\Services\Providers;

use Illuminate\Support\Facades\Log;
use Modules\Notifications\Contracts\MailProvider;

/**
 * The bound `MailProvider` until a real one is chosen (`D-025`). Writes to the application log
 * instead of sending — honest about not actually reaching an inbox, rather than silently
 * pretending to, the same spirit as `FakePaymentGateway` but used as the real binding rather than
 * only a test double, since no real mail credentials exist in this environment yet.
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
