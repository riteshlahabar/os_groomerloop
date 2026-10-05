<?php

namespace Modules\Insights\Domain\Metrics;

use DateTimeImmutable;
use Modules\Entitlements\Domain\FeatureGrade;
use Modules\Insights\Contracts\MetricDefinition;
use Modules\Insights\Domain\MetricResult;
use Modules\Scheduling\Contracts\AppointmentMetrics;
use Modules\Team\Contracts\StaffDirectory;

/**
 * Spec §16 "Staff utilization" — booked minutes ÷ available minutes (from each groomer's own
 * rota, minus time off) per staff member, over the range.
 */
final readonly class StaffUtilizationMetric implements MetricDefinition
{
    public function __construct(
        private AppointmentMetrics $appointments,
        private StaffDirectory $staff,
    ) {}

    public function key(): string
    {
        return 'staff_utilization';
    }

    public function label(): string
    {
        return 'Staff utilization';
    }

    public function minimumGrade(): FeatureGrade
    {
        return FeatureGrade::Advanced;
    }

    public function compute(DateTimeImmutable $from, DateTimeImmutable $to): MetricResult
    {
        $availableByStaff = $this->staff->availableMinutesBetween($from, $to);

        if ($availableByStaff === []) {
            return MetricResult::insufficientData();
        }

        $bookedByStaff = $this->appointments->occupiedMinutesByStaff($from, $to);
        $names = $this->staff->namesOf(array_keys($availableByStaff));

        $rows = [];

        foreach ($availableByStaff as $staffMemberId => $availableMinutes) {
            $bookedMinutes = $bookedByStaff[$staffMemberId] ?? 0;

            $rows[] = [
                'staff_member_id' => $staffMemberId,
                'staff_member_name' => $names[$staffMemberId] ?? 'Unknown',
                'booked_minutes' => $bookedMinutes,
                'available_minutes' => $availableMinutes,
                // Null rather than 0 when there is no rota to divide by — a groomer with no
                // working hours has an undefined utilization, not a zero one.
                'utilization_percent' => $availableMinutes > 0
                    ? (int) round(($bookedMinutes / $availableMinutes) * 100)
                    : null,
            ];
        }

        return MetricResult::ok(['staff' => $rows]);
    }
}
