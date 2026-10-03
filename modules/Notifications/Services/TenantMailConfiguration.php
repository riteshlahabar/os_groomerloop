<?php

namespace Modules\Notifications\Services;

use Modules\Notifications\Actions\UpdateTenantMailSettings;
use Modules\Notifications\Contracts\TenantMailSettings;
use Modules\Notifications\Domain\ResolvedTenantMailer;
use Modules\Notifications\Domain\TenantMailSettingsSnapshot;
use Modules\Notifications\Models\TenantMailSetting;

/**
 * The one place `tenant_mail_settings` is read (`D-032`).
 *
 * Two audiences, deliberately in one class so there is a single answer to "what is this
 * business's mail configuration":
 *
 *   - **SuperAdmin**, through `Contracts\TenantMailSettings` — snapshots and writes, never a
 *     password, never the Eloquent model.
 *   - **`SmtpMailProvider`**, through `mailerForCurrentTenant()` — the live credential, which is
 *     why that method is on the concrete class and not on the contract.
 *
 * Resolution happens per call rather than per boot. The platform-wide account can be pushed
 * into `config('mail.*')` once at boot because it is the same for everybody
 * (`SuperAdminServiceProvider`); a per-tenant account cannot, because a cron sweep walks many
 * tenants inside one process.
 */
final class TenantMailConfiguration implements TenantMailSettings
{
    public function __construct(
        private readonly UpdateTenantMailSettings $update,
    ) {}

    public function snapshotForCurrentTenant(): TenantMailSettingsSnapshot
    {
        return $this->toSnapshot(TenantMailSetting::currentOrNew());
    }

    public function updateForCurrentTenant(array $attributes): TenantMailSettingsSnapshot
    {
        return $this->toSnapshot($this->update->execute($attributes));
    }

    public function isLiveForCurrentTenant(): bool
    {
        return TenantMailSetting::currentOrNew()->isUsable() || $this->platformAccountIsLive();
    }

    public function configuredTenantIds(): array
    {
        return TenantMailSetting::query()
            ->acrossAllTenants()
            ->where('is_enabled', true)
            ->get()
            // `isUsable()` rather than a second `where` on each column: completeness is the
            // model's definition and belongs in one place, and the enabled set is small enough
            // that filtering it in PHP costs nothing.
            ->filter(static fn (TenantMailSetting $row): bool => $row->isUsable())
            ->map(static fn (TenantMailSetting $row): int => (int) $row->tenant_id)
            ->values()
            ->all();
    }

    /**
     * This business's own mailer, or null when it has none and the platform account should be
     * used instead.
     */
    public function mailerForCurrentTenant(): ?ResolvedTenantMailer
    {
        $settings = TenantMailSetting::currentOrNew();

        if (! $settings->isUsable()) {
            return null;
        }

        return new ResolvedTenantMailer(
            config: [
                'transport' => 'smtp',
                'host' => $settings->host,
                'port' => $settings->port,
                'username' => $settings->username,
                'password' => $settings->password,
                // Null for STARTTLS and for no encryption alike — Symfony Mailer negotiates the
                // first automatically and there is no scheme value for either. See MailEncryption.
                'scheme' => $settings->encryption?->mailerScheme(),
            ],
            fromAddress: (string) $settings->from_address,
            fromName: $settings->from_name,
            replyTo: $settings->reply_to,
        );
    }

    /**
     * Is the platform's own SMTP account configured and switched on?
     *
     * Read from `config('mail.*')`, never from `SuperAdmin\Models\PlatformMailSettings` — that
     * model belongs to another module (`D-007`). `SuperAdminServiceProvider` already pushes the
     * stored row into config at boot and only when it is enabled, so config is both the correct
     * answer and the one that also respects a plain `.env` SMTP setup.
     */
    private function platformAccountIsLive(): bool
    {
        $default = config('mail.default');

        if (! is_string($default) || $default === 'log' || $default === 'array') {
            return false;
        }

        if ($default !== 'smtp') {
            // Some other real transport (ses, postmark, a failover group) — configured
            // deliberately in config/mail.php, so take it at its word.
            return true;
        }

        $host = config('mail.mailers.smtp.host');

        return is_string($host) && $host !== '';
    }

    private function toSnapshot(TenantMailSetting $settings): TenantMailSettingsSnapshot
    {
        return new TenantMailSettingsSnapshot(
            host: $settings->host,
            port: $settings->port,
            encryption: $settings->encryption,
            username: $settings->username,
            hasPassword: $settings->hasPassword(),
            fromAddress: $settings->from_address,
            fromName: $settings->from_name,
            replyTo: $settings->reply_to,
            isEnabled: (bool) $settings->is_enabled,
            isUsable: $settings->isUsable(),
            updatedAt: $settings->updated_at?->toIso8601String(),
        );
    }
}
