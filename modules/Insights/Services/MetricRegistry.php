<?php

namespace Modules\Insights\Services;

use Modules\Insights\Contracts\MetricDefinition;
use Modules\Insights\InsightsServiceProvider;

/**
 * Every spec §16 metric this build knows about, in display order. A single process-wide
 * singleton populated once in {@see InsightsServiceProvider}, the same shape
 * Onboarding's `StepVerifiers` uses for its own registry.
 */
final class MetricRegistry
{
    /**
     * @var list<MetricDefinition>
     */
    private array $metrics = [];

    public function register(MetricDefinition $metric): void
    {
        $this->metrics[] = $metric;
    }

    /**
     * @return list<MetricDefinition>
     */
    public function all(): array
    {
        return $this->metrics;
    }
}
