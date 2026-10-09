<?php

namespace Modules\Entitlements\Domain;

/**
 * Which tier of the price list granted a feature (spec §2, §25; `D-051`).
 *
 * One grade per plan, and every feature a plan grants carries that plan's grade:
 * Starter `basic`, Business `standard`, Growth `advanced`, Growth Partner `enterprise`.
 * The owner chose these four words on 2026-10-09 and had the others removed outright, so the
 * vocabulary is now exactly as long as the price list.
 *
 * **This is a deliberate divergence from §25's own wording** (`D-051`). The spec labels
 * individual cells — Customer retention reads Basic → Strategy → Managed, several Growth
 * Partner rows read "Managed" — which made grade a per-feature judgement five words wide.
 * It is now a tier name, so a grade answers "which plan is this from", not "how much of this
 * feature do they get". The two places that actually read a grade only ever compared tiers
 * anyway (§18's automation cap and §16's metric ladder), which is why collapsing it costs
 * nothing today. What it does cost: "Business gets a lighter version of X" can no longer be
 * said in a grade — it needs its own `Feature` key.
 */
enum FeatureGrade: string
{
    case Basic = 'basic';
    case Standard = 'standard';
    case Advanced = 'advanced';
    case Enterprise = 'enterprise';

    /**
     * Rank used by Entitlements::atLeast(), so a route or a metric can demand "automation, at
     * least advanced" without naming a plan.
     *
     * Strictly increasing, one step per tier — unlike the old five-grade ladder, where
     * Strategy and Advanced shared a rank because the spec used two words for one tier.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Basic => 10,
            self::Standard => 20,
            self::Advanced => 30,
            self::Enterprise => 40,
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
