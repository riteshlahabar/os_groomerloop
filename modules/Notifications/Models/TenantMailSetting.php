<?php

namespace Modules\Notifications\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Notifications\Domain\MailEncryption;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * One business's own outbound SMTP account (spec §13, `D-032`).
 *
 * Tenant-owned, so `BelongsToTenant` applies — a GroomerLoop admin editing this from `/platform`
 * reaches it through `TenantContext::runFor()` like any other tenant-scoped record, rather than
 * through an unscoped query (invariant #1).
 *
 * Never fetched outside this module: SuperAdmin goes through
 * `Notifications\Contracts\TenantMailSettings`, which hands back plain DTOs.
 *
 * @property bool $is_enabled
 * @property ?MailEncryption $encryption
 */
final class TenantMailSetting extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_mail_settings';

    protected $fillable = [
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'from_address',
        'from_name',
        'reply_to',
        'is_enabled',
    ];

    /**
     * Never serialised — this is the one real credential on the row, not configuration.
     *
     * @var list<string>
     */
    protected $hidden = ['password'];

    /**
     * The current tenant's row, or an unsaved one so a first edit has something to fill. Mirrors
     * `PlatformMailSettings::current()`; the global scope supplies the tenant, so this must only
     * ever be called inside tenant context.
     */
    public static function currentOrNew(): self
    {
        return self::query()->firstOrNew([]);
    }

    public function hasPassword(): bool
    {
        return $this->password !== null && $this->password !== '';
    }

    /**
     * Whether this row is both switched on and complete enough to actually send through.
     *
     * A half-filled configuration is refused at validation time, but a host could still be
     * emptied by a later edit, and "enabled but unusable" must fall back to the platform account
     * rather than throw on the next appointment booking.
     */
    public function isUsable(): bool
    {
        return $this->is_enabled
            && $this->host !== null && $this->host !== ''
            && $this->port !== null
            && $this->from_address !== null && $this->from_address !== '';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'encryption' => MailEncryption::class,
            // Keyed on APP_KEY — the standard Eloquent encrypted cast, not a bespoke scheme.
            'password' => 'encrypted',
            'is_enabled' => 'boolean',
        ];
    }
}
