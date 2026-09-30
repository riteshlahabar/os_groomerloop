<?php

namespace Modules\Billing\Http\Controllers\Api\V1;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Billing\Http\Resources\InvoiceResource;
use Modules\Billing\Models\Invoice;

/**
 * Billing history (spec §24).
 */
final class InvoiceController
{
    public function index(): AnonymousResourceCollection
    {
        // Tenant-scoped by the global scope; paginated server-side per spec §33 — a business
        // that has been on the platform for years must not receive every invoice it has ever
        // had in one response.
        return InvoiceResource::collection(
            Invoice::query()->latest('issued_at')->latest('id')->paginate(25)
        );
    }

    /**
     * Route model binding is what makes this tenant-safe, and the binding is resolved after
     * ResolveTenant thanks to the global middleware priority set in bootstrap/app.php
     * (D-014). An invoice belonging to another business is simply not found — 404, not 403,
     * so the response does not confirm it exists.
     */
    public function show(Invoice $invoice): InvoiceResource
    {
        return InvoiceResource::make($invoice);
    }
}
