<?php

namespace Modules\Insights\Domain\Metrics;

use DateTimeImmutable;
use Modules\Crm\Contracts\CustomerMetrics;
use Modules\Entitlements\Domain\FeatureGrade;
use Modules\Insights\Contracts\MetricDefinition;
use Modules\Insights\Domain\MetricResult;

/**
 * Spec §16 "New customers" — customer records created in the range.
 */
final readonly class NewCustomersMetric implements MetricDefinition
{
    public function __construct(private CustomerMetrics $customers) {}

    public function key(): string
    {
        return 'new_customers';
    }

    public function label(): string
    {
        return 'New customers';
    }

    public function minimumGrade(): FeatureGrade
    {
        return FeatureGrade::Basic;
    }

    public function compute(DateTimeImmutable $from, DateTimeImmutable $to): MetricResult
    {
        return MetricResult::ok(['count' => $this->customers->newCustomerCount($from, $to)]);
    }
}
