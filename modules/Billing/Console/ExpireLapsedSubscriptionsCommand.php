<?php

namespace Modules\Billing\Console;

use Illuminate\Console\Command;
use Modules\Billing\Actions\ExpireLapsedSubscriptions;

/**
 * Ends grace periods and scheduled cancellations that have come due (spec §24).
 *
 * A command rather than a queued job because it is a sweep over every tenant on a clock, and
 * because D-011 has not settled how background work runs in production yet — a command can
 * be driven by cron on shared hosting, which a persistent queue worker cannot.
 */
final class ExpireLapsedSubscriptionsCommand extends Command
{
    protected $signature = 'billing:expire-lapsed';

    protected $description = 'Cancel subscriptions whose grace period or cancellation date has passed';

    public function handle(ExpireLapsedSubscriptions $expire): int
    {
        $count = $expire->execute();

        $this->info("Ended {$count} lapsed subscription(s).");

        return self::SUCCESS;
    }
}
