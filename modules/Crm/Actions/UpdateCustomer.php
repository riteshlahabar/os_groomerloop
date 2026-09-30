<?php

namespace Modules\Crm\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Crm\Models\Customer;

/**
 * Edit a customer (spec §8).
 */
final class UpdateCustomer
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly SyncCustomerTags $tags,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>|null  $tagNames  null leaves tags alone; [] clears them
     */
    public function execute(Customer $customer, array $attributes, ?array $tagNames = null): Customer
    {
        return DB::transaction(function () use ($customer, $attributes, $tagNames): Customer {
            $customer->fill($attributes);

            // Captured before save, and only the keys that actually moved. An audit trail
            // that records every field on every edit is one nobody reads.
            $changed = array_keys($customer->getDirty());

            $customer->save();

            if ($tagNames !== null) {
                $this->tags->execute($customer, $tagNames);
            }

            if ($changed !== [] || $tagNames !== null) {
                $this->audit->record('customer.updated', $customer, [
                    'changed' => $changed,
                    'tags_changed' => $tagNames !== null,
                ]);
            }

            return $customer;
        });
    }
}
