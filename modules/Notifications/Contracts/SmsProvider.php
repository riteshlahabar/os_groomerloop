<?php

namespace Modules\Notifications\Contracts;

/**
 * Sending a text message, behind an interface (invariant #5, spec §30). See `MailProvider`'s
 * docblock and `D-025` — same reasoning, same placeholder state.
 */
interface SmsProvider
{
    /**
     * @return bool  true if the message was accepted for delivery
     */
    public function send(string $toPhone, string $body): bool;
}
