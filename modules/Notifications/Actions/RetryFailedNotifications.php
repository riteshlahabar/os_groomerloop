<?php

namespace Modules\Notifications\Actions;

use Illuminate\Support\Collection;
use Modules\Notifications\Domain\DeliveryStatus;
use Modules\Notifications\Models\NotificationLog;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;

/**
 * The automatic half of §35's "retryable": a sweep over every tenant's recent failures, driven by
 * cron (`notifications:retry-failed`) because this host has no persistent queue worker (`D-011`,
 * `D-031`).
 *
 * Same shape as `SendAppointmentReminders` and `Billing\Actions\ExpireLapsedSubscriptions`: each
 * tenant is processed inside its own `TenantContext::runFor()` so every log row it writes is
 * attributed to the right business.
 */
final class RetryFailedNotifications
{
    /**
     * Only recent failures. A message about an appointment two weeks ago is not worth sending late,
     * and an unbounded sweep would keep re-reading rows that will never succeed.
     */
    private const LOOKBACK_HOURS = 48;

    public function __construct(
        private readonly TenantContext $tenants,
        private readonly RetryNotification $retry,
    ) {}

    /**
     * @return array{attempted: int, sent: int}
     */
    public function execute(): array
    {
        $attempted = 0;
        $sent = 0;

        foreach ($this->activeTenants() as $tenant) {
            $this->tenants->runFor($tenant, function () use (&$attempted, &$sent): void {
                $failures = NotificationLog::query()
                    // Failures with attempts left that nothing has already tried again — without the
                    // "already tried" half, one bad email address would be retried once per historic
                    // row in its chain.
                    ->awaitingRetry()
                    ->where('created_at', '>=', now()->subHours(self::LOOKBACK_HOURS))
                    // Oldest first, so a backlog clears in the order customers were meant to hear.
                    ->orderBy('id')
                    ->get();

                foreach ($failures as $failure) {
                    $result = $this->retry->execute($failure);
                    $attempted++;

                    if ($result?->status === DeliveryStatus::Sent) {
                        $sent++;
                    }
                }
            });
        }

        return ['attempted' => $attempted, 'sent' => $sent];
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
