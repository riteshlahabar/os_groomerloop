<?php

namespace Modules\Notifications\Console;

use Illuminate\Console\Command;
use Modules\Notifications\Actions\SendAppointmentReminders;

/**
 * Meant to run hourly via cron (cPanel's Cron Jobs, not a persistent worker — `D-011`). An hourly
 * cadence against a 2-hour window gives every appointment at least one pass where it falls inside
 * the window, without needing the window and the cron interval to line up exactly.
 */
final class SendAppointmentRemindersCommand extends Command
{
    protected $signature = 'notifications:send-reminders';

    protected $description = 'Send reminder notifications for appointments starting in roughly 24 hours';

    public function handle(SendAppointmentReminders $send): int
    {
        $count = $send->execute();

        $this->info("Sent {$count} reminder(s).");

        return self::SUCCESS;
    }
}
