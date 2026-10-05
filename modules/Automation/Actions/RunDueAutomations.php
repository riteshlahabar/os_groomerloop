<?php

namespace Modules\Automation\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Automation\Domain\AutomationKey;
use Modules\Automation\Services\AutomationRunner;
use Modules\Automation\Services\AutomationSettingsManager;
use Modules\Catalog\Contracts\ServiceCatalog;
use Modules\Scheduling\Contracts\AppointmentMetrics;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;

/**
 * The scheduled half of spec §18's four delay-based automations — same shape as
 * `Notifications\Actions\SendAppointmentReminders`: a sweep over every active tenant, each run
 * inside its own `TenantContext::runFor()`, driven by cron rather than a persistent queue worker
 * (`D-011`). The fifth key, `AppointmentCompletedFollowUp`, fires immediately off an event
 * instead and never reaches this sweep.
 */
final class RunDueAutomations
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly AppointmentMetrics $appointments,
        private readonly ServiceCatalog $catalog,
        private readonly AutomationSettingsManager $settings,
        private readonly AutomationRunner $runner,
    ) {}

    public function execute(): int
    {
        $fired = 0;

        foreach ($this->activeTenants() as $tenant) {
            $this->tenants->runFor($tenant, function () use (&$fired): void {
                $fired += $this->runRebookingReminders();
                $fired += $this->runReviewRequests();
                $fired += $this->runNoShowFollowUps();
                $fired += $this->runCustomerRetention();
            });
        }

        return $fired;
    }

    private function runRebookingReminders(): int
    {
        $key = AutomationKey::RebookingReminder;

        if (! $this->settings->isEnabled($key)) {
            return 0;
        }

        $delayDays = $this->settings->delayDaysFor($key);
        $fired = 0;

        foreach ($this->appointments->completedAppointmentsOlderThan(now(), $delayDays) as $candidate) {
            if ($this->appointments->hasBookedSince($candidate['customer_id'], Carbon::parse($candidate['ends_at']))) {
                continue;
            }

            $didFire = $this->runner->fireForAppointment($key, $candidate['appointment_id'], $candidate['customer_id'], [
                'service_name' => $this->serviceName($candidate['service_id']),
            ]);
            $fired += (int) $didFire;
        }

        return $fired;
    }

    private function runReviewRequests(): int
    {
        $key = AutomationKey::ReviewRequest;

        if (! $this->settings->isEnabled($key)) {
            return 0;
        }

        $delayDays = $this->settings->delayDaysFor($key);
        $fired = 0;

        foreach ($this->appointments->completedAppointmentsOlderThan(now(), $delayDays) as $candidate) {
            $didFire = $this->runner->fireForAppointment($key, $candidate['appointment_id'], $candidate['customer_id'], [
                'service_name' => $this->serviceName($candidate['service_id']),
            ]);
            $fired += (int) $didFire;
        }

        return $fired;
    }

    private function runNoShowFollowUps(): int
    {
        $key = AutomationKey::NoShowFollowUp;

        if (! $this->settings->isEnabled($key)) {
            return 0;
        }

        $delayDays = $this->settings->delayDaysFor($key);
        $fired = 0;

        foreach ($this->appointments->noShowAppointmentsOlderThan(now(), $delayDays) as $candidate) {
            $didFire = $this->runner->fireForAppointment($key, $candidate['appointment_id'], $candidate['customer_id'], [
                'service_name' => $this->serviceName($candidate['service_id']),
            ]);
            $fired += (int) $didFire;
        }

        return $fired;
    }

    private function runCustomerRetention(): int
    {
        $key = AutomationKey::CustomerRetentionTag;

        if (! $this->settings->isEnabled($key)) {
            return 0;
        }

        $delayDays = $this->settings->delayDaysFor($key);
        $fired = 0;

        foreach ($this->appointments->staleCustomerIds(now(), $delayDays) as $customerId) {
            $fired += (int) $this->runner->fireForCustomer($key, $customerId);
        }

        return $fired;
    }

    private function serviceName(int $serviceId): string
    {
        return $this->catalog->find($serviceId)?->name ?? 'your appointment';
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
