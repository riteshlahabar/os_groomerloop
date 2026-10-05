<?php

namespace Modules\Crm\Services;

use Modules\Crm\Actions\CreateCustomer;
use Modules\Crm\Actions\UpsertCustomerTag;
use Modules\Crm\Contracts\CustomerDirectory;
use Modules\Crm\Domain\CommunicationChannel;
use Modules\Crm\Domain\CustomerContactDetails;
use Modules\Crm\Models\Customer;
use Modules\Tenancy\Support\TenantContext;

final class EloquentCustomerDirectory implements CustomerDirectory
{
    public function __construct(
        private readonly CreateCustomer $create,
        private readonly UpsertCustomerTag $upsertTag,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * Memoised per request. Notifications asks about the same customer once per message
     * type, and the answer cannot change mid-request.
     *
     * @var array<int, Customer|null>
     */
    private array $resolved = [];

    public function exists(int $customerId): bool
    {
        return $this->find($customerId) !== null;
    }

    /**
     * Not memoised, and `current()` rather than every row: a business that has archived its only
     * customer has not set up a customer book, and the onboarding checklist should say so. The
     * answer can also change within a request — the import creates the first customers — so a
     * cached "no" would leave the checklist wrong until the next page load.
     */
    public function hasAny(): bool
    {
        return Customer::query()->current()->exists();
    }

    public function nameOf(int $customerId): ?string
    {
        return $this->find($customerId)?->fullName();
    }

    /**
     * One query for the whole page, and it fills the same memo `nameOf()` reads — so a caller
     * that primes a list here makes every subsequent per-row lookup free.
     *
     * Ids that resolve to nothing are memoised as null as well, otherwise a missing customer
     * would be re-queried on every row that mentions it.
     *
     * @param  list<int>  $customerIds
     * @return array<int, string|null>
     */
    public function namesOf(array $customerIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $customerIds)));
        $unresolved = array_values(array_diff($ids, array_keys($this->resolved)));

        if ($unresolved !== []) {
            foreach (Customer::query()->whereKey($unresolved)->get() as $customer) {
                $this->resolved[(int) $customer->getKey()] = $customer;
            }

            foreach ($unresolved as $id) {
                $this->resolved[$id] ??= null;
            }
        }

        $names = [];

        foreach ($ids as $id) {
            $names[$id] = $this->resolved[$id]?->fullName();
        }

        return $names;
    }

    public function mayContact(int $customerId, CommunicationChannel $channel): bool
    {
        // Unknown customer means no. A caller that forgot to check existence still cannot
        // send anything, which is the safe direction for invariant #9 to fail in.
        return $this->find($customerId)?->allowsChannel($channel) ?? false;
    }

    public function mayMarketTo(int $customerId, CommunicationChannel $channel): bool
    {
        return $this->find($customerId)?->allowsMarketingOn($channel) ?? false;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function findOrCreateForPublicBooking(array $attributes): int
    {
        $email = (string) ($attributes['email'] ?? '');

        $existing = Customer::query()->where('email', $email)->first();

        if ($existing !== null) {
            return (int) $existing->getKey();
        }

        return (int) $this->create->execute($attributes)->getKey();
    }

    public function contactDetailsOf(int $customerId): ?CustomerContactDetails
    {
        $customer = $this->find($customerId);

        return $customer === null ? null : new CustomerContactDetails(
            fullName: $customer->fullName(),
            email: $customer->email,
            phone: $customer->phone,
        );
    }

    public function tagCustomer(int $customerId, string $tagName): void
    {
        $customer = $this->find($customerId);

        if ($customer === null) {
            return;
        }

        $tag = $this->upsertTag->execute($tagName);

        if ($tag === null) {
            return;
        }

        // The pivot carries its own tenant_id (see `SyncCustomerTags`'s identical comment) —
        // attach()/sync() do not know about it, so it is supplied explicitly rather than left to
        // fail the NOT NULL constraint. Additive and idempotent: never touches a tag already on
        // the customer, and attaching an already-attached one twice is a no-op, not a duplicate row.
        $customer->tags()->syncWithoutDetaching([
            $tag->getKey() => ['tenant_id' => $this->tenants->id()],
        ]);
    }

    /**
     * Tenant-scoped by the global scope, so a customer id from another business resolves to
     * null here exactly as it 404s over HTTP.
     */
    private function find(int $customerId): ?Customer
    {
        return $this->resolved[$customerId] ??= Customer::query()->find($customerId);
    }
}
