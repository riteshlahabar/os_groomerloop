<?php

namespace Modules\Notifications\Actions;

use Illuminate\Support\Collection;
use Modules\Booking\Contracts\CancellationLinks;
use Modules\Catalog\Contracts\ServiceCatalog;
use Modules\Notifications\Domain\NotificationType;
use Modules\Notifications\Models\NotificationLog;
use Modules\Notifications\Services\NotificationDispatcher;
use Modules\Scheduling\Contracts\AppointmentScheduler;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;

/**
 * The scheduled half of spec §13: a reminder roughly a day ahead, which nothing about an HTTP
 * request can trigger on its own. Same shape as `Billing\Actions\ExpireLapsedSubscriptions` — a
 * sweep over every tenant on a clock, each processed inside its own `TenantContext::runFor()` so
 * every log row is attributed correctly, driven by cron rather than a persistent queue worker
 * (`D-011` still unresolved).
 */
final class SendAppointmentReminders
{
    private const WINDOW_START_HOURS = 23;

    private const WINDOW_END_HOURS = 25;

    public function __construct(
        private readonly TenantContext $tenants,
        private readonly AppointmentScheduler $scheduler,
        private readonly NotificationDispatcher $dispatcher,
        private readonly ServiceCatalog $catalog,
        private readonly CancellationLinks $cancellationLinks,
    ) {}

    public function execute(): int
    {
        $sent = 0;
        $from = now()->addHours(self::WINDOW_START_HOURS);
        $to = now()->addHours(self::WINDOW_END_HOURS);

        foreach ($this->activeTenants() as $tenant) {
            $this->tenants->runFor($tenant, function () use ($from, $to, &$sent): void {
                foreach ($this->scheduler->startingBetween($from, $to) as $appointment) {
                    if ($this->alreadyReminded($appointment->id)) {
                        continue;
                    }

                    $service = $this->catalog->find($appointment->serviceId);

                    $this->dispatcher->send(
                        $appointment->customerId,
                        NotificationType::AppointmentReminder,
                        [
                            'service_name' => $service?->name ?? 'your appointment',
                            'starts_at' => $appointment->startsAt->format('D, M j \a\t g:i A'),
                            'cancel_url' => $this->cancellationLinks->urlFor($appointment->id),
                        ],
                        $appointment->id,
                    );

                    $sent++;
                }
            });
        }

        return $sent;
    }

    private function alreadyReminded(int $appointmentId): bool
    {
        return NotificationLog::query()
            ->where('appointment_id', $appointmentId)
            ->where('type', NotificationType::AppointmentReminder->value)
            ->exists();
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function activeTenants()
    {
        return $this->tenants->withoutTenancy(
            static fn () => Tenant::query()->get()->filter(fn (Tenant $t): bool => $t->allowsAccess())
        );
    }
}
