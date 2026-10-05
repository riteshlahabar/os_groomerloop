<?php

namespace Modules\Automation\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Automation\Domain\AutomationKey;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * Whether, and after how many days, one {@see AutomationKey} is turned on for this tenant.
 *
 * @property AutomationKey $automation_key
 */
final class AutomationSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'automation_key',
        'is_enabled',
        'delay_days',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'automation_key' => AutomationKey::class,
            'is_enabled' => 'boolean',
        ];
    }
}
