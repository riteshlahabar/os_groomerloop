<?php

namespace Modules\Notifications\Domain;

/**
 * Everything `SmtpMailProvider` needs to send one message for one business: the on-demand
 * mailer configuration Laravel's `MailManager::build()` expects, plus the sender identity to
 * stamp on the message.
 *
 * Internal to Notifications — it carries a live SMTP password, so unlike
 * `TenantMailSettingsSnapshot` it never crosses a module boundary and is never serialised into
 * an API response.
 */
final readonly class ResolvedTenantMailer
{
    /**
     * @param  array<string, mixed>  $config  a `mail.mailers.*` shaped array
     */
    public function __construct(
        public array $config,
        public string $fromAddress,
        public ?string $fromName,
        public ?string $replyTo,
    ) {}
}
