<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Named rate limiters, defined in one place and applied through throttle middleware.
 *
 * Every limiter is keyed per tenant where a tenant is known, so one busy salon cannot
 * exhaust another salon's allowance — the rate limit is part of tenant isolation
 * (invariant #1), not only of abuse prevention.
 */
class RateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerApiLimiter();
        $this->registerAuthenticationLimiter();
        $this->registerPublicLimiter();
        $this->registerPublicAvailabilityLimiter();
    }

    /**
     * General authenticated API traffic: the React SPA's normal chatter.
     */
    private function registerApiLimiter(): void
    {
        RateLimiter::for('api', static fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip()));
    }

    /**
     * Login, registration and password reset.
     *
     * Deliberately tight, and keyed on the submitted email as well as the IP so that
     * spraying one password across many accounts is throttled just as hard as guessing
     * many passwords for one account.
     */
    private function registerAuthenticationLimiter(): void
    {
        RateLimiter::for('auth', static function (Request $request) {
            $email = (string) $request->input('email');

            return [
                Limit::perMinute(5)->by('auth-ip:'.$request->ip()),
                Limit::perMinute(5)->by('auth-email:'.mb_strtolower($email)),
            ];
        });
    }

    /**
     * Unauthenticated public surfaces: the spec §12 booking page and §14 contact forms.
     *
     * These are the only endpoints a stranger can reach, so they get the smallest budget.
     */
    private function registerPublicLimiter(): void
    {
        RateLimiter::for('public', static fn (Request $request) => Limit::perMinute(30)
            ->by('public:'.$request->ip()));
    }

    /**
     * Open-slots availability only, carved out of the general public budget on 2026-10-03.
     *
     * The booking page's own "find the next open day" search (`loadSlots()` in
     * `resources/views/frontview/booking.blade.php`) can call this one endpoint up to 15 times
     * for a single date pick — a single customer going through the wizard once could exhaust
     * the 30/minute public budget on this endpoint alone, long before touching `/services`,
     * `/staff` or submitting. A read-only availability lookup is also materially cheaper and
     * lower-risk than the public group's write endpoint (`POST appointments`), which stays on
     * the tighter limiter.
     */
    private function registerPublicAvailabilityLimiter(): void
    {
        RateLimiter::for('public-availability', static fn (Request $request) => Limit::perMinute(60)
            ->by('public-availability:'.$request->ip()));
    }
}
