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
     * Does this business have a customer book at all?
     *
     * Added for the §7 `customers_and_pets` onboarding step (`D-015`), which is only satisfied
     * when both halves exist. Pets owns that verifier and must not count Crm's rows itself, so
     * the question is asked here rather than through a query into another module's table.
     *
     * Deliberately a boolean rather than a count: nothing in the product needs to know how many
     * customers there are through this contract, and returning a number would invite a caller
     * to paginate or report on it from outside Crm.
     */
    public function hasAny(): bool;

    /**
     * Display name for a calendar entry or a message greeting, or null if the customer is
     * not in the current tenant.
     */
    public function nameOf(int $customerId): ?string;

    /**
     * Names for many customers at once, keyed by customer id, with null for any the current
     * tenant cannot see.
     *
     * Exists because a list of pets (§9) shows whose pet each one is, and resolving that one row
     * at a time is a query per row — an N+1 that `Model::shouldBeStrict()` will not catch,
     * because it is not a relationship. An implementation is expected to warm whatever cache
     * `nameOf()` reads, so a caller can prime a page and let per-row rendering hit memory.
     *
     * @param  list<int>  $customerIds
     * @return array<int, string|null>
     */
    public function namesOf(array $customerIds): array;

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
