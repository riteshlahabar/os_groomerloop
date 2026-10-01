<?php

namespace Modules\SuperAdmin;

use App\Support\ModuleServiceProvider;
use Illuminate\Support\Facades\Cache;
use Modules\SuperAdmin\Models\PlatformMailSettings;
use Throwable;

/**
 * The first slice of spec §31's Super Admin console — GroomerLoop's own staff configuring
 * platform-wide settings, starting with the SMTP account every tenant's notification email
 * sends through (spec §13). Built ahead of §31's normal phase order (Phase 2) because the
 * owner asked for SMTP configuration directly; see `D-026`.
 *
 * Depends on nothing but the framework — no other module's contract — so it can register
 * anywhere in `bootstrap/providers.php`.
 */
final class SuperAdminServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        $this->applyStoredMailSettings();
    }

    /**
     * Overrides Laravel's own `mail` config with whatever is stored and enabled, once per
     * request. Cached (invalidated by `UpdatePlatformMailSettings` on every save) rather than
     * queried every boot — mail config is read far more often than it is written.
     *
     * Caches a plain array, never the Eloquent model itself: the database cache driver
     * serializes with PHP's native `serialize()`, and an Eloquent model's internal state
     * (relations, booted-trait bookkeeping) does not reliably round-trip through that — it can
     * come back as an unusable `__PHP_Incomplete_Class` on a later, unrelated process if the
     * class map resolves slightly differently, which is exactly what happened here. A plain
     * array has no such failure mode.
     *
     * Wrapped defensively: this runs on every single request, including the very first
     * `php artisan migrate` before this module's own table exists, and a config-loading
     * concern must never be what breaks the whole application's boot.
     */
    private function applyStoredMailSettings(): void
    {
        try {
            $settings = Cache::rememberForever(PlatformMailSettings::CACHE_KEY, static function (): array {
                $row = PlatformMailSettings::query()->first();

                return [
                    'is_enabled' => $row?->is_enabled ?? false,
                    'host' => $row?->host,
                    'port' => $row?->port,
                    'username' => $row?->username,
                    'password' => $row?->password,
                    'scheme' => $row?->encryption?->mailerScheme(),
                    'from_address' => $row?->from_address,
                    'from_name' => $row?->from_name,
                ];
            });
        } catch (Throwable) {
            return;
        }

        if (! $settings['is_enabled']) {
            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $settings['host'],
            'mail.mailers.smtp.port' => $settings['port'],
            'mail.mailers.smtp.username' => $settings['username'],
            'mail.mailers.smtp.password' => $settings['password'],
            'mail.mailers.smtp.scheme' => $settings['scheme'],
            'mail.from.address' => $settings['from_address'],
            'mail.from.name' => $settings['from_name'],
        ]);
    }
}
