<?php

namespace Modules\Entitlements\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Modules\Entitlements\Contracts\Entitlements;
use Modules\Entitlements\Domain\Feature;
use Modules\Entitlements\Domain\FeatureGrade;
use Modules\Entitlements\Exceptions\FeatureNotEntitled;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route on a plan feature: `->middleware('entitlement:ai_voice_agent')`.
 *
 * A minimum grade may follow the feature: `entitlement:automation,advanced` passes only for a
 * plan whose Automation cell is Advanced or better. The route says what it needs; it never
 * says which plan provides it, which is invariant #3 in practice.
 *
 * Sits beside `permission:` deliberately, and the two are not interchangeable. A permission
 * asks "is this person allowed"; an entitlement asks "has this business paid for it". A route
 * that needs both declares both, and the order matters: check the permission first, so a
 * groomer poking at an owner-only endpoint is told 403 rather than being told to upgrade.
 */
final class EnsureEntitlement
{
    public function __construct(private readonly Entitlements $entitlements) {}

    public function handle(Request $request, Closure $next, string $feature, ?string $grade = null): Response
    {
        $case = Feature::tryFrom($feature);

        // A typo in a route definition must not silently grant access, so an unknown feature
        // is a programming error rather than a denial. Same reasoning as EnsurePermission.
        if ($case === null) {
            throw new InvalidArgumentException("Unknown feature [{$feature}].");
        }

        $minimum = $this->resolveGrade($grade);

        $satisfied = $minimum === null
            ? $this->entitlements->allows($case)
            : $this->entitlements->atLeast($case, $minimum);

        if (! $satisfied) {
            // Whether the business is on a plan at all decides which refusal this is — see
            // FeatureNotEntitled. Asked only on the failing path, so the happy path costs nothing.
            throw new FeatureNotEntitled($case, $minimum, $this->entitlements->hasPlan());
        }

        return $next($request);
    }

    private function resolveGrade(?string $grade): ?FeatureGrade
    {
        if ($grade === null) {
            return null;
        }

        $case = FeatureGrade::tryFrom($grade);

        if ($case === null) {
            throw new InvalidArgumentException("Unknown feature grade [{$grade}].");
        }

        return $case;
    }
}
