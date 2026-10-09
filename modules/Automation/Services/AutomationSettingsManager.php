<?php

namespace Modules\Automation\Services;

use Modules\Automation\Domain\AutomationKey;
use Modules\Automation\Models\AutomationSetting;
use Modules\Entitlements\Contracts\Entitlements;
use Modules\Entitlements\Domain\Feature;
use Modules\Entitlements\Domain\FeatureGrade;

/**
 * Reads and writes the five {@see AutomationKey} rows (spec §18), and is the one place the
 * `business_insights`-style grading on `Feature::Automation` is enforced (invariant #3) — a
 * metric class checks its own grade in Insights because each metric is a separate object; here
 * there is one setting type, so one method is simpler than five identical checks.
 */
final class AutomationSettingsManager
{
    public function __construct(private readonly Entitlements $entitlements) {}

    /**
     * Every key, including ones the tenant has never touched — those come back disabled with
     * their built-in default delay, never omitted. A screen listing "what automations exist"
     * must not depend on a row already existing for one to appear.
     *
     * @return array<string, array{key: AutomationKey, is_enabled: bool, delay_days: ?int}>
     */
    public function all(): array
    {
        $existing = AutomationSetting::query()->get()->keyBy(fn (AutomationSetting $s): string => $s->automation_key->value);

        $rows = [];

        foreach (AutomationKey::all() as $key) {
            $setting = $existing->get($key->value);

            $rows[$key->value] = [
                'key' => $key,
                'is_enabled' => $setting?->is_enabled ?? false,
                'delay_days' => $setting?->delay_days ?? $key->defaultDelayDays(),
            ];
        }

        return $rows;
    }

    /**
     * Whether this automation should actually fire.
     *
     * Answers false for a plan that no longer includes automation at all, not only for a row the
     * owner switched off. Every firing path funnels through here — the cron sweep's four keys
     * directly, the immediate listener's fifth through {@see AutomationRunner} — so this is the
     * one check that can stop a downgraded business from being automated forever. Without it a
     * tier change that removed the feature would hide the screen and 402 the API while the
     * already-enabled rows kept sending, with no surface left to turn them off from.
     *
     * Invariant #4 is why the rows are left alone rather than disabled: losing a plan feature
     * locks it, never destroys it, and an upgrade brings the owner's own choices straight back.
     */
    public function isEnabled(AutomationKey $key): bool
    {
        if (! $this->entitlements->allows(Feature::Automation)) {
            return false;
        }

        return AutomationSetting::query()
            ->where('automation_key', $key->value)
            ->first()?->is_enabled ?? false;
    }

    public function delayDaysFor(AutomationKey $key): int
    {
        $setting = AutomationSetting::query()->where('automation_key', $key->value)->first();

        return $setting?->delay_days ?? $key->defaultDelayDays() ?? 0;
    }

    /**
     * How many of the five keys this tenant's `automation` grade may have enabled at once — a
     * quantity cap rather than per-key gating, because every key here is equally "automation",
     * unlike Insights' metrics which differ in sophistication. The owner picks which ones matter
     * to their business; the plan only caps how many.
     *
     * The grade is now the granting tier's own name (`D-051`), so only the top two rungs are
     * reachable: §18 is Growth-and-above (`D-042`), which makes `advanced` Growth and
     * `enterprise` Growth Partner. Those two keep exactly the caps they had under the old
     * five-word vocabulary — 3 of 5, then all 5. `basic` and `standard` cannot occur while no
     * tier below Growth grants automation; they are mapped rather than thrown on, so granting
     * it to a lower tier one day is a seeder edit and not a crash.
     */
    public function maxEnabled(): int
    {
        $grade = $this->entitlements->gradeOf(Feature::Automation);

        return match ($grade) {
            null => 0,
            FeatureGrade::Basic => 1,
            FeatureGrade::Standard => 2,
            FeatureGrade::Advanced => 3,
            FeatureGrade::Enterprise => count(AutomationKey::all()),
        };
    }

    /**
     * @throws AutomationGradeLimitExceeded when enabling this key would exceed {@see self::maxEnabled()}
     */
    public function update(AutomationKey $key, bool $isEnabled, ?int $delayDays): AutomationSetting
    {
        if ($isEnabled) {
            // `pluck()` hydrates full models rather than raw column values here, because
            // `automation_key` has an enum cast — so this is a collection of AutomationKey
            // instances, not strings, and must be compared by ->value rather than loose equality.
            $currentlyEnabled = AutomationSetting::query()->where('is_enabled', true)->pluck('automation_key');
            $alreadyOn = $currentlyEnabled->contains(fn (AutomationKey $k): bool => $k === $key);

            if (! $alreadyOn && $currentlyEnabled->count() >= $this->maxEnabled()) {
                throw new AutomationGradeLimitExceeded($this->maxEnabled());
            }
        }

        $resolvedDelay = $key->needsDelay() ? ($delayDays ?? $key->defaultDelayDays()) : null;

        $setting = AutomationSetting::query()->where('automation_key', $key->value)->first()
            ?? new AutomationSetting(['automation_key' => $key->value]);

        $setting->fill([
            'is_enabled' => $isEnabled,
            'delay_days' => $resolvedDelay,
        ])->save();

        return $setting;
    }
}
