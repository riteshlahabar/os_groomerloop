<?php

namespace Modules\Pets;

use App\Support\ModuleServiceProvider;
use Modules\Crm\Services\MergeParticipants;
use Modules\Onboarding\Services\StepVerifiers;
use Modules\Pets\Contracts\PetDirectory;
use Modules\Pets\Services\CustomersAndPetsVerifier;
use Modules\Pets\Services\EloquentPetDirectory;
use Modules\Pets\Services\PetMergeParticipant;

/**
 * Pets owns the pet half of the core domain (spec §9, §26).
 *
 * It depends on Tenancy, Audit, Entitlements and Crm — the last only through Crm's `Contracts/`,
 * never its models (D-007). Scheduling, Booking, Notifications and Retention will depend on *it*,
 * through `PetDirectory`.
 *
 * Note the direction of both registrations below. Pets reaches into Crm's merge registry and
 * Onboarding's verifier registry to volunteer itself; neither of those modules knows this module
 * exists. That is what made both of them shippable before pets were a thing.
 */
final class PetsServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();

        // Singleton so the per-request memo is shared — Scheduling will ask about the same pet
        // more than once while validating one booking.
        $this->app->singleton(EloquentPetDirectory::class);
        $this->app->alias(EloquentPetDirectory::class, PetDirectory::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Spec §8: merging two customers moves their pets to the survivor. Crm calls this; it does
        // not know what a pet is.
        $this->app->make(MergeParticipants::class)
            ->register(new PetMergeParticipant);

        // Spec §7 step 8, and `D-015`: the step needs customers *and* pets, so Pets owns it and
        // registers it only now that both halves can be answered.
        $this->app->make(StepVerifiers::class)
            ->register($this->app->make(CustomersAndPetsVerifier::class));
    }
}
