<?php

namespace Modules\Scheduling\Domain;

use DateTimeImmutable;

/**
 * What the `AppointmentScheduler` contract hands other modules — never the Eloquent model, the
 * same boundary `ServiceSummary`/`StaffSummary` already draw.
 */
final readonly class AppointmentSummary
{
    public function __construct(
        public int $id,
        public int $customerId,
        public int $petId,
        public int $serviceId,
        public ?int $staffMemberId,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public AppointmentStatus $status,
        public ?string $customerNotes,
    ) {}
}
