<?php

namespace Modules\Insights\Contracts;

use DateTimeImmutable;
use Modules\Entitlements\Domain\FeatureGrade;
use Modules\Insights\Domain\MetricResult;

/**
 * One row of the spec §16 dashboard, and the subject of CI guard 5 (D-007): "every metric class
 * implements MetricDefinition." Invariant #7 — "metrics are defined or absent" — is enforced by
 * this interface's shape rather than by convention: a metric either returns real, computed data
 * through {@see MetricResult::ok()} or says plainly that it cannot through
 * {@see MetricResult::insufficientData()}. There is no third way to return a number, so a metric
 * class cannot quietly fabricate one — every implementation is read against this file in review.
 */
interface MetricDefinition
{
    /**
     * Stable identifier, used as the JSON key and never shown to a user.
     */
    public function key(): string;

    public function label(): string;

    /**
     * The §25 `business_insights` grade a plan needs before this metric is computed at all.
     * Checked by the report builder against the tenant's entitlement before `compute()` is ever
     * called, so a metric class never has to ask "am I allowed to run?" itself.
     */
    public function minimumGrade(): FeatureGrade;

    /**
     * `$from` inclusive, `$to` exclusive — the same half-open window every contract this module
     * reads from already uses.
     */
    public function compute(DateTimeImmutable $from, DateTimeImmutable $to): MetricResult;
}
