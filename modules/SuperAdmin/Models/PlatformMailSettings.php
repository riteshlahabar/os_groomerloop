<?php

namespace Modules\SuperAdmin\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\SuperAdmin\Domain\MailEncryption;

/**
 * The platform's own outbound-email configuration (spec §13, §31) — not tenant-owned, so no
 * `BelongsToTenant` (the same exemption `plans`/`plan_features` already have; `ModelTenancyGuardTest`
 * only flags a table that actually has a `tenant_id` column, which this one deliberately does
 * not). Exactly one row ever exists; `current()` is the only way this model is ever fetched.
 *
 * @property bool $is_enabled
 * @property ?MailEncryption $encryption
 */
final class PlatformMailSettings extends Model
{
    public const CACHE_KEY = 'super_admin.platform_mail_settings';

    protected $table = 'platform_mail_settings';

    protected $fillable = [
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'from_address',
        'from_name',
        'is_enabled',
    ];

    /**
     * Never serialised — this is the one real credential on the row, not configuration.
     *
     * @var list<string>
     */
    protected $hidden = ['password'];

    public static function current(): self
    {
        return self::query()->firstOrNew([]);
    }

    public function hasPassword(): bool
    {
        return $this->password !== null && $this->password !== '';
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
