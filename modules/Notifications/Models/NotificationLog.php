<?php

namespace Modules\Notifications\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Crm\Domain\CommunicationChannel;
use Modules\Notifications\Domain\DeliveryStatus;
use Modules\Notifications\Domain\NotificationType;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * One attempted send (spec §13 "delivery logs"). Append-only: nothing in this module updates or
 * deletes a row here.
 *
 * `customer_id`/`appointment_id` are plain foreign-key columns, never Eloquent relations to
 * Crm's/Scheduling's own models (D-007) — read back through each module's own contract when a
 * response needs a name rather than just an id.
 *
 * @property NotificationType $type
 * @property CommunicationChannel $channel
 * @property DeliveryStatus $status
 */
final class NotificationLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'customer_id',
        'type',
        'channel',
        'status',
        'recipient',
        'subject',
        'appointment_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'channel' => CommunicationChannel::class,
            'status' => DeliveryStatus::class,
        ];
    }
}
