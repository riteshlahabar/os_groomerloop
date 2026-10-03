<?php

namespace Modules\Notifications\Domain;

/**
 * The three options every SMTP settings screen offers, mapped to what Symfony Mailer (which
 * Laravel's `smtp` transport wraps) actually needs: explicit `smtps` for implicit TLS (port
 * 465), or nothing for STARTTLS, which Symfony Mailer negotiates automatically when the server
 * offers it (the common case, port 587) — there is no separate "tls" scheme value to set.
 *
 * Two modules need it: Notifications owns the per-tenant SMTP row and the sending driver,
 * SuperAdmin owns the platform-wide row and both settings screens. It lives here, alongside the
 * DTOs `Contracts\TenantMailSettings` hands across the boundary, the same shape
 * `Billing\Domain\SubscriptionSummary` already has. It moved out of `Modules\SuperAdmin\Domain`
 * when per-tenant mail arrived (`D-032`); the stored `none|tls|ssl` strings were unaffected.
 */
enum MailEncryption: string
{
    case None = 'none';
    case Tls = 'tls';
    case Ssl = 'ssl';

    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::Tls => 'TLS',
            self::Ssl => 'SSL',
        };
    }

    /**
     * The value Laravel's `mail.mailers.smtp.scheme` config key expects.
     */
    public function mailerScheme(): ?string
    {
        return $this === self::Ssl ? 'smtps' : null;
    }
}
