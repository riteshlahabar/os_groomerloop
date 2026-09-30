<?php

namespace Modules\Crm\Contracts;

use Modules\Crm\Domain\CommunicationChannel;

/**
 * How other modules look a customer up (D-007).
 *
 * Pets (§9) needs to know a customer exists before linking to it, Scheduling (§11) needs a
 * name for the calendar, and Notifications (§13) needs to know whether it is allowed to send
 * anything at all. None of them may load the Customer model.
 *
 * `mayContact` is the important one. Invariant #9 says opt-out is honoured across every
 * channel, and that is only true if there is exactly one implementation of the question
 * — not a per-channel reimplementation inside whichever module is doing the sending.
 */
interface CustomerDirectory
{
    public function exists(int $customerId): bool;

    /**
     * Display name for a calendar entry or a message greeting, or null if the customer is
     * not in the current tenant.
     */
    public function nameOf(int $customerId): ?string;

    /**
     * May the business send this customer a transactional message on this channel?
     *
     * False for an unknown customer, so a caller that fails to check existence separately
     * still cannot send to one.
     */
    public function mayContact(int $customerId, CommunicationChannel $channel): bool;

    /**
     * May the business send this customer marketing on this channel?
     *
     * Always at least as strict as mayContact.
     */
    public function mayMarketTo(int $customerId, CommunicationChannel $channel): bool;
}
