<?php

namespace Modules\Entitlements\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Entitlements\Contracts\Entitlements;
use Modules\Entitlements\Domain\Feature;
use Modules\Entitlements\Domain\FeatureGrade;
use Modules\Entitlements\Services\PlanEntitlements;

/**
 * What the signed-in business is entitled to (spec §25, invariant #3).
 *
 * One request the SPA makes after login and caches, so navigation, upsell prompts and locked
 * states can all be driven from a single answer rather than the frontend re-deriving the §25
 * matrix or — worse — branching on a plan name.
 *
 * Every feature is listed, including the ones this plan does not have. A feature the client
 * cannot see at all is a feature it will render a broken empty state for; a feature it knows
 * is locked is an upgrade prompt.
 */
final class EntitlementController
{
    public function __invoke(Entitlements $entitlements): JsonResponse
    {
        $grants = $entitlements->all();

        // The concrete service exposes the resolved plan, which the contract deliberately does
        // not — no other module is allowed to care which plan it is, but this endpoint exists
        // precisely to tell the client's billing screen.
        $plan = $entitlements instanceof PlanEntitlements ? $entitlements->plan() : null;

        return response()->json([
            'data' => [
                'plan' => $plan === null ? null : [
                    'key' => $plan->key,
                    'name' => $plan->name,
                    'price_cents' => $plan->price_cents,
                ],
                'features' => array_map(
                    static fn (Feature $feature): array => [
                        'key' => $feature->value,
                        'label' => $feature->label(),
                        'included' => isset($grants[$feature->value]),
                        'grade' => ($grants[$feature->value] ?? null)?->value,
                    ],
                    Feature::all(),
                ),
                'grades' => FeatureGrade::values(),
            ],
        ]);
    }
}
