<?php

namespace Modules\Entitlements\Domain;

/**
 * How much of a feature a plan includes (spec §25).
 *
 * The §25 matrix is not a grid of ticks. Several rows are graded — Automation is "Basic" on
 * Starter and Business, plain on Growth and "Advanced" on Growth Partner; Customer retention
 * runs Basic → Strategy → Managed. Modelling entitlement as a boolean would flatten that and
 * force the difference to be re-invented, plan name in hand, wherever it mattered.
 *
 * `Standard` is the unlabelled tick in the matrix: included, with no qualifier.
 */
enum FeatureGrade: string
{
    case Basic = 'basic';
    case Standard = 'standard';
    case Strategy = 'strategy';
    case Advanced = 'advanced';
    case Managed = 'managed';

    /**
     * Rank used by Entitlements::atLeast(), so a route can demand "automation, at least
     * advanced" without naming a plan.
     *
     * Strategy and Advanced deliberately share a rank. They are the same tier of the ladder
     * expressed in different words by the spec — §25 uses "Strategy" for the Growth cell of
     * Customer retention and "Advanced" for the Growth-and-above cell of other rows. Ranking
     * one above the other would invent a distinction the spec does not make.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Basic => 10,
            self::Standard => 20,
            self::Strategy => 30,
            self::Advanced => 30,
            self::Managed => 40,
        };
    }

    public function atLeast(self $minimum): bool
    {
        return $this->rank() >= $minimum->rank();
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $g): string => $g->value, self::cases());
    }
}
