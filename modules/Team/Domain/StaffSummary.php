<?php

namespace Modules\Team\Domain;

/**
 * What another module is told about a member of staff (D-007).
 *
 * Readonly, and deliberately not the model. Scheduling needs a name and whether this person can be
 * given work; handing it a StaffMember would also hand it the rota, the time-off history and a
 * `save()`, and the first thing that happens then is a calendar bug that edits someone's contract.
 */
final readonly class StaffSummary
{
    public function __construct(
        public int $id,
        public string $displayName,
        public ?string $jobTitle,
        public ?string $bio,
        public bool $isAssignable,
        public bool $isBookableOnline,

        /**
         * Null when this staff member has no login. Not the same as "no user exists" — a Saturday
         * junior is a real, bookable groomer with no account.
         */
        public ?int $userId,

        /**
         * False when no shifts are recorded. Carried so a caller can tell "not at work right now"
         * from "has never been given a rota", which are different problems for a salon to fix.
         */
        public bool $hasWorkingHours,
    ) {}
}
