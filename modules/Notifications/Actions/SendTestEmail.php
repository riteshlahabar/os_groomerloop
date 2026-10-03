<?php

namespace Modules\Notifications\Actions;

use Modules\Audit\Contracts\AuditRecorder;
use Modules\Notifications\Contracts\MailProvider;
use Modules\Notifications\Contracts\TestMailSender;
use Modules\Tenancy\Support\TenantContext;

/**
 * Prove a business's outbound mail configuration actually works (`D-032`).
 *
 * Sends through the bound `MailProvider`, which is the whole point: the test takes the same
 * route a booking confirmation will, so a success here is evidence about the real path rather
 * than about a separate test harness. It still cannot tell an admin whether the message landed
 * in an inbox or a spam folder — only that the server accepted it.
 *
 * Deliberately not consent-gated and not written to `notification_logs`: this is not a message
 * to a customer, it is a message to whoever is setting the account up. Invariant #9 is about
 * the customer's opt-out and is untouched by it.
 */
final class SendTestEmail implements TestMailSender
{
    public function __construct(
        private readonly MailProvider $mail,
        private readonly AuditRecorder $audit,
        private readonly TenantContext $tenants,
    ) {}

    public function send(string $toEmail): bool
    {
        $business = $this->tenants->tenant()?->name ?? 'your business';

        $sent = $this->mail->send(
            $toEmail,
            'GroomerLoop test',
            'GroomerLoop test message',
            "This is a test message from GroomerLoop for {$business}. "
                .'If you are reading it, outbound email for this business is configured correctly.',
        );

        $this->audit->record('tenant.mail_settings_tested', null, [
            'to' => $toEmail,
            'accepted' => $sent,
        ]);

        return $sent;
    }
}
