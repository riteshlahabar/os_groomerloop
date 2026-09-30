<?php

namespace Modules\Entitlements\Http\Controllers\Api\V1;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Entitlements\Http\Resources\PlanResource;
use Modules\Entitlements\Models\Plan;

/**
 * The public price list (spec §2, §25).
 *
 * Unauthenticated on purpose: the marketing site's pricing page and the SPA's pre-signup
 * plan chooser both need it, and there is nothing tenant-specific in a price list. Throttled
 * with the public limiter.
 *
 * Only active plans are listed, so a retired tier keeps entitling its existing subscribers
 * without being offered to anyone new.
 */
final class PlanController
{
    public function __invoke(): AnonymousResourceCollection
    {
        return PlanResource::collection(
            Plan::query()->with('features')->active()->ordered()->get()
        );
    }
}
