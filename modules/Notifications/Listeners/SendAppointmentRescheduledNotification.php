<?php

namespace Modules\Notifications\Listeners;

use Modules\Catalog\Contracts\ServiceCatalog;
use Modules\Notifications\Domain\NotificationType;
use Modules\Notifications\Services\NotificationDispatcher;
use Modules\Scheduling\Events\AppointmentRescheduled;

final class SendAppointmentRescheduledNotification
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly ServiceCatalog $catalog,
    ) {}

    public function handle(AppointmentRescheduled $event): void
    {
        $service = $this->catalog->find($event->appointment->serviceId);

        $this->dispatcher->send(
            $event->appointment->customerId,
            NotificationType::BookingRescheduled,
            [
                'service_name' => $service?->name ?? 'your appointment',
                'starts_at' => $event->appointment->startsAt->format('D, M j \a\t g:i A'),
                'previous_starts_at' => $event->previousStart->format('D, M j \a\t g:i A'),
            ],
            $event->appointment->id,
        );
    }
}
