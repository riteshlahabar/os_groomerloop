<?php

namespace Modules\Crm\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Crm\Domain\ContactNormaliser;
use Modules\Crm\Domain\DuplicateMatch;
use Modules\Crm\Models\Customer;

/**
 * Duplicate detection for spec §8.
 *
 * Suggests, never acts. Every match found here is offered to a human, because the cost of
 * the two mistakes is wildly asymmetric: a missed duplicate leaves two tidy records that
 * someone merges later, while a wrong automatic merge combines two families' pets, notes
 * and appointment history into one record and there is no clean way back.
 *
 * That asymmetry is why the rules below are deliberately narrow:
 *
 *   - A shared email or phone is a confident match. People do share a household email, so
 *     even this is offered rather than applied.
 *   - A shared name is NOT a match on its own. Two customers called John Smith are
 *     completely ordinary; merging them would be a serious data loss. A name match counts
 *     only when it comes with a matching email or phone as well.
 *   - Nothing is inferred from address alone. A whole apartment building shares one.
 */
final class DuplicateDetector
{
    /**
     * How many suggestions to return for one customer. Beyond a handful, the answer is not
     * "these are duplicates" but "this detection rule is too loose".
     */
    private const LIMIT = 10;

    /**
     * Possible duplicates of a customer that already exists.
     *
     * @return list<DuplicateMatch>
     */
    public function for(Customer $customer): array
    {
        return $this->find(
            email: $customer->email_normalised,
            phone: $customer->phone_normalised,
            firstName: $customer->first_name,
            lastName: $customer->last_name,
            excludeId: $customer->getKey(),
        );
    }

    /**
     * Possible duplicates of a customer about to be created.
     *
     * Used by create and import so the warning arrives before the duplicate exists, rather
     * than after someone notices the book has two of everybody.
     *
     * @return list<DuplicateMatch>
     */
    public function forCandidate(?string $email, ?string $phone, ?string $firstName, ?string $lastName): array
    {
        return $this->find(
            email: ContactNormaliser::email($email),
            phone: ContactNormaliser::phone($phone),
            firstName: $firstName,
            lastName: $lastName,
            excludeId: null,
        );
    }

    /**
     * @return list<DuplicateMatch>
     */
    private function find(
        ?string $email,
        ?string $phone,
        ?string $firstName,
        ?string $lastName,
        ?int $excludeId,
    ): array {
        // Nothing to match on. A record with neither an email nor a phone cannot be
        // confidently tied to anyone, and guessing from the name alone is the mistake this
        // class exists to avoid.
        if ($email === null && $phone === null) {
            return [];
        }

        $candidates = Customer::query()
            ->when($excludeId !== null, fn (Builder $q) => $q->whereKeyNot($excludeId))
            ->where(function (Builder $q) use ($email, $phone): void {
                if ($email !== null) {
                    $q->orWhere('email_normalised', $email);
                }

                if ($phone !== null) {
                    $q->orWhere('phone_normalised', $phone);
                }
            })
            ->limit(self::LIMIT)
            ->get();

        $name = $this->normalisedName($firstName, $lastName);

        return $candidates
            ->map(fn (Customer $candidate): DuplicateMatch => $this->classify($candidate, $email, $phone, $name))
            ->values()
            ->all();
    }

    private function classify(Customer $candidate, ?string $email, ?string $phone, string $name): DuplicateMatch
    {
        $sameEmail = $email !== null && $candidate->email_normalised === $email;
        $samePhone = $phone !== null && $candidate->phone_normalised === $phone;
        $sameName = $name !== '' && $this->normalisedName($candidate->first_name, $candidate->last_name) === $name;

        // Both contact details agree, or one does and so does the name. As close to certain
        // as this gets without a human looking.
        if (($sameEmail && $samePhone) || (($sameEmail || $samePhone) && $sameName)) {
            return new DuplicateMatch(
                customer: $candidate,
                reason: $sameName && ! ($sameEmail && $samePhone)
                    ? DuplicateMatch::REASON_NAME_AND_PARTIAL_CONTACT
                    : ($sameEmail ? DuplicateMatch::REASON_EMAIL : DuplicateMatch::REASON_PHONE),
                confident: true,
            );
        }

        // One contact detail matches but the name does not. Often a household sharing an
        // address — a couple, or a parent and child — so it is surfaced but not asserted.
        return new DuplicateMatch(
            customer: $candidate,
            reason: $sameEmail ? DuplicateMatch::REASON_EMAIL : DuplicateMatch::REASON_PHONE,
            confident: false,
        );
    }

    private function normalisedName(?string $first, ?string $last): string
    {
        return trim(
            ContactNormaliser::name($first).' '.ContactNormaliser::name($last)
        );
    }
}
