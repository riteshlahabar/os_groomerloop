<?php

namespace Modules\Crm\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Crm\Actions\CreateCustomer;
use Modules\Crm\Actions\UpdateCustomer;
use Modules\Crm\Domain\CustomerStatus;
use Modules\Crm\Http\Requests\ListCustomersRequest;
use Modules\Crm\Http\Requests\StoreCustomerRequest;
use Modules\Crm\Http\Requests\UpdateCustomerRequest;
use Modules\Crm\Http\Resources\CustomerResource;
use Modules\Crm\Models\Customer;
use Modules\Crm\Services\CustomerIndex;
use Symfony\Component\HttpFoundation\Response;

/**
 * The customer book (spec §8).
 *
 * Merging, importing, exporting, consent and tags are each their own controller — different
 * use cases, different permissions, and the difference between this staying readable and
 * becoming the 400-line file every CRM eventually grows.
 */
final class CustomerController
{
    public function index(ListCustomersRequest $request, CustomerIndex $index): AnonymousResourceCollection
    {
        return CustomerResource::collection($index->paginate($request->filters()));
    }

    public function store(StoreCustomerRequest $request, CreateCustomer $create): JsonResponse
    {
        $customer = $create->execute($request->customerAttributes(), $request->tagNames());

        return CustomerResource::make($customer->load('tags'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Route model binding, resolved after ResolveTenant thanks to the global middleware
     * priority in bootstrap/app.php (D-014). Another business's customer is simply not
     * found — 404, not 403, so the response does not confirm the record exists.
     */
    public function show(Customer $customer): CustomerResource
    {
        return CustomerResource::make($customer->load('tags'));
    }

    public function update(
        UpdateCustomerRequest $request,
        Customer $customer,
        UpdateCustomer $update,
    ): CustomerResource {
        $customer = $update->execute($customer, $request->customerAttributes(), $request->tagNames());

        return CustomerResource::make($customer->load('tags'));
    }

    /**
     * Archives rather than deletes.
     *
     * Invariant #4 and spec §8: a customer carries pets, appointment history and service
     * history, and a business tidying its list must not be able to destroy that. The record
     * leaves the working view and stays queryable.
     */
    public function destroy(Customer $customer, AuditRecorder $audit): JsonResponse
    {
        $customer->status = CustomerStatus::Archived;
        $customer->save();

        $audit->record('customer.archived', $customer, [
            'name' => $customer->fullName(),
        ]);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
