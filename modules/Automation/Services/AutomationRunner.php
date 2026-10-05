<?php

namespace Modules\Automation\Services;

use Modules\Automation\Domain\AutomationKey;
use Modules\Automation\Models\AutomationRun;
use Modules\Crm\Contracts\CustomerDirectory;
use Modules\Notifications\Contracts\MessageSender;

/**
 * Fires one {@see AutomationKey}, if it is enabled and has not already fired for this subject —
 * the one place both the dedup check and the actual action (send a message, or tag a customer)
 * happen, used by both the immediate listener and the cron sweep so the two cannot disagree about
 * what counts as "already done."
 */
final class AutomationRunner
{
    public function __construct(
        private readonly AutomationSettingsManager $settings,
        private readonly MessageSender $messages,
        private readonly CustomerDirectory $customers,
    ) {}

    /**
     * @param  array<string, string>  $context
     * @return bool whether this call actually fired something — false for a disabled automation
     *              or one already run for this appointment, so a caller counting "how many did I
     *              just do" (the cron sweep's summary line) does not count a skip as a fire.
     */
    public function fireForAppointment(AutomationKey $key, int $appointmentId, int $customerId, array $context): bool
    {
        if (! $this->settings->isEnabled($key)) {
            return false;
        }

        $alreadyRan = AutomationRun::query()
            ->where('automation_key', $key->value)
            ->where('appointment_id', $appointmentId)
            ->exists();

        if ($alreadyRan) {
            return false;
        }

        $type = $key->notificationType();

        if ($type !== null) {
            $this->messages->send($customerId, $type, $context, $appointmentId);
        }

        AutomationRun::query()->create([
            'automation_key' => $key->value,
            'customer_id' => $customerId,
            'appointment_id' => $appointmentId,
        ]);

        return true;
    }

    public function fireForCustomer(AutomationKey $key, int $customerId): bool
    {
        if (! $this->settings->isEnabled($key)) {
            return false;
        }

        $alreadyRan = AutomationRun::query()
            ->where('automation_key', $key->value)
            ->where('customer_id', $customerId)
            ->exists();

        if ($alreadyRan) {
            return false;
        }

        $this->customers->tagCustomer($customerId, $key->label());

        AutomationRun::query()->create([
            'automation_key' => $key->value,
            'customer_id' => $customerId,
            'appointment_id' => null,
        ]);

        return true;
    }
}
