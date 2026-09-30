<?php

namespace Modules\Crm\Contracts;

/**
 * Lets a module move its own records when two customers are merged (spec §8).
 *
 * Merging is the one CRM operation that reaches across the whole product: a customer owns
 * pets (§9), appointments (§11), bookings (§12), notifications (§13) and review requests
 * (§20), and every one of those has to follow the survivor. Crm must not reach into those
 * tables itself (D-007), and it must not be the place that has to be edited every time a
 * new module starts referencing a customer.
 *
 * So each module registers a participant and moves its own rows. A module that forgets is
 * the failure mode worth worrying about, because its records would be left pointing at a
 * soft-deleted customer — which is exactly why the loser is never hard-deleted.
 */
interface CustomerMergeParticipant
{
    /**
     * Human-readable name of what is being moved, for the audit record: "pets",
     * "appointments". Shown to whoever approved the merge.
     */
    public function describes(): string;

    /**
     * Repoint this module's records from the merged-away customer to the survivor.
     *
     * Called inside a transaction and inside the tenant's context, so ordinary scoped
     * queries are correct. Must be idempotent: a merge retried after a failure must not
     * double-count anything.
     *
     * @return int how many records were moved, for the audit trail
     */
    public function transfer(int $fromCustomerId, int $toCustomerId): int;
}
