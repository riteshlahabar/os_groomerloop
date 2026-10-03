<?php

namespace Modules\Notifications\Actions;

use Modules\Audit\Contracts\AuditRecorder;
use Modules\Notifications\Models\TenantMailSetting;

/**
 * Create or change one business's own SMTP account (spec §13, §31; `D-032`). There is at most
 * one row per tenant; this both creates it on first use and updates it afterward.
 *
 * Runs inside the tenant's own context — `BelongsToTenant` fills and enforces `tenant_id`, and
 * the audit event picks the same tenant up from ambient `TenantContext`. A GroomerLoop admin
 * editing this from `/platform` therefore produces an audit trail on the tenant whose
 * configuration changed, not a platform-level one.
 *
 * Unlike `platform_mail_settings` there is no cache to invalidate: a tenant's row is read once
 * per send, not once per boot, so an edit takes effect on the very next message.
 */
final class UpdateTenantMailSettings
{
    public function __construct(
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  host, port, encryption, username, from_address,
     *                                            from_name, reply_to, is_enabled, and password
     *                                            (optional — omitted or blank keeps whatever is
     *                                            already stored, so the admin never has to
     *                                            re-type it to change an unrelated field)
     */
    public function execute(array $attributes): TenantMailSetting
    {
        $settings = TenantMailSetting::currentOrNew();

        $password = $attributes['password'] ?? null;
        unset($attributes['password']);

        $settings->fill($attributes);

        if ($password !== null && $password !== '') {
            $settings->password = $password;
        }

        $settings->save();

        $this->audit->record('tenant.mail_settings_updated', $settings, [
            'host' => $settings->host,
            'port' => $settings->port,
            'encryption' => $settings->encryption?->value,
            'from_address' => $settings->from_address,
            'reply_to' => $settings->reply_to,
            'is_enabled' => $settings->is_enabled,
            // Never the password itself — there is nothing safe to show of a credential, so the
            // audit records only that it changed, the precedent `UpdatePlatformMailSettings` set.
            'password_changed' => $password !== null && $password !== '',
        ]);

        return $settings;
    }
}
