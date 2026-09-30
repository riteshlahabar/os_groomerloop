<?php

namespace Modules\Crm\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Crm\Contracts\CustomerMergeParticipant;
use Modules\Crm\Models\Customer;
use Modules\Crm\Services\MergeParticipants;

/**
 * Combine two customer records into one (spec §8 "duplicate detection and merge").
 *
 * The riskiest operation in the CRM, so the rules are conservative throughout:
 *
 *   - The survivor is chosen by the person doing the merge, never guessed.
 *   - Fields are filled in, never overwritten. The survivor keeps everything it has; the
 *     loser only contributes where the survivor is blank. Nobody merging two records
 *     expects the one they kept to change its phone number.
 *   - Consent takes the most restrictive answer of the two, not the survivor's. If either
 *     record says the customer opted out, the merged customer is opted out — invariant #9
 *     means an opt-out cannot be lost to a data-tidying operation.
 *   - Notes are concatenated rather than picked between, because they are the one field
 *     where both records genuinely hold information.
 *   - Tags are unioned.
 *   - The loser is soft-deleted, never destroyed (invariant #4), so a merge done in error
 *     leaves everything still on disk.
 *
 * Related records — pets, appointments, bookings — are moved by the modules that own them,
 * through the CustomerMergeParticipant contract.
 */
final class MergeCustomers
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly MergeParticipants $participants,
    ) {}

    public function execute(Customer $survivor, Customer $loser): Customer
    {
        if ($survivor->is($loser)) {
            throw ValidationException::withMessages([
                'customer' => 'A customer cannot be merged into itself.',
            ]);
        }

        return DB::transaction(function () use ($survivor, $loser): Customer {
            $moved = $this->transferRelatedRecords($survivor, $loser);

            $this->fillBlanksFrom($survivor, $loser);
            $this->mergeNotes($survivor, $loser);
            $this->mergeConsent($survivor, $loser);

            $survivor->save();

            $this->unionTags($survivor, $loser);

            // Soft delete: invariant #4, and the only reason a merge is recoverable.
            $loser->delete();

            $this->audit->record('customer.merged', $survivor, [
                'survivor_id' => $survivor->getKey(),
                'merged_id' => $loser->getKey(),
                'merged_name' => $loser->fullName(),
                'merged_email' => $loser->email,
                'records_moved' => $moved,
            ]);

            return $survivor->refresh();
        });
    }

    /**
     * @return array<string, int>
     */
    private function transferRelatedRecords(Customer $survivor, Customer $loser): array
    {
        $moved = [];

        foreach ($this->participants->all() as $participant) {
            /** @var CustomerMergeParticipant $participant */
            $moved[$participant->describes()] = $participant->transfer(
                $loser->getKey(),
                $survivor->getKey(),
            );
        }

        return $moved;
    }

    /**
     * The loser fills gaps only. Anything the survivor already has, it keeps.
     */
    private function fillBlanksFrom(Customer $survivor, Customer $loser): void
    {
        $fields = [
            'last_name', 'email', 'phone',
            'address_line_1', 'address_line_2', 'city', 'state', 'postal_code',
            'source',
        ];

        foreach ($fields as $field) {
            if (blank($survivor->getAttribute($field)) && filled($loser->getAttribute($field))) {
                $survivor->setAttribute($field, $loser->getAttribute($field));
            }
        }
    }

    private function mergeNotes(Customer $survivor, Customer $loser): void
    {
        if (blank($loser->notes)) {
            return;
        }

        if (blank($survivor->notes)) {
            $survivor->notes = $loser->notes;

            return;
        }

        // Kept apart and labelled, rather than run together, so whoever reads it later can
        // see that two records were combined and does not misread one note as context for
        // the other.
        $survivor->notes = $survivor->notes
            ."\n\n--- Merged from duplicate record ---\n"
            .$loser->notes;
    }

    /**
     * The most restrictive of the two answers wins, on every channel.
     */
    private function mergeConsent(Customer $survivor, Customer $loser): void
    {
        $survivor->forceFill([
            'accepts_email' => $survivor->accepts_email && $loser->accepts_email,
            'accepts_sms' => $survivor->accepts_sms && $loser->accepts_sms,
            'accepts_push' => $survivor->accepts_push && $loser->accepts_push,
            'accepts_marketing' => $survivor->accepts_marketing && $loser->accepts_marketing,

            // Either opt-out carries, and the earlier of the two timestamps is kept so the
            // record shows when the customer first asked to be left alone.
            'opted_out_at' => $this->earliest($survivor->opted_out_at, $loser->opted_out_at),
        ]);
    }

    private function earliest(mixed $a, mixed $b): mixed
    {
        if ($a === null) {
            return $b;
        }

        if ($b === null) {
            return $a;
        }

        return $a->lessThan($b) ? $a : $b;
    }

    private function unionTags(Customer $survivor, Customer $loser): void
    {
        $tagIds = $loser->tags()->pluck('customer_tags.id')->all();

        if ($tagIds === []) {
            return;
        }

        $survivor->tags()->syncWithoutDetaching(
            array_fill_keys($tagIds, ['tenant_id' => $survivor->tenant_id])
        );
    }
}
