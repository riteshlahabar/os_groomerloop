<?php

namespace Modules\Notifications\Services\Providers;

use Illuminate\Support\Facades\Log;
use Modules\Notifications\Contracts\SmsProvider;

/**
 * The bound `SmsProvider` until a real one is chosen (`D-025`). See `LogMailProvider`'s docblock
 * — identical reasoning.
 */
final class LogSmsProvider implements SmsProvider
{
    public function send(string $toPhone, string $body): bool
    {
        Log::info('[notifications] sms', [
            'to' => $toPhone,
            'body' => $body,
        ]);

        return true;
    }
}
