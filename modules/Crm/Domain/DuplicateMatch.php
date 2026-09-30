<?php

namespace Modules\Crm\Domain;

use Modules\Crm\Models\Customer;

/**
 * One suspected duplicate, with the reason it is suspected (spec §8).
 *
 * The reason is carried rather than just a score, because a human has to approve the merge
 * and "same email address" justifies itself in a way that "confidence 0.87" never does.
 */
final readonly class DuplicateMatch
{
    public const REASON_EMAIL = 'email';

    public const REASON_PHONE = 'phone';

    public const REASON_NAME_AND_PARTIAL_CONTACT = 'name_and_partial_contact';

    public function __construct(
        public Customer $customer,
        public string $reason,
        public bool $confident,
    ) {}

    public function explain(): string
    {
        return match ($this->reason) {
            self::REASON_EMAIL => 'Same email address',
            self::REASON_PHONE => 'Same phone number',
            self::REASON_NAME_AND_PARTIAL_CONTACT => 'Same name, and one matching contact detail',
            default => 'Possible duplicate',
        };
    }
}
