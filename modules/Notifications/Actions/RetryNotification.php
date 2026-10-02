<?php

namespace Modules\Notifications\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Notifications\Models\NotificationLog;
use Modules\Notifications\Services\NotificationDispatcher;

/**
 * Send one failed message again (spec §35: "notification failures are logged and retryable").
 *
 * Used by the §13 Messages screen's Retry button and by the cron sweep. A retry is audited because
 * it is a deliberate outbound message to a customer, taken by a person — exactly the kind of action
 * invariant #8 exists for.
 */
final class RetryNotification
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * The new attempt's log row, or null when this row must not be retried (already sent, skipped for
     * consent, or out of attempts). Null is an ordinary answer, not an error — a stale screen offering
     * Retry on a row that has since succeeded should get a quiet refusal.
     */
    public function execute(NotificationLog $log): ?NotificationLog
    {
        return DB::transaction(function () use ($log): ?NotificationLog {
            $retry = $this->dispatcher->retry($log);

            if ($retry === null) {
                return null;
            }

            $this->audit->record('notification.retried', $retry, [
                'original_id' => $log->getKey(),
                'type' => $retry->type->value,
                'channel' => $retry->channel->value,
                'attempt' => $retry->attempt,
                'status' => $retry->status->value,
            ]);

            return $retry;
        });
    }
}
