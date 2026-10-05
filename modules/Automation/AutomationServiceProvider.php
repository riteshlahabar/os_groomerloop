<?php

namespace Modules\Automation;

use App\Support\ModuleServiceProvider;
use Illuminate\Support\Facades\Event;
use Modules\Automation\Console\RunDueAutomationsCommand;
use Modules\Automation\Listeners\SendCompletionFollowUp;
use Modules\Scheduling\Events\AppointmentStatusChanged;

/**
 * Automation owns spec §18 — a fixed catalogue of five trigger/action pairs (see
 * `Domain\AutomationKey` for which spec §18 rows are and are not here, and why), each a tenant
 * opts into, acting through other modules' own contracts rather than a second send/write path:
 * `Notifications\Contracts\MessageSender` for messages, `Crm\Contracts\CustomerDirectory` for the
 * retention tag, `Scheduling\Contracts\AppointmentMetrics` for which appointments/customers are
 * due. It boots after all three, and after Entitlements for the grade cap on how many may be
 * enabled at once.
 */
final class AutomationServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Event::listen(AppointmentStatusChanged::class, SendCompletionFollowUp::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                RunDueAutomationsCommand::class,
            ]);
        }
    }
}
