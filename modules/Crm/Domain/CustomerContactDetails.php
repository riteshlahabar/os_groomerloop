<?php

namespace Modules\Crm\Domain;

/**
 * What Notifications (§13) needs to actually address a message — a name, an email, a phone
 * number — never the Customer model (D-007). Consent is a separate question, already answered by
 * `CustomerDirectory::mayContact()`; this DTO only says where to send something once that
 * question has already been asked.
 */
final readonly class CustomerContactDetails
{
    public function __construct(
        public string $fullName,
        public ?string $email,
        public ?string $phone,
    ) {}
}
