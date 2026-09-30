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

                $this->syncFeatures($plan, $definition['features']);
            });
        }
    }

    /**
     * Replace this plan's slice of the matrix with the definition above.
     *
     * Rows are deleted rather than left behind, because a feature removed from a tier must
     * actually stop being granted — an orphan row would keep entitling it forever.
     *
     * @param  array<string, FeatureGrade>  $features
     */
    private function syncFeatures(Plan $plan, array $features): void
    {
        $plan->features()->whereNotIn('feature', array_keys($features))->delete();

        foreach ($features as $feature => $grade) {
            $plan->features()->updateOrCreate(
                ['feature' => $feature],
                ['grade' => $grade->value],
            );
        }
    }

    /**
     * Spec §2 pricing and the spec §25 matrix.
     *
     * A cell the matrix marks "--" is simply absent from the features array; an unlabelled
     * tick is FeatureGrade::Standard; a labelled cell carries its label.
     *
     * @return list<array{key: string, name: string, tagline: string, price_cents: int, is_default: bool, features: array<string, FeatureGrade>}>
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
                'features' => $this->core() + [
                    Feature::Automation->value => FeatureGrade::Basic,
                    Feature::BusinessInsights->value => FeatureGrade::Basic,
                ],
            ],
            [
                'key' => 'business',
                'name' => 'Business',
                'tagline' => 'Run your business',
                'price_cents' => 14_900,
                'is_default' => false,
                'features' => $this->core() + [
                    Feature::GoogleBusinessOptimization->value => FeatureGrade::Standard,
                    Feature::ReviewSupport->value => FeatureGrade::Standard,
                    Feature::SocialManagement->value => FeatureGrade::Standard,
                    Feature::CustomerFollowUp->value => FeatureGrade::Standard,
                    Feature::CustomerRetention->value => FeatureGrade::Basic,
                    Feature::Automation->value => FeatureGrade::Basic,
                    Feature::BusinessInsights->value => FeatureGrade::Standard,
                    Feature::GrowthReporting->value => FeatureGrade::Basic,
                ],
            ],
            [
                'key' => 'growth',
                'name' => 'Growth',
                'tagline' => 'Grow your business — most popular',
                'price_cents' => 24_900,
                'is_default' => false,
                'features' => $this->core() + [
                    Feature::GoogleBusinessOptimization->value => FeatureGrade::Standard,
                    Feature::ReviewSupport->value => FeatureGrade::Standard,
                    Feature::SocialManagement->value => FeatureGrade::Standard,
                    Feature::CustomerFollowUp->value => FeatureGrade::Standard,
                    Feature::CustomerRetention->value => FeatureGrade::Strategy,
                    Feature::LocalSeo->value => FeatureGrade::Standard,
                    Feature::ContentMarketing->value => FeatureGrade::Standard,
                    Feature::BookingConversionOptimization->value => FeatureGrade::Standard,
                    Feature::AiBusinessTools->value => FeatureGrade::Standard,
                    Feature::Automation->value => FeatureGrade::Standard,
                    Feature::BusinessInsights->value => FeatureGrade::Advanced,
                    Feature::GrowthReporting->value => FeatureGrade::Standard,
                ],
            ],
            [
                'key' => 'growth_partner',
                'name' => 'Growth Partner',
                'tagline' => 'Premium, high-touch digital growth partner',
                'price_cents' => 39_900,
                'is_default' => false,
                'features' => $this->core() + [
                    Feature::GoogleBusinessOptimization->value => FeatureGrade::Advanced,
                    Feature::ReviewSupport->value => FeatureGrade::Managed,
                    Feature::SocialManagement->value => FeatureGrade::Managed,
                    Feature::CustomerFollowUp->value => FeatureGrade::Advanced,
                    Feature::CustomerRetention->value => FeatureGrade::Managed,
                    Feature::LocalSeo->value => FeatureGrade::Advanced,
                    Feature::ContentMarketing->value => FeatureGrade::Managed,
                    Feature::BookingConversionOptimization->value => FeatureGrade::Standard,
                    Feature::AiBusinessTools->value => FeatureGrade::Standard,
                    Feature::AiVoiceAgent->value => FeatureGrade::Standard,
                    Feature::Automation->value => FeatureGrade::Advanced,
                    Feature::BusinessInsights->value => FeatureGrade::Advanced,
                    Feature::GrowthReporting->value => FeatureGrade::Advanced,
                    Feature::MonthlyGrowthReview->value => FeatureGrade::Standard,
                    Feature::DedicatedGrowthSupport->value => FeatureGrade::Standard,
                ],
            ],
        ];
    }

    /**
     * The six rows spec §25 ticks for every plan.
     *
     * These are the operating system itself. No plan may omit them — a business that cannot
     * reach its own customers, pets or appointments is not a tenant of this product.
     *
     * @return array<string, FeatureGrade>
     */
    private function core(): array
    {
        return [
            Feature::CoreOs->value => FeatureGrade::Standard,
            Feature::OnlineBooking->value => FeatureGrade::Standard,
            Feature::CrmPets->value => FeatureGrade::Standard,
            Feature::AppointmentsCalendar->value => FeatureGrade::Standard,
            Feature::MobileAccess->value => FeatureGrade::Standard,
            Feature::BasicWebsite->value => FeatureGrade::Standard,
        ];
    }
}
