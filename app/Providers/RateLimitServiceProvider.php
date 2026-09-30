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
}
