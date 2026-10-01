<?php

namespace Modules\SuperAdmin\Domain;

/**
 * The three options every SMTP settings screen offers, mapped to what Symfony Mailer (which
 * Laravel's `smtp` transport wraps) actually needs: explicit `smtps` for implicit TLS (port
 * 465), or nothing for STARTTLS, which Symfony Mailer negotiates automatically when the server
 * offers it (the common case, port 587) — there is no separate "tls" scheme value to set.
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
