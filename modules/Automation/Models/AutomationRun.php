<?php

namespace Modules\Automation\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Automation\Domain\AutomationKey;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * One automation that fired (spec §18 "logs"). Append-only: nothing in this module updates or
 * deletes a row here — the same convention `NotificationLog` uses.
 *
 * `customer_id`/`appointment_id` are plain foreign-key columns, never Eloquent relations to
 * Crm's/Scheduling's own models (D-007).
 *
 * @property AutomationKey $automation_key
 */
final class AutomationRun extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'automation_key',
        'customer_id',
        'appointment_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'automation_key' => AutomationKey::class,
        ];
    }
}
