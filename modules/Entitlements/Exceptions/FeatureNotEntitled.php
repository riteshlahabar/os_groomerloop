<?php

namespace Modules\Entitlements\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Entitlements\Domain\Feature;
use Modules\Entitlements\Domain\FeatureGrade;
use Symfony\Component\HttpFoundation\Response;

/**
 * The business's plan does not include what it just asked for (spec §35: plan restrictions
 * enforced server-side).
 *
 * Answered 402 rather than 403, and the distinction is load-bearing for the SPA. 403 means
 * "your role does not allow this" — the answer is to ask an owner. 402 means "your plan does
 * not include this" — the answer is to upgrade. Collapsing both into 403 would force the
 * frontend to guess which message to show, and guessing wrong tells a groomer to buy
 * something they already have.
 *
 * The body names the feature and the grade required so the upgrade prompt can be specific
 * without the frontend hard-coding the §25 matrix. It deliberately does not name the plan
 * that would satisfy it — that is packaging, it lives in the plan catalog, and the SPA reads
 * it from GET /api/v1/plans.
 *
 * **Two refusals wear this one status.** `reason` separates them, because they are opposite
 * problems with opposite fixes:
 *
 *   - `feature_not_in_plan` — real packaging. Upgrade.
 *   - `no_plan` — the business resolves to no plan at all: no `plan_id` and no plan flagged
 *     `is_default`, which in practice means the plan catalogue was never seeded on that
 *     environment. Nothing is entitled, including the six core features every plan grants, so
 *     "your plan does not include X" is actively misleading — it sends an owner to buy
 *     something Starter already includes. The fix is `PlanSeeder`, not Billing.
 */
final class FeatureNotEntitled extends Exception
{
    public function __construct(
        public readonly Feature $feature,
        public readonly ?FeatureGrade $requiredGrade = null,
        public readonly bool $hasPlan = true,
    ) {
        parent::__construct($hasPlan
            ? sprintf('Your plan does not include %s.', $feature->label())
            : 'This business is not on a plan yet, so nothing is enabled. '
                .'Choose a plan under Billing & Plan, or ask GroomerLoop support if you already have one.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'reason' => $this->hasPlan ? 'feature_not_in_plan' : 'no_plan',
            'feature' => $this->feature->value,
            'feature_label' => $this->feature->label(),
            'required_grade' => $this->requiredGrade?->value,
        ], Response::HTTP_PAYMENT_REQUIRED);
    }
}
