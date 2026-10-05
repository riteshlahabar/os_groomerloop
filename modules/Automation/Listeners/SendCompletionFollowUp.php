<?php

namespace Modules\Automation\Listeners;

use Modules\Automation\Domain\AutomationKey;
use Modules\Automation\Services\AutomationRunner;
use Modules\Catalog\Contracts\ServiceCatalog;
use Modules\Scheduling\Domain\AppointmentStatus;
use Modules\Scheduling\Events\AppointmentStatusChanged;

/**
 * Spec §18 "Appointment completed → send follow-up" — the one {@see AutomationKey} that fires
 * immediately rather than on a cron sweep, because the event that means "completed" already
 * exists and there is nothing to wait for.
 */
final class SendCompletionFollowUp
{
    public function __construct(
        private readonly AutomationRunner $runner,
        private readonly ServiceCatalog $catalog,
    ) {}

    public function handle(AppointmentStatusChanged $event): void
    {
        if ($event->to !== AppointmentStatus::Completed) {
            return;
        }

        $service = $this->catalog->find($event->appointment->serviceId);

        $this->runner->fireForAppointment(
            AutomationKey::AppointmentCompletedFollowUp,
            $event->appointment->id,
            $event->appointment->customerId,
            ['service_name' => $service?->name ?? 'your appointment'],
        );
    }
}
