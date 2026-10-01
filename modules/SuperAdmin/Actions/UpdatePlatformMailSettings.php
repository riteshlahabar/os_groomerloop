<?php

namespace Modules\SuperAdmin\Actions;

use Illuminate\Support\Facades\Cache;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\SuperAdmin\Models\PlatformMailSettings;

/**
 * Change the one SMTP account GroomerLoop sends every tenant's notification email through
 * (spec §13, §31). There is exactly one row; this both creates it on first use and updates it
 * afterward.
 */
final class UpdatePlatformMailSettings
{
    public function __construct(
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  host, port, encryption, username, from_address,
     *                                            from_name, is_enabled, and password (optional —
     *                                            omitted or blank keeps whatever is already
     *                                            stored, so the admin never has to re-type it to
     *                                            change an unrelated field)
     */
    public function execute(array $attributes): PlatformMailSettings
    {
        $settings = PlatformMailSettings::current();

        $password = $attributes['password'] ?? null;
        unset($attributes['password']);

        $settings->fill($attributes);

        if ($password !== null && $password !== '') {
            $settings->password = $password;
        }

        $settings->save();

        // The boot-time config override (SuperAdminServiceProvider) reads this cache key, not
        // the table directly — forgetting it is what makes an edit actually take effect on the
        // next request. A running queue worker still needs restarting (`php artisan
        // queue:restart`): its process already booted with the old config.
        Cache::forget(PlatformMailSettings::CACHE_KEY);

        $this->audit->record('platform.mail_settings_updated', $settings, [
            'host' => $settings->host,
            'port' => $settings->port,
            'encryption' => $settings->encryption?->value,
            'from_address' => $settings->from_address,
            'is_enabled' => $settings->is_enabled,
            // Never the password itself — brand and last-four is the Billing precedent for
            // "enough to recognise, never enough to leak"; here there is nothing safe to show
            // at all, so the audit just records that it changed.
            'password_changed' => $password !== null && $password !== '',
        ]);

        return $settings;
    }
}
