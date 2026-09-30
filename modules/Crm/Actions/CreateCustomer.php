<?php

namespace Modules\Crm\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Crm\Domain\CustomerStatus;
use Modules\Crm\Models\Customer;

/**
 * Add a customer to the book (spec §8).
 */
final class CreateCustomer
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly SyncCustomerTags $tags,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>|null  $tagNames
     */
    public function execute(array $attributes, ?array $tagNames = null): Customer
    {
        return DB::transaction(function () use ($attributes, $tagNames): Customer {
            $customer = Customer::create([
                // A customer added by hand has been spoken to; a lead is someone who filled
                // in a form. Defaulting to Lead would mean every walk-in the front desk
                // types in counts against the §36 conversion rate.
                'status' => CustomerStatus::Active->value,
                ...$attributes,
            ]);

            if ($tagNames !== null) {
                $this->tags->execute($customer, $tagNames);
            }

            $this->audit->record('customer.created', $customer, [
                'name' => $customer->fullName(),
                'source' => $customer->source?->value,
            ]);

            return $customer;
        });
    }
}
