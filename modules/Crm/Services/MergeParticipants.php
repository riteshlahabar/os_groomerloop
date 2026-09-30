<?php

namespace Modules\Crm\Services;

use Modules\Crm\Contracts\CustomerMergeParticipant;

/**
 * Where modules hang the handlers that move their records during a customer merge.
 *
 * Same shape as Onboarding's step-verifier registry, and for the same reason: the list of
 * things that reference a customer grows with every phase, and Crm should not need editing
 * each time.
 */
final class MergeParticipants
{
    /** @var list<CustomerMergeParticipant> */
    private array $participants = [];

    public function register(CustomerMergeParticipant $participant): void
    {
        $this->participants[] = $participant;
    }

    /**
     * @return list<CustomerMergeParticipant>
     */
    public function all(): array
    {
        return $this->participants;
    }
}
