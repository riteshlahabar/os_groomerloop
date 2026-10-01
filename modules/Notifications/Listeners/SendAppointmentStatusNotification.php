<?php

namespace Modules\Notifications\Listeners;

use Modules\Catalog\Contracts\ServiceCatalog;
use Modules\Notifications\Domain\NotificationType;
use Modules\Notifications\Services\NotificationDispatcher;
use Modules\Scheduling\Domain\AppointmentStatus;
use Modules\Scheduling\Events\AppointmentStatusChanged;

/**
 * Spec §13 "confirmation ... reschedule/cancel". Only the two transitions a customer needs to
 * hear about trigger a message — checked-in/in-service/completed are internal, operational
 * progress a groomer records, not news for the customer's inbox. No-show follow-up is a delayed
 * message sent some time *after* the no-show, not at the moment it is marked, so it belongs to a
 * scheduled sweep rather than this immediate listener — not built this phase (see `D-025`).
 */
final class SendAppointmentStatusNotification
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly ServiceCatalog $catalog,
    ) {}

    public function handle(AppointmentStatusChanged $event): void
    {
        $type = match ($event->to) {
            AppointmentStatus::Confirmed => NotificationType::BookingConfirmed,
            AppointmentStatus::Cancelled => NotificationType::BookingCancelled,
            default => null,
        };

        if ($type === null) {
            return;
        }

        $service = $this->catalog->find($event->appointment->serviceId);

        $this->dispatcher->send(
            $event->appointment->customerId,
            $type,
            [
                'service_name' => $service?->name ?? 'your appointment',
                'starts_at' => $event->appointment->startsAt->format('D, M j \a\t g:i A'),
            ],
            $event->appointment->id,
        );
    }
}
