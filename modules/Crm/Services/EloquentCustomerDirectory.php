<?php

namespace Modules\Crm\Services;

use Modules\Audit\Contracts\AuditRecorder;
use Modules\Crm\Actions\CreateCustomer;
use Modules\Crm\Actions\UpsertCustomerTag;
use Modules\Crm\Contracts\CustomerDirectory;
use Modules\Crm\Domain\CommunicationChannel;
use Modules\Crm\Domain\ContactNormaliser;
use Modules\Crm\Domain\CustomerContactDetails;
use Modules\Crm\Domain\CustomerSelfProfile;
use Modules\Crm\Models\Customer;
use Modules\Tenancy\Support\TenantContext;

final class EloquentCustomerDirectory implements CustomerDirectory
{
    public function __construct(
        private readonly CreateCustomer $create,
        private readonly UpsertCustomerTag $upsertTag,
        private readonly TenantContext $tenants,
        private readonly AuditRecorder $audit,
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

    public function findIdByEmail(string $email): ?int
    {
        $customer = Customer::query()
            ->where('email_normalised', ContactNormaliser::email($email))
            ->orderBy('id')
            ->first();

        return $customer?->getKey();
    }

    public function setPassword(int $customerId, string $plainPassword): void
    {
        $customer = Customer::query()->findOrFail($customerId);

        // The model's own `hashed` cast does the actual hashing on save.
        $customer->password = $plainPassword;
        $customer->password_set_at = now();
        $customer->save();

        unset($this->resolved[$customerId]);
    }

    public function selfProfileOf(int $customerId): ?CustomerSelfProfile
    {
        $customer = $this->find($customerId);

        if ($customer === null) {
            return null;
        }

        return new CustomerSelfProfile(
            id: (int) $customer->getKey(),
            firstName: $customer->first_name,
            lastName: $customer->last_name,
            email: $customer->email,
            phone: $customer->phone,
            addressLine1: $customer->address_line_1,
            addressLine2: $customer->address_line_2,
            city: $customer->city,
            state: $customer->state,
            postalCode: $customer->postal_code,
            country: $customer->country,
            customerSince: $customer->created_at?->toDateString(),
        );
    }

    public function updateSelfProfile(int $customerId, array $attributes): bool
    {
        $customer = $this->find($customerId);

        if ($customer === null) {
            return false;
        }

        // An allow-list, not the caller's array. `Customer::$fillable` is the staff-side surface
        // and includes `email`, `status`, `source` and `notes`; a customer editing their own
        // profile may touch none of those, so the keys are filtered here rather than trusted to
        // be filtered by whichever request happens to call this.
        $permitted = array_intersect_key($attributes, array_flip([
            'first_name',
            'last_name',
            'phone',
            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'postal_code',
            'country',
        ]));

        $customer->fill($permitted);

        // `phone_normalised` is what every lookup matches on, so it has to move with `phone` —
        // the staff-side action does this too. Recomputed only when the phone actually changed,
        // so an address-only save does not touch it.
        if ($customer->isDirty('phone')) {
            $customer->phone_normalised = ContactNormaliser::phone($customer->phone);
        }

        $changed = array_keys($customer->getDirty());

        if ($changed === []) {
            return true;
        }

        $customer->save();

        unset($this->resolved[$customerId]);

        // Its own event, deliberately not `customer.updated`: "the customer changed this
        // themselves" and "a staff member changed it" are different facts, and the audit trail is
        // where that difference has to survive — the same reasoning that keeps
        // `customer.portal_password_reset_by_staff` separate from `customer.account_claimed`.
        $this->audit->record('customer.self_profile_updated', $customer, ['changed' => $changed]);

        return true;
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
