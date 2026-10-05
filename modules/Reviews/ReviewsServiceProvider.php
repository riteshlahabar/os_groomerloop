<?php

namespace Modules\Reviews;

use App\Support\ModuleServiceProvider;
use Modules\Reviews\Contracts\ReviewDestinations;
use Modules\Reviews\Contracts\ReviewMetrics;
use Modules\Reviews\Services\EloquentReviewDestinations;
use Modules\Reviews\Services\EloquentReviewMetrics;

/**
 * Reviews owns spec §20's buildable half: a configured review destination and a manual log of
 * reviews staff saw elsewhere. The provider-integration half (live review pulls, in-app reply)
 * has no subject code — no Google Business/Yelp/Facebook credentials exist in this environment
 * — and stays an honest "Not available yet" gap on the admin screen rather than a second module.
 *
 * Depends on Crm's `CustomerDirectory` (optional customer attribution, read-only) and
 * Entitlements (its routes are entitlement-gated), so it boots after both. Automation and
 * Insights each gain a dependency on this module's contracts in return — binding order does not
 * affect correctness, since Laravel resolves both lazily, but this module is listed after Crm
 * and before either of them in `bootstrap/providers.php` to keep that list's narrative order.
 */
final class ReviewsServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();

        $this->app->bind(ReviewDestinations::class, EloquentReviewDestinations::class);
        $this->app->bind(ReviewMetrics::class, EloquentReviewMetrics::class);
    }
}
