<?php

namespace Modules\Notifications\Domain;

/**
 * A business's SMTP configuration as another module is allowed to see it (`D-007`, `D-032`) —
 * never the Eloquent model, the same boundary `Billing\Domain\SubscriptionSummary` draws.
 * Built for SuperAdmin's per-tenant mail screen (spec §31), which has to read and write a
 * tenant's mail configuration without loading `Modules\Notifications\Models\TenantMailSetting`.
 *
 * Carries `hasPassword` rather than the password: a stored credential is never readable back
 * out, by any caller, which is why `PlatformMailSettingsResource` has exposed the same boolean
 * since `D-026`.
 */
final readonly class TenantMailSettingsSnapshot
{
    public function __construct(
        public ?string $host,
        public ?int $port,
        public ?MailEncryption $encryption,
        public ?string $username,
        public bool $hasPassword,
        public ?string $fromAddress,
        public ?string $fromName,
        public ?string $replyTo,
        public bool $isEnabled,
        /** True when this configuration is both switched on and complete enough to send with. */
        public bool $isUsable,
        public ?string $updatedAt,
    ) {}
}
