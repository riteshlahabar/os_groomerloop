<?php

namespace Modules\Pets\Services;

use Modules\Crm\Contracts\CustomerMergeParticipant;
use Modules\Pets\Models\Pet;

/**
 * Moves pets to the surviving customer when two customer records are merged (spec §8, §9).
 *
 * The extension point Crm built and tested against a fake in Phase 5; this is the first real
 * implementation of it. Crm does not know pets exist — it asks every registered participant to
 * move its own records — which is why merging never needed editing when this module arrived.
 */
final class PetMergeParticipant implements CustomerMergeParticipant
{
    public function describes(): string
    {
        return 'pets';
    }

    /**
     * Idempotent by construction: after the first run no pet matches the old owner, so a merge
     * retried after a failure moves nothing and reports zero rather than double-counting.
     *
     * Deliberately includes archived and deceased pets. They carry the grooming history the
     * surviving customer's record is supposed to keep, and leaving them pointing at a
     * soft-deleted customer is how history quietly detaches from the family it belongs to.
     */
    public function transfer(int $fromCustomerId, int $toCustomerId): int
    {
        return Pet::query()
            ->forCustomer($fromCustomerId)
            ->update(['customer_id' => $toCustomerId]);
    }
}
