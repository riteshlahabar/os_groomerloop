<?php

namespace Modules\Insights\Domain;

use Modules\Insights\Contracts\MetricDefinition;

/**
 * What a {@see MetricDefinition} hands back — invariant #7 made a
 * type. `insufficientData()` carries no payload on purpose: a caller cannot accidentally render
 * a zero or an empty array as if it were a real answer, because there is no `data` to reach for
 * on that branch.
 */
final readonly class MetricResult
{
    private function __construct(
        public string $status,
        public array $data,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function ok(array $data): self
    {
        return new self('ok', $data);
    }

    public static function insufficientData(): self
    {
        return new self('insufficient_data', []);
    }
}
