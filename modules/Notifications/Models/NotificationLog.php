<?php

namespace Modules\Notifications\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
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

    /**
     * Three attempts in total. Enough to ride out a provider blip, few enough that a customer with a
     * dead email address is not messaged about indefinitely.
     */
    public const MAX_ATTEMPTS = 3;

    protected $fillable = [
        'customer_id',
        'type',
        'channel',
        'status',
        'recipient',
        'subject',
        'context',
        'failure_reason',
        'attempt',
        'retry_of_id',
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
            'context' => 'array',
        ];
    }

    /**
     * May this attempt be tried again (spec §35: "notification failures are logged and retryable")?
     *
     * Only a genuine delivery failure. A send skipped for lack of consent must never be retried —
     * invariant #9 is not a transient error, and a retry loop over it would be the exact bug that
     * invariant exists to prevent. A successful send is not retried either; re-sending it would mean
     * messaging the customer twice.
     */
    public function isRetryable(int $maxAttempts = self::MAX_ATTEMPTS): bool
    {
        return $this->status === DeliveryStatus::Failed && $this->attempt < $maxAttempts;
    }

    /**
     * The rows that genuinely still need a retry: a failure, with attempts left, that nothing has
     * already tried again.
     *
     * One definition, used by both the cron sweep and the Messages screen's "needs a retry" tile.
     * They disagreed when each had its own: the tile counted every failed row under the attempt cap,
     * so a message tried three times showed as three things needing attention while the sweep
     * correctly saw none.
     *
     * `retry_of_id` is null on an original attempt, so the chain is keyed by
     * `coalesce(retry_of_id, id)` — every attempt of one message shares that value.
     *
     * @param  Builder<NotificationLog>  $query
     * @return Builder<NotificationLog>
     */
    public function scopeAwaitingRetry(Builder $query): Builder
    {
        return $query
            ->where('status', DeliveryStatus::Failed->value)
            ->where('attempt', '<', self::MAX_ATTEMPTS)
            ->whereNotExists(function (QueryBuilder $sub): void {
                $sub->selectRaw('1')
                    ->from('notification_logs as newer')
                    // The subquery is raw SQL and carries no global scope, so tenancy is asserted
                    // here by hand (invariant #1): a newer attempt only counts within the same
                    // business.
                    ->whereColumn('newer.tenant_id', 'notification_logs.tenant_id')
                    ->whereRaw('newer.retry_of_id = coalesce(notification_logs.retry_of_id, notification_logs.id)')
                    ->whereColumn('newer.id', '>', 'notification_logs.id');
            });
    }
}
