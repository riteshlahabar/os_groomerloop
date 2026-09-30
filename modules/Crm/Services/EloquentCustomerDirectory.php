<?php

namespace Modules\Crm\Services;

use Modules\Crm\Contracts\CustomerDirectory;
use Modules\Crm\Domain\CommunicationChannel;
use Modules\Crm\Models\Customer;

final class EloquentCustomerDirectory implements CustomerDirectory
{
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

    public function nameOf(int $customerId): ?string
    {
        return $this->find($customerId)?->fullName();
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
     * Tenant-scoped by the global scope, so a customer id from another business resolves to
     * null here exactly as it 404s over HTTP.
     */
    private function find(int $customerId): ?Customer
    {
        return $this->resolved[$customerId] ??= Customer::query()->find($customerId);
    }
}
