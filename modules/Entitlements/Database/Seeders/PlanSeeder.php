<?php

namespace Modules\Entitlements\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Entitlements\Domain\Feature;
use Modules\Entitlements\Domain\FeatureGrade;
use Modules\Entitlements\Models\Plan;

/**
 * The spec §2 price list and the spec §25 entitlement matrix, written down once.
 *
 * THIS FILE IS THE ONLY PLACE IN THE CODEBASE ALLOWED TO NAME A PLAN OR A PRICE. That is
 * invariant #3, and PlanLiteralGuardTest fails the build if a plan name or a plan price
 * appears anywhere else. Re-packaging the product — moving a feature between tiers, renaming
 * a plan, changing a price — is an edit to this file and nothing else.
 *
 * **Shape changed 2026-10-09 (`D-051`).** A plan used to grade each of its features
 * individually, from a five-word vocabulary. It now declares **one grade**, and every feature
 * it grants carries it: Starter `basic`, Business `standard`, Growth `advanced`, Growth Partner
 * `enterprise`. So `features` is a plain list of keys, and the grade column is a restatement of
 * which tier granted the row. Which features sit in which tier did not change with that edit —
 * the counts are still 7 / 13 / 18 / 21 of the 21 §25 keys.
 *
 * Idempotent: re-running it updates the catalog in place rather than duplicating it, so it is
 * safe to run against an existing database after a packaging change.
 */
final class PlanSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalog() as $sortOrder => $definition) {
            DB::transaction(function () use ($definition, $sortOrder): void {
                $plan = Plan::query()->updateOrCreate(
                    ['key' => $definition['key']],
                    [
                        'name' => $definition['name'],
                        'tagline' => $definition['tagline'],
                        'price_cents' => $definition['price_cents'],
                        'currency' => 'USD',
                        'billing_interval' => 'month',
                        'is_default' => $definition['is_default'],
                        'is_active' => true,
                        'sort_order' => $sortOrder,
                    ]
                );

                $this->syncFeatures($plan, $definition['features'], $definition['grade']);
            });
        }
    }

    /**
     * Replace this plan's slice of the matrix with the definition above, every row at the
     * plan's own grade.
     *
     * Rows are deleted rather than left behind, because a feature removed from a tier must
     * actually stop being granted — an orphan row would keep entitling it forever. The same
     * applies to a grade that is no longer the plan's: `updateOrCreate` rewrites it, which is
     * how a re-run migrates a database off the old five-word vocabulary with no migration of
     * its own (the column is a plain varchar).
     *
     * @param  list<string>  $features
     */
    private function syncFeatures(Plan $plan, array $features, FeatureGrade $grade): void
    {
        $plan->features()->whereNotIn('feature', $features)->delete();

        foreach ($features as $feature) {
            $plan->features()->updateOrCreate(
                ['feature' => $feature],
                ['grade' => $grade->value],
            );
        }
    }

    /**
     * Spec §2 pricing and the spec §25 matrix.
     *
     * A cell the matrix marks "--" is simply absent from the features list. Every cell a plan
     * does tick is granted at that plan's own grade — see the class docblock and `D-051`.
     *
     * @return list<array{key: string, name: string, tagline: string, price_cents: int, is_default: bool, grade: FeatureGrade, features: list<string>}>
     */
    private function catalog(): array
    {
        return [
            [
                'key' => 'starter',
                'name' => 'Starter',
                'tagline' => 'Get started',
                'price_cents' => 7_900,

                // The floor every business lands on before subscribing, and the tier a
                // cancelled subscription falls back to (invariant #4: they keep their data).
                'is_default' => true,

                'grade' => FeatureGrade::Basic,

                // 7 of the 21 §25 keys.
                //
                // Automation is deliberately absent, 2026-10-06: the price list sells it from
                // Growth up only ("Automation Support" / "Business Automation"), so Starter and
                // Business both go without. Granting it here had let a $79 business run two of
                // the five §18 automations for free.
                //
                // BusinessInsights stays: Starter's five basic metrics plus eight locked rows
                // linking to Billing is a better upsell than an absent screen, and the price
                // list's "Basic Business Dashboard" fairly covers it.
                'features' => [
                    ...$this->core(),
                    Feature::BusinessInsights->value,
                ],
            ],
            [
                'key' => 'business',
                'name' => 'Business',
                'tagline' => 'Run your business',
                'price_cents' => 14_900,
                'is_default' => false,

                'grade' => FeatureGrade::Standard,

                // 13 of 21. No Automation row, 2026-10-06 — see the Starter block above. §18
                // automation is a Growth-tier-and-up capability, so this is the highest plan
                // without it.
                'features' => [
                    ...$this->core(),
                    Feature::GoogleBusinessOptimization->value,
                    Feature::ReviewSupport->value,
                    Feature::SocialManagement->value,
                    Feature::CustomerFollowUp->value,
                    Feature::CustomerRetention->value,
                    Feature::BusinessInsights->value,
                    Feature::GrowthReporting->value,
                ],
            ],
            [
                'key' => 'growth',
                'name' => 'Growth',
                'tagline' => 'Grow your business — most popular',
                'price_cents' => 24_900,
                'is_default' => false,

                'grade' => FeatureGrade::Advanced,

                // 18 of 21, and the lowest tier holding Automation — which is what makes
                // `advanced` the grade §18's cap is read against (3 of the 5 automations).
                'features' => [
                    ...$this->core(),
                    Feature::GoogleBusinessOptimization->value,
                    Feature::ReviewSupport->value,
                    Feature::SocialManagement->value,
                    Feature::CustomerFollowUp->value,
                    Feature::CustomerRetention->value,
                    Feature::LocalSeo->value,
                    Feature::ContentMarketing->value,
                    Feature::BookingConversionOptimization->value,
                    Feature::AiBusinessTools->value,
                    Feature::Automation->value,
                    Feature::BusinessInsights->value,
                    Feature::GrowthReporting->value,
                ],
            ],
            [
                'key' => 'growth_partner',
                'name' => 'Growth Partner',
                'tagline' => 'Premium, high-touch digital growth partner',
                'price_cents' => 39_900,
                'is_default' => false,

                'grade' => FeatureGrade::Enterprise,

                // All 21.
                'features' => [
                    ...$this->core(),
                    Feature::GoogleBusinessOptimization->value,
                    Feature::ReviewSupport->value,
                    Feature::SocialManagement->value,
                    Feature::CustomerFollowUp->value,
                    Feature::CustomerRetention->value,
                    Feature::LocalSeo->value,
                    Feature::ContentMarketing->value,
                    Feature::BookingConversionOptimization->value,
                    Feature::AiBusinessTools->value,
                    Feature::AiVoiceAgent->value,
                    Feature::Automation->value,
                    Feature::BusinessInsights->value,
                    Feature::GrowthReporting->value,
                    Feature::MonthlyGrowthReview->value,
                    Feature::DedicatedGrowthSupport->value,
                ],
            ],
        ];
    }

    /**
     * The six rows spec §25 ticks for every plan.
     *
     * These are the operating system itself. No plan may omit them — a business that cannot
     * reach its own customers, pets or appointments is not a tenant of this product. They are
     * graded at each plan's own tier like everything else, so the same key reads `basic` on
     * Starter and `enterprise` on Growth Partner; nothing reads those grades, and a core
     * feature is all-or-nothing by definition.
     *
     * @return list<string>
     */
    private function core(): array
    {
        return [
            Feature::CoreOs->value,
            Feature::OnlineBooking->value,
            Feature::CrmPets->value,
            Feature::AppointmentsCalendar->value,
            Feature::MobileAccess->value,
            Feature::BasicWebsite->value,
        ];
    }
}
