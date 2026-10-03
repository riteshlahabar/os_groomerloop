<?php

namespace Modules\Notifications\Services\Providers;

use Illuminate\Mail\MailManager;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Modules\Notifications\Contracts\MailProvider;
use Modules\Notifications\Services\TenantMailConfiguration;
use Throwable;

/**
 * The real `MailProvider` (`D-032`) — the first one in this product that actually reaches an
 * inbox, replacing `LogMailProvider` as the bound implementation.
 *
 * Picks a mailer per message, in this order:
 *
 *   1. **This business's own SMTP account**, when it has a usable one (`tenant_mail_settings`,
 *      set by a GroomerLoop admin at `/platform/tenants/{id}/mail-settings`). Built on demand
 *      with `MailManager::build()` rather than registered as a named mailer, because a cron
 *      sweep walks many tenants inside one process and a named mailer would be cached across
 *      them — one business's reminder going out through another's account.
 *   2. **The platform account**, when the tenant has none: Laravel's default mailer, which
 *      `SuperAdminServiceProvider` has already pointed at the stored platform SMTP row at boot.
 *      This is exactly how every tenant behaved before per-tenant settings existed.
 *   3. **The log driver**, when neither is configured — an unconfigured install stays honest
 *      instead of throwing, and the §13 Messages screen keeps saying nothing was delivered.
 *
 * Nothing above `MailProvider` learns which of the three ran (invariant #5): the answer is the
 * same bool it has always been. A send that throws is logged with its reason and reported as
 * false, which the dispatcher records as `Failed` and `notifications:retry-failed` picks up.
 */
final class SmtpMailProvider implements MailProvider
{
    public function __construct(
        private readonly TenantMailConfiguration $configuration,
        private readonly MailManager $mail,
        private readonly LogMailProvider $fallback,
    ) {}

    public function send(string $toEmail, string $toName, string $subject, string $body): bool
    {
        $tenantMailer = $this->configuration->mailerForCurrentTenant();

        if ($tenantMailer === null && ! $this->configuration->isLiveForCurrentTenant()) {
            return $this->fallback->send($toEmail, $toName, $subject, $body);
        }

        try {
            $mailer = $tenantMailer === null
                ? $this->mail->mailer()
                : $this->mail->build($tenantMailer->config);

            $mailer->raw($body, function (Message $message) use ($toEmail, $toName, $subject, $tenantMailer): void {
                $message->to($toEmail, $toName)->subject($subject);

                if ($tenantMailer !== null) {
                    // Only when the tenant has its own account. On the platform mailer the from
                    // address comes from `mail.from`, which the platform settings screen owns.
                    $message->from($tenantMailer->fromAddress, $tenantMailer->fromName);

                    if ($tenantMailer->replyTo !== null && $tenantMailer->replyTo !== '') {
                        $message->replyTo($tenantMailer->replyTo);
                    }
                }
            });

            return true;
        } catch (Throwable $e) {
            // The contract answers with a bool, so the reason has to live here. Without this
            // line a misconfigured host is indistinguishable from a rejected recipient in the
            // delivery log, and neither is something a salon owner could act on.
            Log::error('[notifications] email send failed', [
                'to' => $toEmail,
                'subject' => $subject,
                'tenant_account' => $tenantMailer !== null,
                'reason' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
