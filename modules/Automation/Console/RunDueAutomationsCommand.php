<?php

namespace Modules\Automation\Console;

use Illuminate\Console\Command;
use Modules\Automation\Actions\RunDueAutomations;

/**
 * Meant to run hourly via cron (cPanel's Cron Jobs, not a persistent worker — `D-011`), a third
 * entry alongside `notifications:send-reminders` and `notifications:retry-failed`. Until this is
 * installed on the host, none of the four delay-based automations ever fire — the same
 * production requirement `D-031` already names for those two.
 */
final class RunDueAutomationsCommand extends Command
{
    protected $signature = 'automation:run-due';

    protected $description = 'Fire any due delay-based automations (rebooking reminders, review requests, no-show follow-ups, retention tags)';

    public function handle(RunDueAutomations $run): int
    {
        $count = $run->execute();

        $this->info("Fired {$count} automation(s).");

        return self::SUCCESS;
    }
}
