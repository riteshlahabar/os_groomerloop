<?php

namespace Modules\Crm\Http\Controllers\Api\V1;

use Modules\Crm\Actions\MergeCustomers;
use Modules\Crm\Http\Requests\MergeCustomersRequest;
use Modules\Crm\Http\Resources\CustomerResource;
use Modules\Crm\Models\Customer;

/**
 * Combine a duplicate into the record the business wants to keep (spec §8).
 *
 * The survivor is the bound {customer}; the record being absorbed is named in the body. That
 * way round deliberately: the URL identifies what will still exist afterwards, so a
 * mis-click on the wrong row keeps the wrong customer rather than deleting the right one.
 */
final class CustomerMergeController
{
    public function __invoke(
        MergeCustomersRequest $request,
        Customer $customer,
        MergeCustomers $merge,
    ): CustomerResource {
        // Resolved through the tenant-scoped query, not route binding, because it comes
        // from the body. A customer id from another business finds nothing and 404s, the
        // same as if it had been in the URL.
        $loser = Customer::query()->findOrFail($request->mergeCustomerId());

        return CustomerResource::make(
            $merge->execute($customer, $loser)->load('tags')
        );
    }
}
