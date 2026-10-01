<?php

namespace Modules\Notifications\Listeners;

use Modules\Catalog\Contracts\ServiceCatalog;
use Modules\Notifications\Domain\NotificationType;
use Modules\Notifications\Services\NotificationDispatcher;
use Modules\Scheduling\Events\AppointmentBooked;

/**
 * Spec §13 "confirmation, request" — a new appointment is created `requested` (spec §11's state
 * machine), so what goes out here is the request acknowledgement, not a confirmation; that follows
 * separately, from `SendAppointmentStatusNotification`, if and when the business confirms it.
 */
final class SendAppointmentBookedNotification
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly ServiceCatalog $catalog,
    ) {}

    public function handle(AppointmentBooked $event): void
    {
        $service = $this->catalog->find($event->appointment->serviceId);

        $this->dispatcher->send(
            $event->appointment->customerId,
            NotificationType::BookingRequested,
            [
                'service_name' => $service?->name ?? 'your appointment',
                'starts_at' => $event->appointment->startsAt->format('D, M j \a\t g:i A'),
            ],
            $event->appointment->id,
        );
    }
}
