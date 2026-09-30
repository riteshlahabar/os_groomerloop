<?php

namespace Modules\Platform\Domain;

/**
 * The outcome of a system health check.
 *
 * Lives in Domain/ because it is pure data with no framework or database dependency —
 * the same convention every later module follows for its enums and value objects.
 */
final readonly class HealthReport
{
    /**
     * @param  array<string, bool>  $checks  Check name => whether it passed.
     */
    public function __construct(
        public array $checks,
    ) {}

    public function isHealthy(): bool
    {
        return ! in_array(false, $this->checks, strict: true);
    }

    public function status(): string
    {
        return $this->isHealthy() ? 'ok' : 'degraded';
    }

    /**
     * @return array<string, string>
     */
    public function checkResults(): array
    {
        return array_map(
            static fn (bool $passed): string => $passed ? 'ok' : 'failing',
            $this->checks
        );
    }
}
