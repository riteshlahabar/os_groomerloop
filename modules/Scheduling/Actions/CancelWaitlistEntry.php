<?php

namespace Modules\Scheduling\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Scheduling\Domain\WaitlistStatus;
use Modules\Scheduling\Models\WaitlistEntry;

/**
 * Give up a waiting slot (spec §11) — the customer no longer wants it, or staff cleared it by
 * hand. A booked entry is not cancelled through here: cancel the appointment it became instead,
 * the same way `AppointmentController::destroy()` already works.
 */
final class CancelWaitlistEntry
{
    public function __construct(
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(WaitlistEntry $entry): WaitlistEntry
    {
        if ($entry->status->isTerminal()) {
            throw ValidationException::withMessages([
                'waitlist_entry' => 'This waitlist entry is no longer waiting.',
            ]);
        }

        $entry->status = WaitlistStatus::Cancelled;
        $entry->save();

        $this->audit->record('waitlist.cancelled', $entry);

        return $entry;
    }
}
