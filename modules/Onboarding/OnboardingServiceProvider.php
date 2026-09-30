<?php

namespace Modules\Onboarding;

use App\Support\ModuleServiceProvider;
use Modules\Onboarding\Contracts\OnboardingStatus;
use Modules\Onboarding\Services\ChecklistStatus;
use Modules\Onboarding\Services\StepVerifiers;
use Modules\Onboarding\Verifiers\AccountVerifier;
use Modules\Onboarding\Verifiers\BusinessDetailsVerifier;

/**
 * Onboarding owns the spec §7 setup checklist and the business profile.
 *
 * It deliberately owns none of the work the checklist tracks. Services belong to Catalog,
 * staff to Team, hours to Scheduling; each will register a verifier here for its own step
 * (D-007), so this module never reaches into another's tables to find out whether a step is
 * done.
 */
final class OnboardingServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();

        // One registry per process, so verifiers registered by other modules during boot are
        // all visible to the checklist.
        $this->app->singleton(StepVerifiers::class);

        $this->app->bind(OnboardingStatus::class, ChecklistStatus::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerOwnVerifiers();
    }

    /**
     * The two §7 steps this module can answer for itself. Every other verified step is
     * registered by the module that owns the work, as those modules are built.
     */
    private function registerOwnVerifiers(): void
    {
        $verifiers = $this->app->make(StepVerifiers::class);

        $verifiers->register($this->app->make(AccountVerifier::class));
        $verifiers->register($this->app->make(BusinessDetailsVerifier::class));
    }
}
