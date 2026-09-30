<?php

namespace Modules\Crm;

use App\Support\ModuleServiceProvider;
use Modules\Crm\Contracts\CustomerDirectory;
use Modules\Crm\Services\EloquentCustomerDirectory;
use Modules\Crm\Services\MergeParticipants;

/**
 * Crm owns the customer half of the core domain (spec §8, §26).
 *
 * It depends on Tenancy, Audit and Entitlements, and on nothing else. Pets, Scheduling and
 * Notifications will depend on *it* — through CustomerDirectory for lookups and consent, and
 * through CustomerMergeParticipant to move their own records when two customers are merged
 * (D-007). Neither direction requires Crm to know those modules exist.
 */
final class CrmServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();

        // Singleton so the directory's per-request memo is shared, and so every module
        // asking about consent gets the same answer within one request.
        $this->app->singleton(EloquentCustomerDirectory::class);
        $this->app->alias(EloquentCustomerDirectory::class, CustomerDirectory::class);

        // One registry per process, populated by other modules during boot.
        $this->app->singleton(MergeParticipants::class);
    }
}
