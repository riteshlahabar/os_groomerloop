<?php

namespace Modules\Notifications\Console;

use Illuminate\Console\Command;
use Modules\Notifications\Actions\RetryFailedNotifications;

/**
 * Meant to run every 15 minutes via cron (cPanel's Cron Jobs — `D-011`, `D-031`). This is what makes
 * §35's "notification failures are logged and retryable" true on a host with no persistent queue
 * worker: the retry is a scheduled sweep rather than a queue's own backoff.
 */
final class RetryFailedNotificationsCommand extends Command
{
    protected $signature = 'notifications:retry-failed';

    protected $description = 'Retry recent failed notification deliveries (up to 3 attempts each)';

    public function handle(RetryFailedNotifications $retry): int
    {
        ['attempted' => $attempted, 'sent' => $sent] = $retry->execute();

        $this->info("Retried {$attempted} failed notification(s); {$sent} went out.");

        return self::SUCCESS;
    }
}
